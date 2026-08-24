<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des modifications apportées à une réservation par l'équipe.
 *
 * On consigne ce qui a effectivement changé, pas ce que la personne déclare
 * avoir changé : un commentaire libre dépend de la bonne volonté de celui qui
 * modifie, un relevé de champs non.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            // Auteur de la modification. Nullable : un compte supprimé ne doit pas
            // emporter la trace de ce qu'il a fait.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Clé stable du champ concerné : dates, title, description, price.
            $table->string('field', 40);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_changes');
    }
};
