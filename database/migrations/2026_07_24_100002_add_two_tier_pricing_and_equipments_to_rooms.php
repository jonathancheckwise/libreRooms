<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Tarification à deux colonnes (La Pépite).
            // Les colonnes existantes price_hourly / price_half_day /
            // price_full_day = tarif « organisme à but lucratif ».
            // Ci-dessous le tarif « organisme sans but lucratif ».
            $table->integer('price_np_hourly')->nullable()->after('price_hourly');
            $table->integer('price_np_half_day')->nullable()->after('price_half_day');
            $table->integer('price_np_full_day')->nullable()->after('price_full_day');

            // Salle « sur demande » : pas de prix affiché, réservation via le
            // formulaire de demande spéciale / devis (Big Room, Place du
            // Village, Atelier…).
            $table->boolean('on_request')->default(false)->after('is_public');

            // Équipements de la salle (wifi, visio, pmr, sonorisation,
            // ecran_beamer, flipchart…), stockés en JSON.
            $table->json('equipments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'price_np_hourly',
                'price_np_half_day',
                'price_np_full_day',
                'on_request',
                'equipments',
            ]);
        });
    }
};
