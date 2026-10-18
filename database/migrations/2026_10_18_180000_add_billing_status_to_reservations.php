<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statut de facturation par réservation, pour la facturation mensuelle bexio :
 * - billed_at : horodatage où la résa a été incluse dans une facture (null = pas
 *   encore facturée). Empêche toute double facturation.
 * - bexio_invoice_id : la facture bexio qui l'a facturée (réconciliation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('billed_at')->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('bexio_invoice_id')->nullable()->after('billed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['billed_at', 'bexio_invoice_id']);
        });
    }
};
