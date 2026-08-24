<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'room_id',
        'tenant_id',
        'hash',
        'status',
        'booked_by_user_id',
        'reservant_org_type',
        'reservant_is_member',
        'free_minutes_applied',
        'title',
        'description',
        'full_price',
        'sum_discounts',
        'discounts',
        'special_discount',
        'donation',
        'custom_message',
        'confirmed_at',
        'confirmed_by',
        // Trace de l'acceptation des CG, figée à la création
        'terms_accepted_at',
        'terms_version',
        'cancelled_at',
    ];

    /**
     * Adresse du PDF des CG dans LA VERSION ACCEPTÉE par le réservataire.
     *
     * Le fichier courant est remplacé à chaque révision : y renvoyer ferait
     * pointer une vieille confirmation vers un texte que la personne n'a jamais
     * accepté. On vise donc le fichier daté, que l'art. 18.2 impose d'archiver.
     * conditions-generales.pdf → conditions-generales-2026-08-23.pdf
     */
    public function termsDocumentUrl(): ?string
    {
        $base = app(SystemSettings::class)->terms_url;
        if (! $base) {
            return null;
        }
        if (! $this->terms_version) {
            return $base;
        }

        return preg_match('/^(.*)\.pdf$/i', $base, $m)
            ? $m[1].'-'.$this->terms_version.'.pdf'
            : $base;
    }

    protected $casts = [
        'status' => ReservationStatus::class,
        'reservant_is_member' => 'boolean',
        'discounts' => 'array',
        'sum_discounts' => 'decimal:2',
        'special_discount' => 'decimal:2',
        'donation' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'tenant_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return array<int, int>
     */
    public function discountIds(): array
    {
        return array_map(fn (array $d) => $d[0], $this->discounts ?? []);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Journal des modifications, de la plus récente à la plus ancienne.
     *
     * Surtout pas « changes » : Eloquent réserve ce nom pour les attributs
     * modifiés lors du dernier enregistrement, et la relation ne serait jamais
     * atteinte.
     */
    public function modifications(): HasMany
    {
        return $this->hasMany(ReservationChange::class)->latest('id');
    }

    /** Modifications intervenues depuis la demande et avant la validation. */
    public function changesBeforeConfirmation()
    {
        $journal = $this->modifications;

        return $this->confirmed_at
            ? $journal->filter(fn ($ch) => $ch->created_at <= $this->confirmed_at)
            : $journal;
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReservationEvent::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function recommendedPrice(): float
    {
        return (float) ($this->full_price - $this->sum_discounts - $this->special_discount);
    }

    public function finalPrice(): float
    {
        if ($this->room->price_mode === \App\Enums\PriceModes::FREE) {
            return (float) $this->donation;
        }

        return (float) ($this->full_price - $this->sum_discounts - $this->special_discount + $this->donation);
    }

    public function isPaid(): bool
    {
        return $this->invoice && $this->invoice->paid_at !== null;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ReservationStatus::PENDING, ReservationStatus::CANCELLED])
                && ! $this->isPaid();
    }
}
