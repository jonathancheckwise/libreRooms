<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correctif de production (La Pépite).
     *
     * Ces quatre salles de réunion étaient repassées, en base de prod, en
     * « sur demande » (devis) — elles n'affichaient donc que « Demande
     * spéciale » au lieu de la réservation classique. Le script
     * pepite:configure-rooms les définit pourtant en on_request=false ; la
     * prod avait dérivé. On rétablit la réservation en ligne sans toucher aux
     * jours/heures/prix/fenêtres réglés par ailleurs.
     *
     * Big Room, Place du Village et L'Atelier restent volontairement en devis.
     */
    public function up(): void
    {
        DB::table('rooms')
            ->whereIn('slug', [
                'la-petite-serieuse',
                'la-grande-serieuse',
                'la-dynamique',
                'la-focus',
            ])
            ->update([
                'on_request' => false,
                'bookable' => true,
            ]);
    }

    public function down(): void
    {
        // Correctif de données de production : pas de rollback automatique.
    }
};
