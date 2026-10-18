<?php

namespace App\Models;

use App\Enums\ContactTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'entity_name',
        'email',
        'invoice_email',
        'phone',
        'street',
        'zip',
        'city',
    ];

    protected $casts = [
        'type' => ContactTypes::class,
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'tenant_id');
    }

    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /* ----- Déduplication (fusion/défusion non destructives) ------------------
     * Un contact « absorbé » pointe vers son survivant via merged_into_id.
     * On n'affiche/facture que les « racines » (merged_into_id = null) ; la
     * fusion = poser le lien, la défusion = le retirer. Zéro perte.
     */

    /** Le contact survivant dont celui-ci est un alias (null si c'est une racine). */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'merged_into_id');
    }

    /** Les contacts absorbés par celui-ci. */
    public function aliases(): HasMany
    {
        return $this->hasMany(Contact::class, 'merged_into_id');
    }

    /** Ce contact a-t-il été fusionné dans un autre ? */
    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    /** Entités « racines » uniquement (ce qu'on liste/facture). */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    public function display_name(): string
    {
        return $this->type === ContactTypes::ORGANIZATION
            ? $this->entity_name
            : "{$this->first_name} {$this->last_name}";
    }

    public function invoiceEmail(): string
    {
        return $this->invoice_email ?? $this->email;
    }

    public function bothEmailsUnique(): array
    {
        $emails = $this->invoice_email ? [$this->email, $this->invoice_email] : [$this->email];

        return array_unique($emails);
    }
}
