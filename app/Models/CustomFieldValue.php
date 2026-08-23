<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\CustomFieldTypes;

class CustomFieldValue extends Model
{
    /** @use HasFactory<\Database\Factories\CustomFieldValueFactory> */
    use HasFactory;

    protected $fillable = [
        'value',
    ];

    protected $casts = [
        'type' => CustomFieldTypes::class,
        'options' => 'array',
        'required' => 'boolean',
    ];

    public static function fromReservationAndField(
        Reservation $reservation,
        CustomField $customField,
        mixed $value
    ): self {
        return static::unguarded(function () use ($reservation, $customField, $value) {
            return self::create([
                'reservation_id' => $reservation->id,
                'key'            => $customField->key,
                'label'          => $customField->label,
                'type'           => $customField->type,
                'options'        => $customField->options,
                'required'       => $customField->required,
                'value'          => $value,
            ]);
        });
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return match ($this->type) {
                    CustomFieldTypes::CHECKBOX => json_decode($value, true),
                    default => $value,
                };
            },
            set: function ($value) {
                return match ($this->type) {
                    CustomFieldTypes::CHECKBOX => json_encode($value),
                    default => $value,
                };
            }
        );
    }

    public function labelValue(): array
    {
        switch ($this->type) {
            case CustomFieldTypes::TEXT:
            case CustomFieldTypes::TEXTAREA:
                return [$this->value];
            case CustomFieldTypes::RADIO:
            case CustomFieldTypes::SELECT:
                // Champ facultatif laissé vide : pas de libellé à afficher.
                return isset($this->options[$this->value]) ? [$this->options[$this->value]] : [];
            case CustomFieldTypes::CHECKBOX:
                // Aucune case cochée : la valeur est null, pas un tableau vide.
                return array_map(fn ($value) => $this->options[$value] ?? $value,
                                is_array($this->value) ? $this->value : []);
            default:
                return [];
        }
    }

    // Input:: Collection of CustomFieldValues, 1 CustomField
    // Output: Value of first CustomFieldValue that matches CustomField
    public static function getMatchingValue(Collection $cf_values, CustomField $field): mixed
    {
        // Attempt to find matching value
        foreach ($cf_values as $cf_value) {
            if ($cf_value->matches($field)) {
                return $cf_value->value;
            }
        }
        // Nothing found, return empty value corresponding to type
        return match ($field->type) {
            CustomFieldTypes::CHECKBOX => [],
            default => '',
        };
    }

    private function matches(CustomField $field): bool
    {
        return $this->key === $field->key && $this->options === $field->options && $this->type === $field->type;
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
