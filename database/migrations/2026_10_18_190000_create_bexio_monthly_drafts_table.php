<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brouillon de facture bexio mensuel et GLOBAL par entité : une ligne par
 * (entité racine, mois). On y mémorise l'id du brouillon bexio pour pouvoir
 * y AJOUTER des positions au fil du mois (facturation incrémentale), au lieu
 * de tout créer d'un coup en fin de mois. Ainsi les admins voient dans bexio
 * la facture du mois se construire et ne la refont pas à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bexio_monthly_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                 // 'AAAA-MM'
            $table->unsignedBigInteger('bexio_invoice_id');
            $table->string('bexio_document_nr')->nullable();
            $table->boolean('has_special')->default(false); // note « catering » déjà ajoutée ?
            $table->timestamp('finalized_at')->nullable();  // validée dans bexio → on n'y touche plus
            $table->timestamps();

            $table->unique(['contact_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bexio_monthly_drafts');
    }
};
