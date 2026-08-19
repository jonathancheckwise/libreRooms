<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salle « réservée aux membres » (La Pépite) : non réservable par les
        // externes non connectés. Un visiteur non connecté voit la salle mais
        // avec un cadenas + un CTA « Devenir membre » (inscription). Concerne
        // les espaces communautaires (La Douce, La Secrète, La Coworking…).
        // Distinct de `bookable` (La Garderie = non réservable par TOUS).
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('members_only')->default(false)->after('booking_optional');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('members_only');
        });
    }
};
