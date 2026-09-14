<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un email de confirmation de TEST (design Pépite) via le mailer de
 * l'app (reservations@pepite-lausanne.ch), sans créer de vraie réservation.
 * Usage : php artisan pepite:test-email  (ou avec une adresse en argument).
 */
class SendTestConfirmationEmail extends Command
{
    protected $signature = 'pepite:test-email {email? : Destinataire (défaut : reservations@pepite-lausanne.ch)} {reservation? : ID de réservation à utiliser (défaut : la dernière)}';

    protected $description = 'Envoie un email de confirmation de test (design Pépite) à une adresse.';

    public function handle(SettingsService $settings): int
    {
        $to = $this->argument('email') ?: 'reservations@pepite-lausanne.ch';

        $query = Reservation::with(['room.owner.contact', 'tenant', 'events']);
        $reservation = $this->argument('reservation')
            ? $query->find($this->argument('reservation'))
            : $query->latest('id')->first();

        if (! $reservation) {
            $this->error('Aucune réservation trouvée pour le test.');

            return self::FAILURE;
        }

        $owner = $reservation->room->owner;
        $settings->configureMailer($owner);

        $html = view('emails.confirmation', [
            'reservation' => $reservation,
            'room' => $reservation->room,
            'owner' => $owner,
            'tenant' => $reservation->tenant,
            'invoice' => null,
        ])->render();

        Mail::html($html, function ($message) use ($owner, $to) {
            $message->from($owner->mailSettings()->user, 'La Pépite')
                ->to($to)
                ->subject('[TEST] Confirmation de réservation — La Pépite');
        });

        $this->info("Email de test envoyé à {$to} (réservation #{$reservation->id}).");

        return self::SUCCESS;
    }
}
