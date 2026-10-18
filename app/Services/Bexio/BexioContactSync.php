<?php

namespace App\Services\Bexio;

use App\Enums\ContactTypes;
use App\Models\Contact;

/**
 * Synchronise une entité La Pépite (Contact) vers un contact bexio.
 * Crée le contact dans bexio s'il n'existe pas encore (bexio_contact_id vide),
 * puis mémorise l'id bexio sur l'entité (clé de réconciliation).
 * On ne synchronise que les entités RACINES (les alias fusionnés passent par
 * leur survivant).
 */
class BexioContactSync
{
    public function __construct(private BexioClient $bexio) {}

    /** Payload bexio pour créer/mettre à jour un contact. */
    public function payload(Contact $c): array
    {
        $isOrg = $c->type === ContactTypes::ORGANIZATION;
        [$street, $houseNo] = $this->splitStreet($c->street);

        return array_filter([
            'contact_type_id' => $isOrg ? 1 : 2,              // 1 = entreprise, 2 = personne
            'name_1' => $isOrg ? $c->entity_name : $c->last_name,
            'name_2' => $isOrg ? null : $c->first_name,
            'street_name' => $street,
            'house_number' => $houseNo,
            'postcode' => $c->zip,
            'city' => $c->city,
            'country_id' => (int) config('services.bexio.country_id') ?: null,
            'mail' => $c->invoice_email ?: $c->email,
            'phone_fixed' => $c->phone,
            'language_id' => (int) config('services.bexio.language_id') ?: null,
            'user_id' => (int) config('services.bexio.user_id') ?: null,
            'owner_id' => (int) config('services.bexio.user_id') ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Garantit que l'entité a un contact bexio. Retourne l'id bexio.
     * $force recrée même si un id existe déjà (rare).
     */
    public function sync(Contact $c): int
    {
        if ($c->isMerged()) {
            // Les alias ne sont pas facturés directement : on synchronise la racine.
            $c = $c->mergedInto ?? $c;
        }

        if ($c->bexio_contact_id) {
            return (int) $c->bexio_contact_id;
        }

        // Anti-doublon : l'entité existe peut-être DÉJÀ dans bexio (créée à la
        // main par une admin). On la cherche (email, puis nom) et on s'y rattache
        // au lieu de créer un doublon.
        if ($existing = $this->findInBexio($c)) {
            $c->bexio_contact_id = $existing;
            $c->save();

            return $existing;
        }

        $created = $this->bexio->post('2.0/contact', $this->payload($c));
        $bexioId = (int) ($created['id'] ?? 0);
        if ($bexioId <= 0) {
            throw new \RuntimeException('bexio : création de contact sans id retourné.');
        }

        $c->bexio_contact_id = $bexioId;
        $c->save();

        return $bexioId;
    }

    /**
     * Cherche un contact bexio existant correspondant à l'entité : par e-mail
     * d'abord (fort), puis par nom exact (name_1). Retourne l'id bexio ou null.
     */
    public function findInBexio(Contact $c): ?int
    {
        $email = $c->invoice_email ?: $c->email;
        $name = $c->type === ContactTypes::ORGANIZATION ? $c->entity_name : $c->last_name;

        foreach ([
            $email ? [['field' => 'mail', 'value' => $email, 'criteria' => '=']] : null,
            $name ? [['field' => 'name_1', 'value' => $name, 'criteria' => '=']] : null,
        ] as $criteria) {
            if (! $criteria) {
                continue;
            }
            try {
                $hits = $this->bexio->post('2.0/contact/search', $criteria);
            } catch (\Throwable $e) {
                continue;
            }
            if (! empty($hits[0]['id'])) {
                return (int) $hits[0]['id'];
            }
        }

        return null;
    }

    /**
     * Sépare "Place de la Gare 10" → ["Place de la Gare", "10"].
     * Si pas de numéro final détecté, tout va dans street_name.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitStreet(?string $street): array
    {
        $street = trim((string) $street);
        if ($street === '') {
            return [null, null];
        }
        if (preg_match('/^(.*?)[\s,]+([0-9]+\s?[a-zA-Z]?)$/', $street, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$street, null];
    }
}
