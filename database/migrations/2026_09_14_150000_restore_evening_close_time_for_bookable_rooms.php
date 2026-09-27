<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Incident 14.09.2026 — « Impossible de réserver ».
 *
 * En prod, 13 salles sur 15 avaient dérivé à day_end_time = 17:00 (une seule,
 * La Coworking, était restée à 21:00), alors que ces salles sont réservables
 * jusqu'à 21:00. Conséquence : le créneau « Demi-journée soir (17:00–21:00) »
 * proposé par le formulaire tombait toujours en « Non réservable » et les
 * réservations en soirée étaient impossibles.
 *
 * On restaure 21:00 UNIQUEMENT sur les salles concernées (celles données à
 * 21:00 par la config de référence, ConfigureRooms) ET seulement si elles sont
 * encore à 17:00 — pour ne pas écraser un réglage manuel volontaire.
 *
 * NE SONT PAS touchées : La Douce (17:00 voulu), La Garderie (non réservable),
 * les salles « sur demande » (Big Room, Place du Village, Atelier), ni aucun
 * autre réglage (visibilité, membres, prix…).
 *
 * cf. docs/incident-2026-09-14-reservation-impossible.md
 */
return new class extends Migration
{
    /** Salles réservables « à l'heure / demi-journées » qui ferment à 21:00. */
    private array $slugs = [
        'la-dynamique',
        'la-grande-serieuse',
        'la-petite-serieuse',
        'la-focus',
        'la-chill',
        'la-secrete',
        'laccueil',
        'cabine-acoustique',
        'la-cuisine',
    ];

    public function up(): void
    {
        $target = collect($this->slugs)
            ->filter(fn ($slug) => DB::table('rooms')
                ->where('slug', $slug)
                ->where('day_end_time', 'like', '17:00%')
                ->exists());

        $count = DB::table('rooms')
            ->whereIn('slug', $target->all())
            ->update(['day_end_time' => '21:00:00']);

        Log::info('[incident-1409] Restauration heure de fermeture à 21:00', [
            'salles_ciblees' => $this->slugs,
            'salles_modifiees' => $target->values()->all(),
            'lignes' => $count,
        ]);
    }

    public function down(): void
    {
        // Correction ponctuelle d'un incident : pas de rollback automatique
        // (on ne veut pas re-casser les réservations du soir).
    }
};
