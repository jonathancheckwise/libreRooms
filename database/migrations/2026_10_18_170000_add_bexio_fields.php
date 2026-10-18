<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intégration bexio (compta = source de vérité) :
 * - contacts.bexio_contact_id : mapping entité ↔ contact bexio (clé de réconciliation).
 * - invoices.bexio_invoice_id : mapping facture locale ↔ facture bexio.
 * - invoices.payment_method : rail de paiement (stripe | qr), null = non défini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('bexio_contact_id')->nullable()->after('merged_into_id')->index();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('bexio_invoice_id')->nullable()->after('number')->index();
            $table->string('payment_method')->nullable()->after('bexio_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('bexio_contact_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['bexio_invoice_id', 'payment_method']);
        });
    }
};
