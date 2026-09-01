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

        // La Pépite : le menu « Je suis » d'un invité pilote le tarif et le statut
        // membre. On fixe côté serveur (autorité) plutôt que de faire confiance au JS.
        if (! $this->user()) {
            $structure = $this->input('pep_structure');
            if ($structure === 'coworker') {
                // Coworkeur·se = membre ; sa grille (NP/lucratif) est le tarif choisi.
                $this->merge([
                    'org_type' => in_array($this->input('coworker_tarif'), ['non_profit', 'for_profit'], true)
                        ? $this->input('coworker_tarif')
                        : null,
                    'is_pepite_member' => true,
                ]);
            } elseif ($structure === 'np') {
                $this->merge(['org_type' => 'non_profit']);
            } elseif ($structure === 'fp') {
                $this->merge(['org_type' => 'for_profit']);
            }
        }
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
            $rules['pep_structure'] = ['required', 'in:np,fp,coworker'];
            // Un·e coworkeur·se doit préciser sa grille (le tarif alimente org_type).
            $rules['coworker_tarif'] = ['required_if:pep_structure,coworker', 'in:non_profit,for_profit'];
            $rules['org_type'] = ['required', 'in:non_profit,for_profit'];
            $rules['is_pepite_member'] = ['boolean'];
            $rules['password'] = ['required', 'confirmed', Password::min(12)];
        }

        // La Pépite : validation obligatoire des CGU du lieu avant de réserver
        // (pour tout le monde, invités, membres et responsables).
        $rules['accept_terms'] = ['accepted'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => __('You must accept the general terms and conditions to book.'),
            'pep_structure.required' => __('Please tell us who you are (this sets your rate).'),
            'coworker_tarif.required_if' => __('Please choose your billing rate (non-profit or for-profit).'),
            'org_type.required' => __('Please choose your billing rate (non-profit or for-profit).'),
        ];
    }
}
