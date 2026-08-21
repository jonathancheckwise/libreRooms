<?php

namespace App\Console\Commands;

use App\Models\Owner;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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

        $owner = Owner::query()->orderBy('id')->first();
        if (! $owner) {
            $this->error("Aucun propriétaire (Owner) en base. Crée d'abord un propriétaire, puis relance.");

            return self::FAILURE;
        }

        // Créneaux globaux (La Pépite) : matin 9-13, après-midi 13-17,
        // soir 17-21, journée 9-17.
        $this->line('• <info>Créneaux globaux</info> : matin 9-13 · après-midi 13-17 · soir 17-21 · journée 9-17');
        if (! $dry) {
            $s = \App\Models\SystemSettings::first();
            if ($s) {
                $s->half_day_morning_start = '09:00';
                $s->half_day_morning_end = '13:00';
                $s->half_day_afternoon_start = '13:00';
                $s->half_day_afternoon_end = '17:00';
                $s->half_day_evening_start = '17:00';
                $s->half_day_evening_end = '21:00';
                $s->full_day_start = '09:00';
                $s->full_day_end = '17:00';
                $s->save();
            }
        }

        $created = 0;
        $updated = 0;

        foreach ($this->roomConfigs() as $cfg) {
            $name = $cfg['name'];
            $windows = $cfg['windows'] ?? [];
            unset($cfg['name'], $cfg['windows']);

            $room = $this->findRoomByName($name);
            $isNew = ! $room;
            $this->line('• '.($isNew ? '<comment>À CRÉER</comment>' : '<info>MAJ</info>')." — {$name}");

            if ($dry) {
                $summary = collect($cfg)->map(fn ($v, $k) => $k.'='.(is_array($v) ? implode('/', $v) : ($v === true ? 'oui' : ($v === false ? 'non' : $v))))->implode('  ');
                $this->line("    {$summary}");
                if ($windows) {
                    $this->line('    fenêtres: '.collect($windows)->map(fn ($w) => "j{$w[0]} {$w[1]}-{$w[2]}")->implode(', '));
                }
                continue;
            }

            if ($isNew) {
                $room = new Room;
                $room->owner_id = $owner->id;
                $room->name = $name;
                $room->slug = $this->uniqueSlug($name);
                // Champs obligatoires : adresse La Pépite (Lausanne) + enums par défaut.
                $room->street = 'Avenue de la Gare 10';
                $room->postal_code = '1003';
                $room->city = 'Lausanne';
                $room->country = 'Suisse';
                $room->latitude = 46.51700000;
                $room->longitude = 6.63300000;
                $room->charter_mode = 'none';
                $room->embed_calendar_mode = 'disabled';
                $room->calendar_view_mode = 'slot';
                $room->timezone = 'Europe/Zurich';
                $created++;
            } else {
                $updated++;
            }

            $room->fill($cfg);
            $room->save();
            $this->matchedIds[] = $room->id;

            $room->availabilityWindows()->delete();
            foreach ($windows as $w) {
                $room->availabilityWindows()->create(['weekday' => $w[0], 'start_time' => $w[1], 'end_time' => $w[2]]);
            }
        }

        $this->newLine();
        $this->info($dry ? 'Aperçu terminé (rien modifié).' : "Salles créées : {$created} · mises à jour : {$updated}");

        if (! $dry) {
            $extra = Room::whereNotIn('id', $this->matchedIds)->pluck('name');
            if ($extra->isNotEmpty()) {
                $this->newLine();
                $this->comment('Autres salles en base, non gérées par la commande (à configurer à la main : La Secrète, L\'Accueil, La Coworking, Cabine acoustique, La Cuisine…) :');
                foreach ($extra as $n) {
                    $this->line("  - {$n}");
                }
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

    /** Génère un slug unique à partir du nom. */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'salle';
        $slug = $base;
        $i = 2;
        while (Room::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
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
        // Format : [nom, [np h/½j/j], [lucratif h/½j/j], [équipements], description]
        $priced = [
            ['La Petite Sérieuse', [25, 60, 100], [35, 120, 200], ['screen', 'flipchart', 'wifi'],
                "Salle de réunion. Table, chaises, écran/beamer, flip-chart.\n\n24 m² · 6–8 personnes"],
            ['La Grande Sérieuse', [35, 120, 200], [45, 160, 290], ['screen', 'flipchart', 'wifi'],
                "Grande salle de réunion. Tables, chaises, écran/beamer, flip-chart.\n\n40 m² · 8–10 personnes"],
            ['La Dynamique', [45, 120, 200], [55, 200, 380], ['screen', 'wifi'],
                "Espace séances, coworking libre, formations, yoga/pilates.\n\n25 m² · 10–12 personnes"],
            ['La Focus', [20, 50, 90], [30, 100, 180], ['wifi'],
                "Salle de réunion / coworking silencieux / espaces de travail individuels.\n\n28 m² · 3–5 personnes\n\nPrivatisée par la Pépite les mardis, mercredis et vendredis de 9h à 13h."],
        ];

        $configs = [];
        foreach ($priced as [$name, $np, $lucr, $equip, $desc]) {
            $configs[] = [
                'name' => $name,
                'active' => true, 'is_public' => true, 'on_request' => false,
                'bookable' => true, 'booking_optional' => false, 'members_only' => false,
                'description' => $desc,
                'price_mode' => 'fixed',
                'price_np_hourly' => $np[0], 'price_np_half_day' => $np[1], 'price_np_full_day' => $np[2],
                'price_hourly' => $lucr[0], 'price_half_day' => $lucr[1], 'price_full_day' => $lucr[2],
                'equipments' => $equip,
                'allowed_weekdays' => ['1', '2', '3', '4', '5'],
                'day_start_time' => '09:00', 'day_end_time' => '21:00',
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
            'bookable' => true, 'booking_optional' => true, 'members_only' => false,
            'description' => "Salon ouvert avec rideau (sans porte), coworking chill, réunion informelle. Tarif réduit — à privilégier par les coworkers Pépite si pas besoin d'équipements ni de confidentialité.\n\n24 m² · 8 personnes",
            'price_mode' => 'fixed',
            'price_np_hourly' => 15, 'price_np_half_day' => 40, 'price_np_full_day' => 60,
            'price_hourly' => 25, 'price_half_day' => 50, 'price_full_day' => 80,
            'equipments' => ['wifi'],
            'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            'day_start_time' => '09:00', 'day_end_time' => '21:00',
        ];

        // Salles « sur demande » (devis) : Big Room, Place du Village, Atelier.
        $onRequest = [
            'La Big Room' => [['screen', 'sound', 'wifi'], "Espace modulable : formation, conférence, événement. Tables/chaises, séparations, écran/beamer.\n\n100 m² · 30–50 personnes"],
            'La Place du Village' => [['wifi'], "Espace ouvert : café/repas, événements.\n\n100 m² · 20–40 personnes"],
            "L'Atelier" => [['wifi'], "Petite salle de réunion / travail, 3 grands bureaux, espace atelier/réparation.\n\n24 m² · 3–6 personnes"],
        ];
        foreach ($onRequest as $name => [$equip, $desc]) {
            $configs[] = [
                'name' => $name,
                'active' => true, 'is_public' => true, 'on_request' => true,
                'bookable' => true, 'booking_optional' => false, 'members_only' => false,
                'description' => $desc,
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
            'bookable' => true, 'booking_optional' => false, 'members_only' => true,
            'description' => "Espace repos / réunions informelles / appels téléphoniques individuels. Lits enfants, poufs. 1 bureau isolé.\n\n28 m² · 1–5 personnes",
            'price_mode' => 'fixed',
            'price_np_hourly' => 0, 'price_np_half_day' => 0, 'price_np_full_day' => 0,
            'price_hourly' => 0, 'price_half_day' => 0, 'price_full_day' => 0,
            'equipments' => ['wifi'],
            'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            // Enveloppe de ses fenêtres (9-17) : le mode « à l'heure » propose
            // 9h-16h ; les fenêtres par jour restreignent encore (mar/mer/ven = 13-17).
            'day_start_time' => '09:00', 'day_end_time' => '17:00',
            'windows' => $communityWindows,
        ];
        // La Garderie : NON réservable, horaires affichés.
        $configs[] = [
            'name' => 'La Garderie',
            'active' => true, 'is_public' => true, 'on_request' => false,
            'bookable' => false, 'booking_optional' => false, 'members_only' => true,
            'description' => "Salle dédiée aux enfants : accueil, espace jeux.\n\n60 m²",
            'price_mode' => 'fixed', 'price_full_day' => 0,
            'equipments' => [],
            'allowed_weekdays' => ['1', '2', '3', '4', '5'],
            'windows' => $communityWindows,
        ];

        // Autres espaces communautaires (gratuits, réservés aux membres, horaires
        // généraux ; pas de fenêtres spécifiques dans le document).
        $community = [
            ['La Secrète', "Espace de réunion pour 2 personnes ou pour téléphoner.\n\n5 m² · 1–2 personnes", ['wifi']],
            ["L'Accueil", "Espace discussion / appels, 2 tables hautes avec 4 chaises, coin lecture.", ['wifi']],
            ['La Coworking', "Places de travail individuelles.\n\n60 m² · 12 personnes", ['wifi']],
            ['Cabine acoustique', "Bulle insonorisée pour téléphoner.\n\n1 personne", []],
            ['La Cuisine', "Cuisine équipée, vaisselle.", []],
        ];
        foreach ($community as [$name, $desc, $equip]) {
            $configs[] = [
                'name' => $name,
                'active' => true, 'is_public' => true, 'on_request' => false,
                'bookable' => true, 'booking_optional' => false, 'members_only' => true,
                'description' => $desc,
                'price_mode' => 'fixed',
                'price_np_hourly' => 0, 'price_np_half_day' => 0, 'price_np_full_day' => 0,
                'price_hourly' => 0, 'price_half_day' => 0, 'price_full_day' => 0,
                'equipments' => $equip,
                'allowed_weekdays' => ['1', '2', '3', '4', '5'],
                'day_start_time' => '09:00', 'day_end_time' => '21:00',
            ];
        }

        return $configs;
    }
}
