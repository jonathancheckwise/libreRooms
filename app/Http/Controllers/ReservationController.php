<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Contact;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\SystemSettings;
use App\Models\User;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ReservationController extends Controller
{
    use RedirectsBack;

    private function reservationForm(Room $room, ?Reservation $reservation): View
    {
        $room->load('owner', 'discounts', 'options', 'customFields');
        $timezone = $room->getTimezone();

        // Créneaux globaux (La Pépite) pour l'aperçu de prix côté client
        $pep = app(SystemSettings::class);

        // Aperçu de prix (La Pépite). On envoie les DEUX grilles (sans/avec but
        // lucratif) + le contexte : pour un connecté, le statut est figé (compte) ;
        // pour un invité, il est piloté en direct par sa déclaration dans le
        // formulaire. Le serveur reste autoritaire (snapshot à l'enregistrement).
        $authUser = auth()->user();
        $isNonProfit = $authUser?->org_type === 'non_profit';
        $tier = fn (string $mode) => $isNonProfit
            ? ($room->{"price_np_$mode"} ?? $room->{"price_$mode"})
            : $room->{"price_$mode"};
        // Minutes offertes restantes ce mois-ci : connecté = réel ; invité (compte
        // à créer) = quota mensuel plein (60).
        $freeMinutesRemaining = $authUser
            ? ($authUser->is_pepite_member
                ? app(\App\Services\Reservation\PricingService::class)->memberFreeMinutesRemaining($authUser->id, now())
                : 60)
            : 60;

        // Prepare room configuration for JavaScript
        $roomConfig = [
            'settings' => [
                'availability_route' => route('rooms.availability', $room),
                'price_mode' => $room->price_mode->value,
                'price_short' => $room->price_short,
                'price_full_day' => $tier('full_day'),
                'max_hours_short' => $room->max_hours_short,
                'always_short_after' => $room->always_short_after,
                'always_short_before' => $room->always_short_before,
                // La Pépite : tarifs (tier réservant) + créneaux globaux + remise membre
                'price_hourly' => $tier('hourly'),
                'price_half_day' => $tier('half_day'),
                // Contexte tarifaire : invité vs connecté
                'is_guest' => ! $authUser,
                'fixed_org_type' => $authUser?->org_type,
                'fixed_is_member' => (bool) $authUser?->is_pepite_member,
                // Les deux grilles pour la MàJ en direct côté invité
                'prices_np' => ['hourly' => $room->price_np_hourly, 'half_day' => $room->price_np_half_day, 'full_day' => $room->price_np_full_day],
                'prices_lp' => ['hourly' => $room->price_hourly, 'half_day' => $room->price_half_day, 'full_day' => $room->price_full_day],
                // Remise membre = réglage brut (le JS l'applique si le réservant est membre)
                'member_discount_percent' => (int) $pep->member_discount_percent,
                'member_free_minutes_remaining' => $freeMinutesRemaining,
                'hourly_max_hours' => (int) $pep->hourly_max_hours,
                'half_day_morning_start' => substr($pep->half_day_morning_start, 0, 5),
                'half_day_morning_end' => substr($pep->half_day_morning_end, 0, 5),
                'half_day_afternoon_start' => substr($pep->half_day_afternoon_start, 0, 5),
                'half_day_afternoon_end' => substr($pep->half_day_afternoon_end, 0, 5),
                'half_day_evening_start' => substr($pep->half_day_evening_start, 0, 5),
                'half_day_evening_end' => substr($pep->half_day_evening_end, 0, 5),
                'full_day_start' => substr($pep->full_day_start, 0, 5),
                'full_day_end' => substr($pep->full_day_end, 0, 5),
                'allow_late_end_hour' => $room->allow_late_end_hour,
                'reservation_cutoff_days' => $room->reservation_cutoff_days,
                'reservation_advance_limit' => $room->reservation_advance_limit,
                'allowed_weekdays' => $room->allowed_weekdays,
                // Fenêtres de dispo par jour (La Pépite) : ISO 1=lun..7=dim.
                'availability_windows' => $room->availabilityWindows->map(fn ($w) => [
                    'weekday' => (int) $w->weekday,
                    'start' => substr($w->start_time, 0, 5),
                    'end' => substr($w->end_time, 0, 5),
                ])->values(),
                'day_start_time' => $room->day_start_time ? substr($room->day_start_time, 0, 5) : null,
                'day_end_time' => $room->day_end_time ? substr($room->day_end_time, 0, 5) : null,
                'timeZone' => $timezone,
                'currency' => $room->owner->getCurrency(),
                'locale' => str_replace('_', '-', $room->owner->getLocale()),
            ],
            'unavailabilities' => $room->unavailabilities->map(fn ($u) => [
                'start' => $u->start->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'end' => $u->end->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'title' => $u->title,
            ])->values(),
            'options' => $room->options->where('active', true)->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'description' => $o->description,
                'price' => $o->price,
            ])->values(),
            'discounts' => $room->discounts->where('active', true)->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'type' => $d->type->value,
                'restrict_to' => $d->limit_to_contact_type?->value,
                'value' => $d->value,
            ])->values(),
        ];
        // Get enabled discounts
        $enabledDiscounts = old('discounts') ?? $reservation?->discountIds() ?? [];

        // Prepare events data
        if (! is_null(old('events'))) {
            // From old input (validation error)
            $events = [];
            foreach (old('events') as $index => $event) {
                $events[$index] = [
                    'start' => $event['start'],
                    'end' => $event['end'],
                    'uid' => $event['uid'] ?? '',
                    'options' => $event['options'] ?? [],
                    'id' => $index,
                ];
            }
        } elseif (! is_null($reservation?->events)) {
            // From existing reservation
            $sortedEvents = $reservation->events->sortBy('start')->values();

            $events = [];
            foreach ($sortedEvents as $index => $reservationEvent) {
                $events[$index] = [
                    'start' => $reservationEvent->startLocalTz()->format('Y-m-d\TH:i'),
                    'end' => $reservationEvent->endLocalTz()->format('Y-m-d\TH:i'),
                    'uid' => $reservationEvent->uid,
                    'options' => $reservationEvent->options->pluck('id')->toArray(),
                    'id' => $index,
                ];
            }
        } else {
            // Default: single empty event
            $events = [
                0 => [
                    'start' => null,
                    'end' => null,
                    'uid' => null,
                    'options' => [],
                    'id' => 0,
                ],
            ];
        }

        $contacts = auth()->check()
        ? auth()->user()->contacts
        : collect();

        // Ajouter $tenant à $contacts s'il existe et n'est pas déjà dans la collection
        if (isset($reservation) && ! $contacts->contains('id', $reservation->tenant->id)) {
            $contacts = $contacts->push($reservation->tenant);
        }

        $contacts = $contacts->sortBy(fn ($c) => $c->display_name())->values();

        return view('reservations.create', [
            'room' => $room,
            'contacts' => $contacts,
            'reservation' => $reservation,
            'roomConfig' => $roomConfig,
            'enabledDiscounts' => $enabledDiscounts,
            'events' => $events,
        ]);
    }

    public function create(Room $room): View|RedirectResponse
    {
        // Salle réservée aux membres (La Pépite) : un non-connecté est invité à
        // créer un compte plutôt que de recevoir un 403 sec.
        if ($room->members_only && $room->bookable && auth()->guest()) {
            return redirect()->route('register')
                ->with('info', __('This room is reserved for members. Create your account to book it.'));
        }

        $this->authorize('reserve', $room);

        // Salle « sur demande » (La Pépite) : pas de réservation directe, on
        // redirige vers le formulaire de demande spéciale / devis.
        if ($room->on_request) {
            return redirect()->route('special-requests.create', ['room' => $room->id]);
        }

        return $this->reservationForm($room, null);
    }

    public function show(Reservation $reservation): View|RedirectResponse
    {
        $user = auth()->user();
        $room = $reservation->room;

        // User must be moderator/admin of the room OR the tenant must be one of their contacts
        if (! $user->can('manageReservations', $room) && ! $user->canAccessContact($reservation->tenant)) {
            abort(403);
        }

        $reservation->load('invoice');

        return view('reservations.show', [
            'reservation' => $reservation,
            'user' => $user,
        ]);
    }

    public function edit(Reservation $reservation): View|RedirectResponse
    {
        if (! $reservation->isEditable()) {
            return redirect()->route('reservations.index')->with('error', __('Reservation cannot be edited.'));
        }

        return $this->reservationForm($reservation->room, $reservation);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Check if user can access admin view (moderator or admin role)
        $canViewAdmin = $user->can('viewAdmin', Room::class);
        $view = $canViewAdmin ?
            $request->input('view', 'admin') :
            'mine';

        if ($view === 'admin') {
            // Get all room IDs where user has moderator or admin rights (global admins see all via model method)
            $roomIds = $user->getAccessibleRoomIds(UserRole::MODERATOR);

            // Build query with filters
            $query = Reservation::with([
                'room.owner',
                'tenant',
                'events',
                'invoice',
                'customFieldValues',
            ])
                ->whereIn('room_id', $roomIds)
                ->orderBy('created_at', 'desc');

            // Get available rooms and contacts for filters
            $rooms = Room::whereIn('id', $roomIds)->get();

            $contacts = Contact::whereIn('id', function ($query) use ($roomIds) {
                $query->select('tenant_id')
                    ->from('reservations')
                    ->whereIn('room_id', $roomIds)
                    ->distinct();
            })->get()->sortBy(fn ($c) => $c->display_name())->values();
        } else {
            // Get all contact IDs for the logged-in user
            $contactIds = $user->contacts()->pluck('contacts.id');

            // Build query with filters
            $query = Reservation::with([
                'room.owner',
                'tenant',
                'events',
                'invoice',
                'customFieldValues',
            ])
                ->whereIn('tenant_id', $contactIds)
                ->orderBy('created_at', 'desc');

            // Get available rooms and contacts for filters
            $rooms = Room::whereHas('reservations', function ($q) use ($contactIds) {
                $q->whereIn('tenant_id', $contactIds);
            })->get();

            $contacts = $user->contacts->sortBy(fn ($c) => $c->display_name())->values();
        }

        // Apply filters
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reservations = $query->paginate(15)->appends($request->except('page'));

        return view('reservations.index', [
            'reservations' => $reservations,
            'rooms' => $rooms,
            'contacts' => $contacts,
            'user' => $user,
            'view' => $view,
            'canViewAdmin' => $canViewAdmin,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReservationRequest $request, Room $room, ReservationService $service): RedirectResponse
    {
        $request->validated();

        $user = auth()->user();

        // La Pépite : un externe finalise en créant un compte (statut + mot de
        // passe déjà validés). Son statut détermine le tarif appliqué.
        if (! $user) {
            $email = $request->input('email');
            if (User::where('email', $email)->exists()) {
                return back()->withInput()->withErrors([
                    'password' => __('An account already exists with this email. Please log in first (top right), then book.'),
                ]);
            }
            $name = trim($request->input('first_name').' '.$request->input('last_name'))
                ?: ($request->input('entity_name') ?: $email);
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($request->input('password')),
                'org_type' => $request->input('org_type'),
                'is_pepite_member' => $request->boolean('is_pepite_member'),
                'email_verified_at' => now(),
            ]);
            Auth::login($user);
            $request->session()->regenerate();
        }

        $reservation = $service->createFromRequest($request, $room, $user);

        $msg = $reservation->status === ReservationStatus::PENDING ?
            __('New reservation created successfully - pending confirmation.') :
            __('New reservation confirmed successfully.');

        return $this->redirectBack(auth()->user() ? 'reservations.index' : 'rooms.index')->with('success', $msg);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        if (! $reservation->isEditable()) {
            return redirect()->route('reservations.index')->with('error', __('Reservation cannot be edited.'));
        }

        $request->validated();
        $reservation = $service->updateFromRequest(
            $request,
            $reservation,
            auth()->user()
        );

        $msg = $reservation->status === ReservationStatus::PENDING ?
            __('Reservation updated successfully - pending confirmation.') :
            __('Reservation confirmed successfully.');

        return $this->redirectBack('reservations.index')->with('success', $msg);
    }

    /**
     * Directly confirm a reservation without editing its data.
     */
    public function directConfirm(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        if (! $reservation->isEditable()) {
            return $this->redirectBack('reservations.index')->with('error', __('Reservation cannot be confirmed.'));
        }

        if (! auth()->user()->can('manageReservations', $reservation->room)) {
            abort(403);
        }

        $service->directConfirm($reservation, auth()->user(), $request->input('custom_message'));

        return $this->redirectBack('reservations.index')->with('success', __('Reservation confirmed successfully.'));
    }

    /**
     * Cancel a reservation (from index page modal or edit form)
     */
    public function cancel(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $user = auth()->user();

        // Check permissions
        if ($reservation->status === ReservationStatus::PENDING) {
            // Anyone can cancel a pending reservation
            $canCancel = true;
        } elseif ($reservation->status === ReservationStatus::CONFIRMED) {
            // Moderators and admins can cancel confirmed reservations
            $canCancel = $user->can('manageReservations', $reservation->room);
        } else {
            $canCancel = false;
        }

        if (! $canCancel) {
            return redirect()->back()->with('error', __('You do not have permission to cancel this reservation.'));
        }

        // Get modal parameters
        $sendEmail = $request->exists('send_email');
        $reason = $request->input('cancellation_reason');

        // Use service to handle cancellation
        $service->cancel($reservation, $sendEmail, $reason);

        if ($reservation->isPaid()) {
            return $this->redirectBack('reservations.index')->with('success', __('Reservation cancelled. Warning - invoice already paid.'));
        }

        return $this->redirectBack('reservations.index')->with('success', __('Reservation cancelled successfully.'));
    }
}
