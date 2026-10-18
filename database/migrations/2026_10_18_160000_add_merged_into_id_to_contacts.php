<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Déduplication NON destructive des contacts (entités facturées) : un contact
 * absorbé devient un « alias » d'un contact survivant via merged_into_id.
 * Rien n'est supprimé, aucune réservation n'est déplacée → fusion/défusion
 * totalement réversibles (il suffit de poser/retirer le lien).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('merged_into_id')
                ->nullable()
                ->after('id')
                ->constrained('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
