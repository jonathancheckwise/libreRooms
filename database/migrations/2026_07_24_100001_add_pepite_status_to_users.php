<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Statut du réservant (La Pépite), déclaré une fois au compte et
        // appliqué à toutes ses réservations :
        //  - is_pepite_member : membre de la Pépite → rabais de 10 %
        //  - org_type : 'non_profit' (sans but lucratif) ou 'for_profit'
        //    (à but lucratif) → détermine la colonne de tarif appliquée.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_pepite_member')->default(false)->after('is_global_admin');
            $table->string('org_type')->nullable()->after('is_pepite_member');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_pepite_member', 'org_type']);
        });
    }
};
