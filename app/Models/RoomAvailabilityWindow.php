<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fenêtre de disponibilité d'une salle pour un jour de semaine (La Pépite).
 * weekday : ISO 1=lundi … 7=dimanche.
 */
class RoomAvailabilityWindow extends Model
{
    protected $fillable = [
        'room_id',
        'weekday',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
