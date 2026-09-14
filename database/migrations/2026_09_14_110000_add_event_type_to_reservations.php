<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type d'activité de la réservation (réunion, atelier, coworking, événement…).
     * Descriptif, sans effet sur le tarif. Voir App\Enums\ReservationType.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('event_type')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });
    }
};
