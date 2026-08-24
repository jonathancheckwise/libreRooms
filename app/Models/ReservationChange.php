<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du journal : un champ modifié, son avant, son après, par qui.
 */
class ReservationChange extends Model
{
    protected $fillable = [
        'reservation_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Libellé lisible du champ concerné. */
    public function fieldLabel(): string
    {
        return match ($this->field) {
            'dates' => __('Dates and times'),
            'title' => __('Title'),
            'description' => __('Description'),
            'price' => __('Amount'),
            default => $this->field,
        };
    }
}
