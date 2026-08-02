<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fenêtres de disponibilité par jour de semaine (La Pépite) : pour les
        // espaces communautaires aux horaires variables (La Douce lun 9-17 /
        // mar 13-17…) et les salles à créneaux privatisés (La Focus).
        // RESTRICTION SEULE : si une salle a des fenêtres, elle n'est
        // réservable QUE dans ces fenêtres ; sans fenêtre, comportement natif.
        // weekday = ISO 1=lundi … 7=dimanche (comme allowed_weekdays).
        Schema::create('room_availability_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1..7
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->index(['room_id', 'weekday']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            // Salle non réservable par les utilisateurs (La Garderie) : reste
            // visible, mais pas de réservation possible.
            $table->boolean('bookable')->default(true)->after('on_request');
            // Réservation facultative mais conseillée (La Chill) : note d'info.
            $table->boolean('booking_optional')->default(false)->after('bookable');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['bookable', 'booking_optional']);
        });
        Schema::dropIfExists('room_availability_windows');
    }
};
