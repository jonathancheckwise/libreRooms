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
            $table->string('terms_version', 40)->nullable()->after('terms_accepted_at');
            // Adresse IP d'où vient l'acceptation (IPv6 compris).
            $table->string('terms_ip', 45)->nullable()->after('terms_version');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            // Version en vigueur, à incrémenter à chaque modification des CG.
            $table->string('terms_version', 40)->nullable();
            // Adresse publique des CG, consultable AVANT de cocher la case.
            $table->string('terms_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version', 'terms_ip']);
        });
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['terms_version', 'terms_url']);
        });
    }
};
