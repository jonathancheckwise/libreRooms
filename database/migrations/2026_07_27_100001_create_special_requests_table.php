<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demandes spéciales (La Pépite) : salles « sur demande » (Big Room,
        // Place du Village, Atelier…), catering, créneaux hors horaires /
        // week-end. Traitées sur devis par l'équipe, hors du flux de
        // réservation classique.
        Schema::create('special_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->text('desired_dates')->nullable();
            $table->integer('people')->nullable();
            $table->text('purpose')->nullable();
            $table->boolean('catering')->default(false);
            $table->text('comment')->nullable();
            $table->string('status')->default('new'); // new | handled | closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_requests');
    }
};
