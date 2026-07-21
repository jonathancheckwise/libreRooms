<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    protected $fillable = [
        'name', 'slug', 'street', 'postal_code', 'city', 'country', 'latitude', 'longitude',
    ];

    protected static function booted(): void
    {
        static::saving(function (Location $location) {
            if (empty($location->slug) || $location->isDirty('name')) {
                $base = Str::slug($location->name) ?: 'lieu';
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $location->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $location->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
