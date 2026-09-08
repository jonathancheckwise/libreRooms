<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réservation interne gratuite (La Pépite) : un responsable peut pousser une
     * réservation sans facturation (usage équipe). finalPrice() renvoie alors 0.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('donation');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
