<?php

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Contact;
use App\Models\CustomFieldValue;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\ReservationEvent;
use App\Models\Room;
use App\Models\User;
use App\Services\Caldav\CaldavClient;
use App\Services\Mailer\MailService;
use App\Services\Webdav\WebdavUploader;
use App\Support\DateHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

class ReservationService
{
    public function __construct(
        private CaldavClient $caldav,
        private PricingService $pricing,
        private MailService $mail,
    ) {}

    public function createFromRequest(FormRequest $request, Room $room, ?User $user): Reservation
    {
        // Create or find Contact
        $contact = $this->createOrFindContact($request, $user);

        // Determine status and confirmation info.
        // Validation manuelle (La Pépite) : seul un responsable de la salle peut
        // confirmer directement ; un réservant crée toujours une demande PENDING.
        $action = $request->input('action');
        $canConfirm = $user && $user->can('manageReservations', $room);
        $isConfirmed = $action === 'confirm' && $canConfirm;
        $status = $isConfirmed ? ReservationStatus::CONFIRMED : ReservationStatus::PENDING;

        // Snapshot du statut du réservant (fige le tarif appliqué).
        $orgType = $user?->org_type;
        $isMember = (bool) $user?->is_pepite_member;

        // Heure offerte : membre OU responsable (admin), même s'il n'est pas coché « membre ».
        $canFreeHour = $isMember || (bool) $user?->can('manageReservations', $room);

        // Calculate prices first (we need full_price before creating reservation)
        [$eventsWithPrices, $fullPrice, $hourlyMinutes, $totalMinutes] = $this->getEventsWithPrices($request->input('events'), $room, $orgType);

        // Refuse les créneaux hors fenêtres de disponibilité (La Pépite).
        $this->assertWithinAvailabilityWindows($room, $eventsWithPrices);

        // Calculate sum of discounts
        $discountIds = $request->input('discounts', []);
        [$sumDiscounts, $discountsData] = $this->pricing->calculateSumDiscounts($room, $discountIds, $fullPrice);

        // Heure offerte membre (quota mensuel) : gratuité sur les heures, avant la
        // remise -10 %. OPT-IN : le membre coche « Je profite de mon heure offerte »
        // (pas pour les invités, à qui la case n'est pas proposée) ; dispo vérifiée
        // sur le mois DE LA RÉSERVATION (pas le mois courant).
        $freeMinutes = 0;
        $freeAmount = 0.0;
        if ($canFreeHour && $request->boolean('use_free_hour') && $totalMinutes > 0 && ! empty($eventsWithPrices)) {
            $monthAnchor = $eventsWithPrices[0]['start']->copy();
            $available = $this->pricing->memberFreeMinutesRemaining($user?->id, $monthAnchor);
            [$freeMinutes, $freeLine] = $this->pricing->memberFreeHour(
                $canFreeHour, $this->pricing->hourlyRate($room, $orgType), $totalMinutes, $available, $fullPrice
            );
            if ($freeLine) {
                $freeAmount = $freeLine[2];
                $sumDiscounts += $freeAmount;
                $discountsData[] = $freeLine;
            }
        }

        // Remise membre La Pépite (-10 %) sur le prix de base restant (après heure offerte)
        if ($memberDiscount = $this->pricing->memberDiscount($isMember, $fullPrice - $freeAmount)) {
            $sumDiscounts += $memberDiscount[2];
            $discountsData[] = $memberDiscount;
        }

        // Use transaction for DB + CalDAV operations
        $reservation = DB::transaction(function () use (
            $room, $contact, $status, $isConfirmed, $user, $request,
            $eventsWithPrices, $fullPrice, $sumDiscounts, $discountsData,
            $orgType, $isMember, $freeMinutes
        ) {
            // Create Reservation
            $reservation = Reservation::create([
                'room_id' => $room->id,
                'tenant_id' => $contact->id,
                'booked_by_user_id' => $user?->id,
                'hash' => Str::random(32),
                'status' => $status,
                'reservant_org_type' => $orgType,
                'reservant_is_member' => $isMember,
                'free_minutes_applied' => $freeMinutes,
                'title' => $request->input('res_title'),
                'description' => $request->input('res_description'),
                'full_price' => $fullPrice,
                'sum_discounts' => $sumDiscounts,
                'discounts' => $discountsData,
                'special_discount' => $request->input('special_discount'),
                'donation' => $request->input('donation'),
                'custom_message' => $request->input('custom_message'),
                'confirmed_at' => $isConfirmed ? now() : null,
                'confirmed_by' => $isConfirmed ? $user?->id : null,
                // Acceptation des CG : horodatée, versionnée et tracée au moment
                // de la réservation. La case est obligatoire (règle « accepted »
                // dans StoreReservationRequest) : si on arrive ici, elle est cochée.
                'terms_accepted_at' => $request->boolean('accept_terms') ? now() : null,
                'terms_version' => $request->boolean('accept_terms')
                    ? (app(\App\Models\SystemSettings::class)->terms_version ?: null)
                    : null,
            ]);

            // Connect CalDAV (safe even if disabled)
            $this->caldav->connect($room);

            // Create ReservationEvents with calculated prices
            foreach ($eventsWithPrices as $eventData) {
                $event = ReservationEvent::create([
                    'reservation_id' => $reservation->id,
                    'start' => $eventData['start'],
                    'end' => $eventData['end'],
                    'uid' => Str::uuid()->toString(),
                    'price' => $eventData['price'],
                    'price_label' => $eventData['price_label'],
                ]);

                if ($room->usesCaldav()) {
                    $this->caldav->createEvent($event);
                }

                // Attach options to event with their individual prices
                foreach ($eventData['options'] as $optionId) {
                    $option = $room->options->firstWhere('id', $optionId);
                    if ($option) {
                        $event->options()->attach($optionId, [
                            'price' => $option->price,
                        ]);
                    }
                }
            }

            // Create CustomFieldValues
            foreach ($room->customFields->where('active', true) as $customField) {
                $value = $request->input($customField->key);
                CustomFieldValue::fromReservationAndField($reservation, $customField, $value);
            }

            // Create invoice if confirmed
            if ($isConfirmed) {
                $this->createInvoice($reservation);
            }

            return $reservation;
        });

        // Reload reservation with all relations for PDF and email
        $reservation = $reservation->fresh([
            'room.owner.contact',
            'tenant',
            'events',
            'invoice',
        ]);

        // WebDAV uploads and emails (deferred, after response)
        defer(function () use ($room, $reservation, $isConfirmed) {
            if ($room->usesWebdav()) {
                $this->uploadPrebookPdf($reservation);
                if ($isConfirmed && $reservation->invoice) {
                    $this->uploadInvoicePdf($reservation->invoice);
                }
            }
            // Send appropriate email
            if ($isConfirmed && ! $room->disable_mailer) {
                $this->mail->sendConfirmation($reservation);
            } elseif (! $room->disable_mailer) {
                $this->mail->sendNewReservation($reservation);
            }
        });

        return $reservation;
    }

    public function updateFromRequest(FormRequest $request, Reservation $reservation, User $user): Reservation
    {
        // Handle confirm/cancel actions via dedicated methods.
        // Validation manuelle (La Pépite) : seul un responsable peut confirmer.
        $action = $request->input('action');
        $canConfirm = $user->can('manageReservations', $reservation->room);

        if ($action === 'confirm' && $canConfirm) {
            // Already set status confirmed in updateReservationData to avoid doing caldav updates twice
            $this->updateReservationData($request, $reservation, $user, confirm: true);

            return $this->confirm($reservation, $user);
        }

        // Regular update (prepare action)
        return $this->updateReservationData($request, $reservation, $user);
    }

    /**
     * Confirm a pending reservation.
     * Creates invoice and syncs CalDAV events.
     */
    public function confirm(Reservation $reservation, User $user): Reservation
    {
        $room = $reservation->room;

        // Confirmed status already set in updateReservationData
        DB::transaction(function () use ($reservation, $user) {
            // Update reservation status
            $reservation->update([
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
            ]);

            // Caldav Events already synced in updateReservationData

            // Create invoice
            $this->createInvoice($reservation);
        });

        // Reload with invoice
        $reservation = $reservation->fresh([
            'room.owner.contact',
            'tenant',
            'events',
            'invoice',
        ]);

        defer(function () use ($room, $reservation) {
            if ($room->usesWebdav() && $reservation->invoice) {
                $this->uploadInvoicePdf($reservation->invoice);
            }
            if (! $room->disable_mailer) {
                $this->mail->sendConfirmation($reservation);
            }
        });

        return $reservation;
    }

    /**
     * Directly confirm a reservation without editing its data.
     * Updates status, syncs CalDAV events, then delegates to confirm() for invoice/email.
     */
    public function directConfirm(Reservation $reservation, User $user, ?string $customMessage = null): Reservation
    {
        $room = $reservation->room;
        $wasCancelled = $reservation->status === ReservationStatus::CANCELLED;

        DB::transaction(function () use ($reservation, $room, $wasCancelled, $customMessage) {
            $reservation->update([
                'status' => ReservationStatus::CONFIRMED,
                'custom_message' => $customMessage,
            ]);

            // Sync CalDAV events
            if ($room->usesCaldav()) {
                $this->caldav->connect($room);
                foreach ($reservation->events as $event) {
                    if ($wasCancelled) {
                        $this->caldav->createEvent($event);
                    } else {
                        $this->caldav->updateOrCreateEvent($event);
                    }
                }
            }
        });

        return $this->confirm($reservation, $user);
    }

    /**
     * Cancel a reservation.
     * Deletes CalDAV events and cancels associated invoice.
     */
    public function cancel(
        Reservation $reservation,
        bool $sendEmail = true,
        ?string $reason = null
    ): Reservation {
        $room = $reservation->room;

        DB::transaction(function () use ($reservation, $room) {
            // Cancel invoice if exists and not paid
            if ($reservation->invoice && $reservation->invoice->paid_at === null) {
                $reservation->invoice->update([
                    'cancelled_at' => now(),
                ]);
            }

            // Update reservation status
            $reservation->update([
                'status' => ReservationStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);

            // Delete CalDAV events
            if ($room->usesCaldav()) {
                $this->caldav->connect($room);
                foreach ($reservation->events as $event) {
                    $this->caldav->deleteEventSilent($event);
                }
            }
        });

        defer(function () use ($reservation, $room, $sendEmail, $reason) {
            if ($room->usesWebdav()) {
                $this->deletePrebookPdf($reservation);
                if ($reservation->invoice) {
                    $this->deleteInvoicePdf($reservation->invoice);
                }
            }
            if ($sendEmail && ! $room->disable_mailer) {
                $this->mail->sendCancellation($reservation, $reason ?? __('No reason specified'));
            }
        });

        return $reservation->fresh(['invoice']);
    }

    /**
     * Update reservation data without changing status.
     */
    protected function updateReservationData(FormRequest $request, Reservation $reservation, User $user, bool $confirm = false): Reservation
    {
        $contact = $this->createOrFindContact($request, $user);
        $room = $reservation->room;
        $wasCancelled = $reservation->status === ReservationStatus::CANCELLED;

        // On recalcule avec le statut FIGÉ du réservant (snapshot), pas celui de
        // la personne qui édite/confirme — sinon la remise serait perdue.
        $orgType = $reservation->reservant_org_type;
        $isMember = (bool) $reservation->reservant_is_member;

        // Calculate prices for all events in request
        [$eventsWithPrices, $fullPrice, $hourlyMinutes, $totalMinutes] = $this->getEventsWithPrices($request->input('events'), $room, $orgType);

        // Refuse les créneaux hors fenêtres de disponibilité (La Pépite).
        $this->assertWithinAvailabilityWindows($room, $eventsWithPrices);

        // Calculate sum of discounts
        $discountIds = $request->input('discounts', []);
        [$sumDiscounts, $discountsData] = $this->pricing->calculateSumDiscounts($room, $discountIds, $fullPrice);

        // Heure offerte : on réutilise les minutes déjà figées (bornées au temps réservé actuel).
        $freeAmount = 0.0;
        $freeMinutes = (int) min($reservation->free_minutes_applied, $totalMinutes);
        if ($freeMinutes > 0) {
            [$freeMinutes, $freeLine] = $this->pricing->memberFreeHour(
                true, $this->pricing->hourlyRate($room, $orgType), $totalMinutes, $freeMinutes, $fullPrice
            );
            if ($freeLine) {
                $freeAmount = $freeLine[2];
                $sumDiscounts += $freeAmount;
                $discountsData[] = $freeLine;
            }
        }

        // Remise membre La Pépite (-10 %) — sur le prix restant, avec le snapshot
        if ($memberDiscount = $this->pricing->memberDiscount($isMember, $fullPrice - $freeAmount)) {
            $sumDiscounts += $memberDiscount[2];
            $discountsData[] = $memberDiscount;
        }

        DB::transaction(function () use (
            $reservation, $contact, $room, $request,
            $eventsWithPrices, $fullPrice, $sumDiscounts, $discountsData, $wasCancelled, $confirm, $freeMinutes
        ) {
            // Update Reservation
            $reservation->update([
                'tenant_id' => $contact->id,
                'status' => $confirm ? ReservationStatus::CONFIRMED : ReservationStatus::PENDING,
                'title' => $request->input('res_title'),
                'description' => $request->input('res_description'),
                'full_price' => $fullPrice,
                'sum_discounts' => $sumDiscounts,
                'discounts' => $discountsData,
                'free_minutes_applied' => $freeMinutes,
                'special_discount' => $request->input('special_discount'),
                'donation' => $request->input('donation'),
                'custom_message' => $request->input('custom_message'),
            ]);

            // Connect CalDAV
            if ($room->usesCaldav()) {
                $this->caldav->connect($room);
            }

            // Handle ReservationEvents
            $existingEvents = $reservation->events->keyBy('uid');
            $requestEventUids = collect($eventsWithPrices)->pluck('uid')->filter()->toArray();

            // Update or create events
            foreach ($eventsWithPrices as $eventData) {
                if ($eventData['uid'] && $existingEvents->has($eventData['uid'])) {
                    // Update existing event
                    $event = $existingEvents->get($eventData['uid']);
                    $event->update([
                        'start' => $eventData['start'],
                        'end' => $eventData['end'],
                        'price' => $eventData['price'],
                        'price_label' => $eventData['price_label'],
                    ]);
                    if ($room->usesCaldav() && ! $wasCancelled) {
                        $this->caldav->updateOrCreateEvent($event);
                    }

                    // Sync options with their prices
                    $optionSyncData = [];
                    foreach ($eventData['options'] as $optionId) {
                        $option = $room->options->firstWhere('id', $optionId);
                        if ($option) {
                            $optionSyncData[$optionId] = ['price' => $option->price];
                        }
                    }
                    $event->options()->sync($optionSyncData);
                } else {
                    // Create new event
                    $event = ReservationEvent::create([
                        'reservation_id' => $reservation->id,
                        'start' => $eventData['start'],
                        'end' => $eventData['end'],
                        'uid' => Str::uuid()->toString(),
                        'price' => $eventData['price'],
                        'price_label' => $eventData['price_label'],
                    ]);

                    if ($room->usesCaldav() && ! $wasCancelled) {
                        $this->caldav->createEvent($event);
                    }

                    // Attach options with their prices
                    foreach ($eventData['options'] as $optionId) {
                        $option = $room->options->firstWhere('id', $optionId);
                        if ($option) {
                            $event->options()->attach($optionId, [
                                'price' => $option->price,
                            ]);
                        }
                    }
                }
                if ($room->usesCaldav() && $wasCancelled) {
                    // In this case, all events need to be recreated on caldav server
                    $this->caldav->createEvent($event);
                }
            }

            // Delete events that are no longer in the request
            foreach ($existingEvents as $existingEvent) {
                if (! in_array($existingEvent->uid, $requestEventUids)) {
                    if ($room->usesCaldav() && ! $wasCancelled) {
                        $this->caldav->deleteEventSilent($existingEvent);
                    }
                    $existingEvent->delete();
                }
            }

            // Delete and recreate CustomFieldValues
            $reservation->customFieldValues()->delete();
            foreach ($room->customFields->where('active', true) as $customField) {
                $value = $request->input($customField->key);
                CustomFieldValue::fromReservationAndField($reservation, $customField, $value);
            }

        });

        // Reload reservation
        $reservation = $reservation->fresh([
            'room.owner.contact',
            'tenant',
            'events',
        ]);

        // Update prebook PDF if WebDAV enabled and status is PENDING
        if ($room->usesWebdav() && $reservation->status === ReservationStatus::PENDING) {
            defer(fn () => $this->uploadPrebookPdf($reservation));
        }

        return $reservation;
    }

    /**
     * Create an invoice for a confirmed reservation.
     */
    protected function createInvoice(Reservation $reservation): Invoice
    {
        $owner = $reservation->room->owner;

        $firstDueAt = Invoice::calculateFirstDueAt($reservation);

        return Invoice::updateOrCreate([
            'reservation_id' => $reservation->id,
        ],
            [
                'owner_id' => $owner->id,
                'number' => Invoice::generateNumber($owner),
                'amount' => $reservation->finalPrice(),
                'first_issued_at' => now(),
                'issued_at' => now(),
                'first_due_at' => $firstDueAt,
                'due_at' => $firstDueAt,
                'reminder_count' => 0,
                'cancelled_at' => null,
                'paid_at' => null,
            ]);
    }

    /**
     * Upload prebook PDF to WebDAV.
     */
    protected function uploadPrebookPdf(Reservation $reservation): void
    {
        if (! $reservation->room->usesWebdav()) {
            return;
        }

        $path = PDFService::getPrebookFilename($reservation);
        $pdfContents = (new PDFService)->generatePrebookingPDF($reservation);

        (new WebdavUploader)
            ->setOwner($reservation->room->owner)
            ->uploadFileContents($pdfContents, $path);
    }

    /**
     * Upload invoice PDF to WebDAV.
     */
    protected function uploadInvoicePdf(Invoice $invoice): void
    {
        $reservation = $invoice->reservation;
        if (! $reservation->room->usesWebdav()) {
            return;
        }

        $path = PDFService::getInvoiceFilename($invoice);
        $pdfContents = (new PDFService)->generateInvoicePDF($reservation);

        (new WebdavUploader)
            ->setOwner($reservation->room->owner)
            ->uploadFileContents($pdfContents, $path);
    }

    /**
     * Delete prebook PDF from WebDAV (silent on error).
     */
    protected function deletePrebookPdf(Reservation $reservation): void
    {
        if (! $reservation->room->usesWebdav()) {
            return;
        }

        $path = PDFService::getPrebookFilename($reservation);
        (new WebdavUploader)
            ->setOwner($reservation->room->owner)
            ->deleteSilent($path);
    }

    /**
     * Delete invoice PDF from WebDAV (silent on error).
     */
    protected function deleteInvoicePdf(Invoice $invoice): void
    {
        $reservation = $invoice->reservation;
        if (! $reservation->room->usesWebdav()) {
            return;
        }

        $path = PDFService::getInvoiceFilename($invoice);
        (new WebdavUploader)
            ->setOwner($reservation->room->owner)
            ->deleteSilent($path);
    }

    /**
     * Refuse tout créneau hors des fenêtres de disponibilité de la salle
     * (La Pépite). Sans fenêtre définie, ne restreint rien.
     */
    protected function assertWithinAvailabilityWindows(Room $room, array $eventsWithPrices): void
    {
        if (! $room->hasAvailabilityWindows()) {
            return;
        }

        foreach ($eventsWithPrices as $ev) {
            if (! $room->isWithinAvailabilityWindows($ev['start'], $ev['end'])) {
                throw ValidationException::withMessages([
                    'events' => __('This room can only be booked within its available time windows.'),
                ]);
            }
        }
    }

    protected function getEventsWithPrices(array $eventsData, $room, ?string $orgType = null): array
    {
        $fullPrice = 0;
        $hourlyMinutes = 0.0;
        $totalMinutes = 0.0;
        $eventsWithPrices = [];
        $timezone = $room->getTimezone();

        foreach ($eventsData as $eventData) {
            $startAt = DateHelper::fromLocalInput($eventData['start'], $timezone);
            $endAt = DateHelper::fromLocalInput($eventData['end'], $timezone);
            $uid = $eventData['uid'] ?? null;

            // Calculate event price (without options) — tarif selon le statut du réservant
            $eventPricing = $this->pricing->calculateEventPrice($startAt, $endAt, $room, $orgType);
            // Calculate options price
            $optionIds = $eventData['options'] ?? [];
            $optionsPricing = $this->pricing->calculateOptionsPrice($optionIds, $room);

            // Combine event and options pricing
            $totalPrice = $eventPricing['price'] + $optionsPricing['price'];
            $fullLabel = $optionsPricing['label']
                ? $eventPricing['label'].' - '.$optionsPricing['label']
                : $eventPricing['label'];

            $fullPrice += $totalPrice;
            $hourlyMinutes += $eventPricing['hourly_minutes'] ?? 0;
            $totalMinutes += $eventPricing['total_minutes'] ?? 0;

            $eventsWithPrices[] = [
                'uid' => $uid,
                'start' => $startAt,
                'end' => $endAt,
                'options' => $optionIds,
                'price' => $totalPrice,
                'price_label' => $fullLabel,
            ];
        }

        return [$eventsWithPrices, $fullPrice, $hourlyMinutes, $totalMinutes];
    }

    protected function createOrFindContact(FormRequest $request, ?User $user): Contact
    {
        // Prepare contact data from request
        $contactData = [
            'type' => $request->input('contact_type'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'entity_name' => $request->input('entity_name'),
            'email' => $request->input('email'),
            'invoice_email' => $request->input('invoice_email'),
            'phone' => $request->input('phone'),
            'street' => $request->input('street'),
            'zip' => $request->input('zip'),
            'city' => $request->input('city'),
        ];

        // If contact_id is provided, find and update if needed
        if ($request->filled('contact_id')) {
            $contact = Contact::findOrFail($request->input('contact_id'));

            // Check if any field has changed
            $hasChanges = false;
            foreach ($contactData as $key => $value) {
                if ($contact->{$key} != $value) {
                    $hasChanges = true;
                    break;
                }
            }

            // Update contact if changes detected
            if ($hasChanges) {
                $contact->update($contactData);
            }

            return $contact;
        }

        // Create new contact
        $contact = Contact::create($contactData);

        // Attach contact to user if logged in
        if ($user) {
            $contact->users()->attach($user->id);
        }

        return $contact;
    }
}
