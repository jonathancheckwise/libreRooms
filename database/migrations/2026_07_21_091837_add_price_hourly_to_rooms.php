<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Prix horaire (La Pépite) : facturé prix × nombre d'heures réservées.
            // Propre à chaque salle (petite salle != grande salle).
            $table->integer('price_hourly')->nullable()->after('price_full_day');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('price_hourly');
        });
    }
};
