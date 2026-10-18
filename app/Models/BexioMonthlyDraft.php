<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Brouillon de facture bexio mensuel et global d'une entité (contact racine)
 * pour un mois donné. Sert de « pointeur » vers le brouillon bexio afin d'y
 * ajouter des positions au fur et à mesure que les réservations du mois passent.
 */
class BexioMonthlyDraft extends Model
{
    protected $fillable = [
        'contact_id',
        'period',
        'status',
        'bexio_invoice_id',
        'bexio_document_nr',
        'has_special',
        'finalized_at',
        'cleared_at',
    ];

    protected $casts = [
        'has_special' => 'boolean',
        'finalized_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
