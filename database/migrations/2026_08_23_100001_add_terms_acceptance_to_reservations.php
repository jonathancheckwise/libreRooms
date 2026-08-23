<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivage de l'acceptation des conditions générales (La Pépite).
 *
 * L'acceptation est portée par LA RÉSERVATION, pas par le compte : les CG
 * peuvent changer entre deux réservations, et c'est la version en vigueur au
 * moment de la réservation qui engage. Rattachée au compte, une mise à jour des
 * CG s'appliquerait rétroactivement à des réservations déjà passées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Horodatage de l'acceptation (UTC, comme le reste des dates).
            $table->timestamp('terms_accepted_at')->nullable()->after('confirmed_by');
            // Version des CG acceptée, telle qu'affichée au réservant.
            // L'art. 1.6 énumère ce qui est enregistré : date et heure, identité et
            // email du réservataire, référence de la réservation, version des CG.
            // L'identité, l'email et la référence sont déjà portés par la
            // réservation et son contact. Pas d'adresse IP : elle n'est pas
            // annoncée dans les CG, et on n'enregistre pas plus que ce qu'on dit.
            $table->string('terms_version', 40)->nullable()->after('terms_accepted_at');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            // Version en vigueur, à incrémenter à chaque modification des CG.
            $table->string('terms_version', 40)->nullable();
            // Adresse publique des CG, consultable AVANT de cocher la case.
            $table->string('terms_url')->nullable();
        });

        // Valeurs de La Pépite, pour éviter une saisie manuelle après déploiement.
        // Sans terms_url, la case s'affiche sans lien et le blocage « ouvrir avant
        // de cocher » ne s'applique pas : autant les poser tout de suite.
        \Illuminate\Support\Facades\DB::table('system_settings')->update([
            'terms_version' => '2026-08-23',
            'terms_url' => 'https://pepite-lausanne.ch/conditions-generales.pdf',
        ]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['terms_version', 'terms_url']);
        });
    }
};
