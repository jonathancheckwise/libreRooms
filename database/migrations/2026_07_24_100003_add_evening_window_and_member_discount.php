<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Nouveau créneau demi-journée « soir » (La Pépite).
            $table->time('half_day_evening_start')->default('18:00');
            $table->time('half_day_evening_end')->default('22:00');
            // Rabais accordé aux membres de la Pépite, en pourcentage.
            $table->integer('member_discount_percent')->default(10);
        });

        // Correction des créneaux existants selon le document validé par les
        // propriétaires (la coquille du tableau — 8-12 / 13-17 — est corrigée
        // en 8-13 / 13-18, sans recouvrement, + soir 18-22).
        DB::table('system_settings')->update([
            'half_day_morning_start' => '08:00',
            'half_day_morning_end' => '13:00',
            'half_day_afternoon_start' => '13:00',
            'half_day_afternoon_end' => '18:00',
            'half_day_evening_start' => '18:00',
            'half_day_evening_end' => '22:00',
            'full_day_start' => '08:00',
            'full_day_end' => '18:00',
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'half_day_evening_start',
                'half_day_evening_end',
                'member_discount_percent',
            ]);
        });
    }
};
