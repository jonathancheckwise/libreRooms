<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Utilisateur qui a effectué la réservation (La Pépite) : sert au
            // quota mensuel « 1 h offerte » par membre et à la facturation.
            $table->foreignId('booked_by_user_id')->nullable()->after('tenant_id')
                ->constrained('users')->nullOnDelete();
            // Minutes offertes consommées par cette réservation (heure gratuite membre).
            $table->integer('free_minutes_applied')->default(0)->after('reservant_is_member');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booked_by_user_id');
            $table->dropColumn('free_minutes_applied');
        });
    }
};
