<?php

namespace App\Enums;

/**
 * Type d'organisme du réservant (La Pépite). Détermine la colonne de tarif
 * appliquée : les organismes sans but lucratif bénéficient d'un tarif réduit.
 */
enum OrgType: string
{
    case NON_PROFIT = 'non_profit';
    case FOR_PROFIT = 'for_profit';

    public function label(): string
    {
        return match ($this) {
            self::NON_PROFIT => __('Non-profit organization'),
            self::FOR_PROFIT => __('For-profit organization'),
        };
    }

    /** Préfixe des colonnes de prix correspondantes sur la salle. */
    public function priceColumn(string $mode): string
    {
        // Non-lucratif → price_np_*, lucratif → price_* (colonnes historiques).
        $prefix = $this === self::NON_PROFIT ? 'price_np_' : 'price_';

        return $prefix.$mode;
    }
}
