<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Contact;
use App\Models\Reservation;
use App\Models\ReservationEvent;
use App\Models\Room;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un email de confirmation de TEST (design Pépite) via le mailer de
 * l'app (reservations@pepite-lausanne.ch), sans créer de vraie réservation.
 *
 * ⚠️ N'UTILISE JAMAIS DE DONNÉES CLIENT RÉELLES : la réservation, le·la
 * réservataire et les créneaux sont FICTIFS (non enregistrés en base). Seule
 * la salle (et donc le propriétaire = La Pépite) est réelle, car l'email doit
 * afficher le vrai nom de salle et la vraie adresse de l'association.
 *
 * Usage : php artisan pepite:test-email  (ou avec une adresse en argument).
 */
class SendTestConfirmationEmail extends Command
{
    protected $signature = 'pepite:test-email {email? : Destinataire (défaut : reservations@pepite-lausanne.ch)} {room? : ID ou slug de salle (défaut : la première)}';

    protected $description = 'Envoie un email de confirmation de test (design Pépite, données FICTIVES) à une adresse.';

    public function handle(SettingsService $settings): int
    {
        $to = $this->argument('email') ?: 'reservations@pepite-lausanne.ch';

        // Salle réelle (La Pépite) — pour le vrai nom + la vraie adresse.
        $roomArg = $this->argument('room');
        $room = $roomArg
            ? Room::with('owner.contact')->where('id', $roomArg)->orWhere('slug', $roomArg)->first()
            : Room::with('owner.contact')->first();

        if (! $room) {
            $this->error('Aucune salle trouvée pour le test.');

            return self::FAILURE;
        }

        // --- Réservation FICTIVE, non persistée (aucune donnée client réelle) ---
        $reservation = new Reservation([
            'title' => 'Réservation de démonstration',
            'description' => 'Ceci est un email de test — aucune réservation réelle.',
            'status' => ReservationStatus::CONFIRMED,
            'admin_price' => 120.00, // montant fixe → évite tout calcul tarifaire
            'hash' => 'DEMO-TEST',
            'terms_accepted_at' => Carbon::now(),
            'terms_version' => app(\App\Models\SystemSettings::class)->terms_version,
        ]);
        $reservation->setRelation('room', $room);
        $reservation->setRelation('modifications', collect());

        // Réservataire fictif.
        $tenant = new Contact([
            'first_name' => 'Prénom',
            'last_name' => 'Exemple',
            'email' => 'exemple@example.test',
        ]);
        $reservation->setRelation('tenant', $tenant);

        // Créneau fictif : demain 9h → 13h (heure locale de la salle).
        $tz = $room->getTimezone();
        $start = Carbon::tomorrow($tz)->setTime(9, 0);
        $end = Carbon::tomorrow($tz)->setTime(13, 0);
        $event = new ReservationEvent([
            'start' => $start,
            'end' => $end,
            'uid' => 'demo-uid',
            'price' => 120.00,
            'price_label' => 'Demi-journée',
        ]);
        $event->setRelation('reservation', $reservation);
        $reservation->setRelation('events', collect([$event]));

        $owner = $room->owner;
        $settings->configureMailer($owner);

        $html = view('emails.confirmation', [
            'reservation' => $reservation,
            'room' => $room,
            'owner' => $owner,
            'tenant' => $tenant,
            'invoice' => null,
        ])->render();

        Mail::html($html, function ($message) use ($owner, $to) {
            $message->from($owner->mailSettings()->user, 'La Pépite')
                ->to($to)
                ->subject('[TEST] Confirmation de réservation — La Pépite');
        });

        $this->info("Email de test (données fictives) envoyé à {$to} — salle « {$room->name} ».");

        return self::SUCCESS;
    }
}
