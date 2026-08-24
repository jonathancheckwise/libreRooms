<?php

namespace App\Services\Mailer;

use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Reservation;
use App\Models\SpecialRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Redirect email to debug address if configured.
     * When MAIL_DEBUG_REDIRECT is set in .env, all emails go to that address.
     */
    private function redirectIfDebug(mixed $email): mixed
    {
        $debugRedirect = config('mail.debug_redirect');

        if ($debugRedirect) {
            return $debugRedirect;
        }

        return $email;
    }

    public function sendNewReservation(Reservation $reservation): void
    {
        $room = $reservation->room;
        $owner = $room->owner;
        $tenant = $reservation->tenant;
        $this->configureMailer($owner);

        Mail::send('emails.new-reservation-admin', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
        ], function ($message) use ($room, $owner) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($owner->contact->email), $owner->contact->display_name())
                ->subject(ucfirst($room->name).' - '.__('New reservation request to review'));
        });
        Mail::send('emails.new-reservation', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
        ], function ($message) use ($room, $owner, $tenant) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($tenant->email), $tenant->display_name())
                ->replyTo($owner->contact->email, $owner->contact->display_name())
                ->subject(ucfirst($room->name).' - '.__('Your reservation request'));
        });
    }

    public function sendConfirmation(Reservation $reservation): void
    {
        $room = $reservation->room;
        $owner = $room->owner;
        $tenant = $reservation->tenant;

        $this->configureMailer($owner);

        Mail::send('emails.confirmation', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
            'tenant' => $tenant,
            'invoice' => $reservation->invoice,
        ], function ($message) use ($room, $owner, $tenant, $reservation) {
            // Une demande retouchée avant validation se signale dès l'objet :
            // c'est la seule ligne qu'on lit à coup sûr.
            $objet = ucfirst($room->name).' - '.__('Reservation confirmation and invoice (automatic email)');
            if ($reservation->changesBeforeConfirmation()->isNotEmpty()) {
                $objet = __('[Modified]').' '.$objet;
            }
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($tenant->bothEmailsUnique()))
                ->cc($this->redirectIfDebug($owner->contact->email), $owner->contact->display_name())
                ->replyTo($owner->contact->email, $owner->contact->display_name())
                ->subject($objet);
        });
    }

    // Function used to send invoice only, when invoice is regenerated
    // Function used to cancel invoice only

    public function sendInvoice(Reservation $reservation, ?string $complement = null): void
    {
        $room = $reservation->room;
        $owner = $room->owner;
        $tenant = $reservation->tenant;

        $this->configureMailer($owner);

        Mail::send('emails.new-invoice', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
            'invoice' => $reservation->invoice,
            'complement' => $complement,
        ], function ($message) use ($room, $owner, $tenant) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($tenant->invoiceEmail()), $tenant->display_name())
                ->cc($this->redirectIfDebug([$tenant->email, $owner->contact->email]))
                ->replyTo($owner->contact->email, $owner->contact->display_name())
                ->subject(ucfirst($room->name).' - '.__('New invoice (automatic email)'));
        });
    }

    public function cancelInvoice(Reservation $reservation, ?string $complement = null): void
    {
        $room = $reservation->room;
        $owner = $room->owner;
        $tenant = $reservation->tenant;

        $this->configureMailer($owner);

        Mail::send('emails.cancel-invoice', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
            'invoice' => $reservation->invoice,
            'complement' => $complement,
        ], function ($message) use ($reservation, $owner, $tenant) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($tenant->invoiceEmail()), $tenant->display_name())
                ->cc($this->redirectIfDebug([$tenant->email, $owner->contact->email]))
                ->replyTo($owner->contact->email, $owner->contact->display_name())
                ->subject(__('Invoice cancellation').' '.$reservation->invoice->number.' '.__('(automatic email)'));
        });
    }

    public function sendCancellation(Reservation $reservation, ?string $complement = null): void
    {
        $room = $reservation->room;
        $owner = $room->owner;
        $tenant = $reservation->tenant;

        $this->configureMailer($owner);

        Mail::send('emails.cancellation', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
            'tenant' => $tenant,
            'complement' => $complement,
        ], function ($message) use ($room, $owner, $tenant) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($tenant->bothEmailsUnique()))
                ->cc($this->redirectIfDebug($owner->contact->email), $owner->contact->display_name())
                ->replyTo($owner->contact->email, $owner->contact->display_name())
                ->subject(ucfirst($room->name).' - '.__('Your reservation cancellation (automatic email)'));
        });
    }

    public function sendReminder(Invoice $invoice): void
    {
        $reservation = $invoice->reservation;
        $room = $reservation->room;
        $owner = $room->owner;
        $tenant = $reservation->tenant;

        $this->configureMailer($owner);

        Mail::send('emails.reminder', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
            'tenant' => $tenant,
            'invoice' => $invoice,
        ], function ($message) use ($room, $owner, $tenant, $invoice) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($tenant->invoiceEmail()), $tenant->display_name())
                ->cc($this->redirectIfDebug([$tenant->email, $owner->contact->email]))
                ->replyTo($owner->contact->email, $owner->contact->display_name())
                ->subject(__('Rental of :room - Invoice to pay - :reminder', ['room' => $room->name, 'reminder' => $invoice->formattedReminderCount()]));
        });
    }

    public function sendLateInvoicesReminder(Owner $owner, int $lateCount): void
    {
        $this->configureMailer($owner);

        Mail::send('emails.late-invoices-reminder', [
            'owner' => $owner,
            'lateCount' => $lateCount,
        ], function ($message) use ($owner, $lateCount) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($owner->contact->email), $owner->contact->display_name())
                ->subject(__('Reminder: :count late invoice(s)', ['count' => $lateCount]));
        });
    }

    /**
     * Notifie l'équipe (propriétaire) d'une nouvelle demande spéciale (La Pépite).
     */
    public function sendSpecialRequest(SpecialRequest $request, Owner $owner): void
    {
        $this->configureMailer($owner);

        Mail::send('emails.special-request-admin', [
            'request' => $request,
            'owner' => $owner,
        ], function ($message) use ($owner, $request) {
            $message->from($owner->mailSettings()->user, $owner->contact->display_name())
                ->to($this->redirectIfDebug($owner->contact->email), $owner->contact->display_name())
                ->replyTo($request->email, $request->name)
                ->subject(__('New special request to review'));
        });
    }

    protected function configureMailer($owner): void
    {
        // Use centralized mailer configuration from SettingsService
        app(SettingsService::class)->configureMailer($owner);
    }
}
