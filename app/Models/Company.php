<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Company extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        // Génère un slug unique à partir du nom.
        static::saving(function (Company $company) {
            if (empty($company->slug) || $company->isDirty('name')) {
                $base = Str::slug($company->name) ?: 'entreprise';
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $company->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $company->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Membres de cette entreprise.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Salles auxquelles cette entreprise a accès.
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }
}
