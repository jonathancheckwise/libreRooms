<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialRequest extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'name',
        'email',
        'phone',
        'organization',
        'desired_dates',
        'people',
        'purpose',
        'catering',
        'comment',
        'status',
    ];

    protected $casts = [
        'catering' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
