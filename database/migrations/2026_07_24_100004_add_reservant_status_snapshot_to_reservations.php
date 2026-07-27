<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instantané du statut du réservant au moment de la réservation
        // (La Pépite). On fige le tarif appliqué pour qu'un recalcul ultérieur
        // (confirmation / édition par le staff) ne change pas le prix.
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reservant_org_type')->nullable()->after('status');
            $table->boolean('reservant_is_member')->default(false)->after('reservant_org_type');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['reservant_org_type', 'reservant_is_member']);
        });
    }
};
