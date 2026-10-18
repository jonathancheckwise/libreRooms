<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle de vie du brouillon mensuel :
 *   en_cours  → mois courant, se remplit, titre « ⏳ EN COURS · … » + note « ne pas envoyer »
 *   a_valider → figé le 1er, titre « ✅ À VALIDER · … », note retirée, à revoir/envoyer
 *   envoyee   → émise/envoyée au client : titre nettoyé « Réservations La Pépite — … »
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bexio_monthly_drafts', function (Blueprint $table) {
            $table->string('status')->default('en_cours')->after('period');
            $table->timestamp('cleared_at')->nullable()->after('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::table('bexio_monthly_drafts', function (Blueprint $table) {
            $table->dropColumn(['status', 'cleared_at']);
        });
    }
};
