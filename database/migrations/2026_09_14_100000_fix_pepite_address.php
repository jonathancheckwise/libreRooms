<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correction d'adresse (La Pépite) : c'est « Pl. de la Gare 10, 1003
     * Lausanne » et non « Avenue de la Gare ». On corrige les salles et le
     * contact du propriétaire (utilisé dans le pied des emails).
     */
    public function up(): void
    {
        DB::table('rooms')
            ->where('street', 'like', '%de la Gare 10%')
            ->update(['street' => 'Pl. de la Gare 10']);

        DB::table('contacts')
            ->where('street', 'like', '%de la Gare 10%')
            ->update(['street' => 'Pl. de la Gare 10']);

        // Contact du/des propriétaire(s) = La Pépite (pied des emails) : on pose
        // l'adresse complète, quelle que soit sa valeur actuelle.
        $ownerContactIds = DB::table('owners')->pluck('contact_id')->filter()->all();
        if (! empty($ownerContactIds)) {
            DB::table('contacts')->whereIn('id', $ownerContactIds)->update([
                'street' => 'Pl. de la Gare 10',
                'zip' => '1003',
                'city' => 'Lausanne',
            ]);
        }
    }

    public function down(): void
    {
        // Correction de données : pas de rollback.
    }
};
