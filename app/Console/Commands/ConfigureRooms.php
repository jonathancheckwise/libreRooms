<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

/**
 * Configure les salles de La Pépite d'après le document validé par les
 * propriétaires (prix bi-tarif, « sur demande », équipements, fenêtres de
 * disponibilité, flags). Idempotent : peut être relancé sans risque.
 *
 *   php artisan pepite:configure-rooms --dry-run   (aperçu, ne modifie rien)
 *   php artisan pepite:configure-rooms             (applique)
 */
class ConfigureRooms extends Command
{
    protected $signature = 'pepite:configure-rooms {--dry-run : Affiche ce qui serait fait sans rien modifier}';

    protected $description = 'Configure les salles La Pépite (tarifs, équipements, fenêtres) depuis le document.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? '— MODE APERÇU (aucune modification) —' : '— Application de la configuration —');

        $matched = 0;
        $missing = [];

        foreach ($this->roomConfigs() as $cfg) {
            $room = $this->findRoomByName($cfg['name']);
            if (! $room) {
                $missing[] = $cfg['name'];
                continue;
            }
            $matched++;
            $this->line("• <info>{$room->name}</info> ← « {$cfg['name']} »");

            $windows = $cfg['windows'] ?? [];
            unset($cfg['name'], $cfg['windows']);

            if ($dry) {
                $summary = collect($cfg)->map(fn ($v, $k) => $k.'='.(is_array($v) ? implode('/', $v) : ($v === true ? 'oui' : ($v === false ? 'non' : $v))))->implode('  ');
                $this->line("    {$summary}");
                if ($windows) {
                    $this->line('    fenêtres: '.collect($windows)->map(fn ($w) => "j{$w[0]} {$w[1]}-{$w[2]}")->implode(', '));
                }
                continue;
            }

            $room->fill($cfg);
            $room->save();

            $room->availabilityWindows()->delete();
            foreach ($windows as $w) {
                $room->availabilityWindows()->create(['weekday' => $w[0], 'start_time' => $w[1], 'end_time' => $w[2]]);
            }
        }

        $this->newLine();
        $this->info("Salles configurées : {$matched}");
        if ($missing) {
            $this->warn('Salles du document NON trouvées (nom à vérifier côté admin) :');
            foreach ($missing as $m) {
                $this->line("  - {$m}");
            }
        }
        $extra = Room::whereNotIn('id', $this->matchedIds)->pluck('name');
        if ($extra->isNotEmpty()) {
            $this->newLine();
            $this->comment('Salles non gérées par la commande — à configurer à la main (ex. espaces communautaires La Secrète, L\'Accueil, La Coworking, Cabine acoustique, La Cuisine) :');
            foreach ($extra as $n) {
                $this->line("  - {$n}");
            }
        }

        return self::SUCCESS;
    }

    protected array $matchedIds = [];

    /** Recherche tolérante par nom (minuscules, sans accents ni ponctuation). */
    protected function findRoomByName(string $name): ?Room
    {
        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower(trim($s))));
        $target = $norm($name);

        foreach (Room::all() as $room) {
            if ($norm($room->name) === $target) {
                $this->matchedIds[] = $room->id;

                return $room;
            }
        }

        return null;
    }

    /**
     * Données issues du document « La Pépite — Réservation des salles » (v2).
     * Prix : [horaire, demi-journée, journée]. np = sans but lucratif,
     * lucr = à but lucratif. Fenêtres : [jourISO(1=lun..7=dim), 'HH:MM', 'HH:MM'].
     * Équipements : « à compléter » dans le doc → minimum raisonnable, à affiner.
     */
    protected function roomConfigs(): array
    {
        // Salles privatisables (bi-tarif). Réservables en ligne lun–ven
        // 08:00–22:00 (créneau soir 18–22 possible). Week-ends = sur demande
        // (bouton « Demande spéciale »).
        $priced = [
            ['La Petite Sérieuse', [25, 60, 100], [35, 120, 200], ['screen', 'flipchart', 'wifi']],
            ['La Grande Sérieuse', [35, 120, 200], [45, 160, 290], ['screen', 'flipchart', 'wifi']],
            ['La Dynamique', [45, 120, 200], [55, 200, 380], ['wifi']],
            ['La Focus', [20, 50, 90], [30, 100, 180], ['wifi']],
        ];

        $configs = [];
        foreach ($priced as [$name, $np, $lucr, $equip]) {
            $configs[] = [
                'name' => $name,
                'active' => true, 'is_public' => true, 'on_request' => false,
                'bookable' => true, 'booking_optional' => false,
                'price_mode' => 'fixed',
                'price_np_hourly' => $np[0], 'price_np_half_day' => $np[1], 'price_np_full_day' => $np[2],
                'price_hourly' => $lucr[0], 'price_half_day' => $lucr[1], 'price_full_day' => $lucr[2],
                'equipments' => $equip,
                'allowed_weekdays' => ['1', '2', '3', '4', '5'],
                'day_start_time' => '08:00', 'day_end_time' => '22:00',
            ];
        }

        // La Focus : privatisée par la Pépite mar/mer/ven 9h-13h. Réservation
        // NORMALE (pas « jour seul ») ; les responsables refusent ces créneaux
        // à la validation. On ajoute une note dans la description.
        foreach ($configs as &$c) {
            if ($c['name'] === 'La Focus') {
                $c['custom_message'] = "Salle privatisée par la Pépite les mardis, mercredis et vendredis de 9h à 13h : merci de ne pas réserver ces créneaux.";
            }
        }
        unset($c);

        // La Chill : tarif réduit, réservation facultative mais conseillée.
        $configs[] = [
            'name' => 'La Chill',
            'active' => true, 'is_public' => true, 'on_request' => false,
            'bookable' => true, 'booking_optional' => true,
            'price_mode' => 'fixed',
            'price_np_hourly' => 15, 'price_np_half_day' => 40, 'price_np_full_day' => 60,
            'price_hourly' => 25, 'price_half_day' => 50, 'price_full_day' => 80,
            'equipments' => ['wifi'],
            'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            'day_start_time' => '08:00', 'day_end_time' => '22:00',
        ];

        // Salles « sur demande » (devis) : Big Room, Place du Village, Atelier.
        foreach (['La Big Room' => ['screen', 'sound', 'wifi'], 'La Place du Village' => ['wifi'], "L'Atelier" => ['wifi']] as $name => $equip) {
            $configs[] = [
                'name' => $name,
                'active' => true, 'is_public' => true, 'on_request' => true,
                'bookable' => true, 'booking_optional' => false,
                'price_mode' => 'fixed', 'price_full_day' => 0,
                'equipments' => $equip,
                'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            ];
        }

        // Espaces communautaires à fenêtres (gratuits, « au jour »).
        $communityWindows = [
            [1, '09:00', '17:00'], [2, '13:00', '17:00'], [3, '13:00', '17:00'],
            [4, '09:00', '17:00'], [5, '13:00', '17:00'],
        ];
        // La Douce : réservable au jour selon ses horaires.
        $configs[] = [
            'name' => 'La Douce',
            'active' => true, 'is_public' => true, 'on_request' => false,
            'bookable' => true, 'booking_optional' => false,
            'price_mode' => 'fixed',
            'price_np_hourly' => 0, 'price_np_half_day' => 0, 'price_np_full_day' => 0,
            'price_hourly' => 0, 'price_half_day' => 0, 'price_full_day' => 0,
            'equipments' => ['wifi'],
            'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            'day_start_time' => null, 'day_end_time' => null,
            'windows' => $communityWindows,
        ];
        // La Garderie : NON réservable, horaires affichés.
        $configs[] = [
            'name' => 'La Garderie',
            'active' => true, 'is_public' => true, 'on_request' => false,
            'bookable' => false, 'booking_optional' => false,
            'price_mode' => 'fixed', 'price_full_day' => 0,
            'equipments' => [],
            'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            'windows' => $communityWindows,
        ];

        return $configs;
    }
}
