<?php

namespace App\Http\Requests;

use App\Validation\ContactRules;
use App\Validation\CustomFieldValuesRules;
use App\Validation\ReservationEventsValidator;
use App\Validation\ReservationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        parent::failedValidation($validator);
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            app(ReservationEventsValidator::class)->validate(
                validator: $validator,
                room: $this->route('room'),
                user: $this->user(),
                events: $this->input('events')
            );
        });
    }

    protected function prepareForValidation(): void
    {
        ContactRules::prepare($this);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $room = $this->route('room'); // route model binding

        $rules = array_merge(
            ContactRules::rules($this),
            CustomFieldValuesRules::createRules($room),
            ReservationRules::createRules($room, $this->input('contact_type')),
        );
        if ($this->user()?->can('manageReservations', $room)) {
            $rules = array_merge(
                $rules,
                ReservationRules::adminRules(),
            );
        }

        // La Pépite : un invité doit créer un compte pour finaliser (statut +
        // mot de passe). L'unicité de l'email est vérifiée dans le contrôleur.
        if (! $this->user()) {
            $rules['org_type'] = ['required', 'in:non_profit,for_profit'];
            $rules['is_pepite_member'] = ['boolean'];
            $rules['password'] = ['required', 'confirmed', Password::min(12)];
        }

        // La Pépite : validation obligatoire des CGU du lieu avant de réserver
        // (les responsables/admins qui saisissent pour un tiers en sont exemptés).
        if (! $this->user()?->can('manageReservations', $room)) {
            $rules['accept_terms'] = ['accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => __('You must accept the venue\'s terms of use to book.'),
        ];
    }
}
