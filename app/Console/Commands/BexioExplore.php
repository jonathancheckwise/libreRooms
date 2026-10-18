<?php

namespace App\Console\Commands;

use App\Services\Bexio\BexioClient;
use Illuminate\Console\Command;

/**
 * Vérifie la connexion bexio (token) et liste les IDs propres au compte
 * (utilisateurs, comptes comptables, TVA, devises, langues, compte bancaire…).
 * Sert à remplir la config BEXIO_* avant de générer des factures.
 * LECTURE SEULE — ne crée rien dans bexio.
 */
class BexioExplore extends Command
{
    protected $signature = 'pepite:bexio-explore';

    protected $description = 'Teste la connexion bexio et liste les IDs du compte (lecture seule)';

    public function handle(BexioClient $bexio): int
    {
        if (! $bexio->configured()) {
            $this->error('BEXIO_TOKEN manquant dans .env. Crée un Personal Access Token sur developer.bexio.com/pat et ajoute BEXIO_TOKEN=... dans .env (puis php artisan config:clear).');

            return self::FAILURE;
        }

        // 1) Connexion (profil société) — confirme que le token marche.
        try {
            $company = $bexio->get('2.0/company_profile');
            $name = $company[0]['name'] ?? ($company['name'] ?? '?');
            $this->info('✓ Connexion bexio OK — société : '.$name);
        } catch (\Throwable $e) {
            $this->error('✗ Connexion bexio échouée : '.$e->getMessage());

            return self::FAILURE;
        }

        // 2) Ressources de config. Chaque bloc est isolé : un endpoint en erreur
        //    (droit/scope/version) n'empêche pas les autres de s'afficher.
        $this->dump($bexio, 'UTILISATEURS (→ BEXIO_USER_ID)', '3.0/users', ['id'], ['firstname', 'lastname', 'email']);
        $this->dump($bexio, 'COMPTES COMPTABLES / produits (→ BEXIO_ACCOUNT_ID, ex. 3xxx)', '2.0/accounts', ['id', 'account_no'], ['name']);
        $this->dump($bexio, 'TVA (→ BEXIO_TAX_ID)', '3.0/taxes', ['id'], ['name', 'value', 'code']);
        $this->dump($bexio, 'DEVISES (→ BEXIO_CURRENCY_ID : CHF)', '3.0/currencies', ['id'], ['name']);
        $this->dump($bexio, 'LANGUES (→ BEXIO_LANGUAGE_ID : français)', '2.0/language', ['id'], ['name', 'iso_639_1']);
        $this->dump($bexio, 'PAYS (→ BEXIO_COUNTRY_ID : Suisse)', '2.0/country', ['id'], ['name', 'name_short']);
        $this->dump($bexio, 'TYPES DE PAIEMENT', '2.0/payment_type', ['id'], ['name']);
        // Comptes bancaires (endpoint incertain selon version) — on tente plusieurs.
        foreach (['3.0/banking/accounts', '2.0/bank_account'] as $path) {
            if ($this->dump($bexio, 'COMPTES BANCAIRES (→ BEXIO_BANK_ACCOUNT_ID, QR-facture) ['.$path.']', $path, ['id'], ['name', 'iban'])) {
                break;
            }
        }

        $this->newLine();
        $this->info('Colle cette sortie à Claude : il remplira les BEXIO_* et construira les factures.');

        return self::SUCCESS;
    }

    /** @return bool  true si l'endpoint a répondu (pour arrêter les tentatives alternatives). */
    private function dump(BexioClient $bexio, string $title, string $path, array $idFields, array $labelFields): bool
    {
        $this->newLine();
        $this->line('<fg=yellow;options=bold>=== '.$title.' ===</>');
        try {
            $rows = $bexio->get($path, ['limit' => 100]);
        } catch (\Throwable $e) {
            $this->line('  (indisponible : '.\Illuminate\Support\Str::limit($e->getMessage(), 120).')');

            return false;
        }

        if (! is_array($rows) || empty($rows)) {
            $this->line('  (vide)');

            return true;
        }

        foreach (array_slice($rows, 0, 60) as $r) {
            if (! is_array($r)) {
                continue;
            }
            $id = implode(' ', array_map(fn ($f) => (string) ($r[$f] ?? ''), $idFields));
            $label = implode(' · ', array_filter(array_map(fn ($f) => (string) ($r[$f] ?? ''), $labelFields)));
            $this->line('  '.str_pad($id, 14).' | '.$label);
        }

        return true;
    }
}
