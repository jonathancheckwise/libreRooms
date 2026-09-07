<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mise à jour des tarifs (feuille « Tarifs Pépite », version août 2026).
     * SBL = sans but lucratif (price_np_*) · BL = but lucratif (price_*).
     * On ne touche qu'aux colonnes de prix, par slug ; le rabais membre −10 %
     * reste calculé dynamiquement. La Focus est inchangée.
     */
    public function up(): void
    {
        $prices = [
            'la-petite-serieuse' => [
                'price_np_hourly' => 25, 'price_np_half_day' => 60, 'price_np_full_day' => 100,
                'price_hourly' => 35, 'price_half_day' => 120, 'price_full_day' => 240,
            ],
            'la-grande-serieuse' => [
                'price_np_hourly' => 35, 'price_np_half_day' => 80, 'price_np_full_day' => 140,
                'price_hourly' => 45, 'price_half_day' => 160, 'price_full_day' => 290,
            ],
            'la-dynamique' => [
                'price_np_hourly' => 45, 'price_np_half_day' => 100, 'price_np_full_day' => 180,
                'price_hourly' => 55, 'price_half_day' => 180, 'price_full_day' => 340,
            ],
            'la-chill' => [
                'price_np_hourly' => 15, 'price_np_half_day' => 20, 'price_np_full_day' => 60,
                'price_hourly' => 25, 'price_half_day' => 50, 'price_full_day' => 80,
            ],
        ];

        foreach ($prices as $slug => $vals) {
            DB::table('rooms')->where('slug', $slug)->update($vals);
        }
    }

    public function down(): void
    {
        // Mise à jour tarifaire de production : pas de rollback automatique.
    }
};
