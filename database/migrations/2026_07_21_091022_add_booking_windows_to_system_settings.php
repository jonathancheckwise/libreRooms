<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plages horaires globales (La Pépite) : définition commune à toutes
        // les salles de ce qu'est une réservation à l'heure / demi-journée /
        // journée. Les PRIX, eux, restent propres à chaque salle.
        Schema::table('system_settings', function (Blueprint $table) {
            $table->integer('hourly_max_hours')->default(3);
            $table->time('half_day_morning_start')->default('06:00');
            $table->time('half_day_morning_end')->default('12:00');
            $table->time('half_day_afternoon_start')->default('12:00');
            $table->time('half_day_afternoon_end')->default('17:00');
            $table->time('full_day_start')->default('07:00');
            $table->time('full_day_end')->default('17:00');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hourly_max_hours',
                'half_day_morning_start', 'half_day_morning_end',
                'half_day_afternoon_start', 'half_day_afternoon_end',
                'full_day_start', 'full_day_end',
            ]);
        });
    }
};
