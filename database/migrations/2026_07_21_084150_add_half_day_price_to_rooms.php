<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Tarif demi-journée (La Pépite) : palier intermédiaire optionnel
            // entre le tarif court et le tarif journée.
            $table->integer('price_half_day')->nullable()->after('price_full_day');
            $table->integer('max_hours_half_day')->nullable()->after('max_hours_short');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['price_half_day', 'max_hours_half_day']);
        });
    }
};
