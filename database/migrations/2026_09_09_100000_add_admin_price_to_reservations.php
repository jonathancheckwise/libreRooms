<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Montant personnalisé fixé par un responsable (La Pépite) : override du
     * prix calculé, notamment pour les salles « sur demande » (prix de base 0)
     * ou toute résa poussée à la main. Null = tarif calculé normal.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('admin_price', 10, 2)->nullable()->after('is_free');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('admin_price');
        });
    }
};
