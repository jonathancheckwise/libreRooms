<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crée (une fois) le secret partagé avec le panneau d'administration du site.
 * Le site le lit directement dans ce .env (même hébergement) ; sinon, le
 * recopier dans la config du site (voir docs/pepite-admin-sso.md).
 */
class PepiteSsoSecret extends Command
{
    protected $signature = 'pepite:sso-secret {--rotate : Remplacer un secret existant} {--show : Afficher le secret}';

    protected $description = 'Génère PEPITE_SSO_SECRET dans le .env (lien avec admin.pepite-lausanne.ch)';

    public function handle(): int
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->components->error('Fichier .env introuvable.');

            return self::FAILURE;
        }

        $content = file_get_contents($envPath);
        $has = preg_match('/^PEPITE_SSO_SECRET=(.+)$/m', $content, $m) && strlen(trim($m[1])) >= 32;

        if ($has && ! $this->option('rotate')) {
            $this->components->info('PEPITE_SSO_SECRET existe déjà (--rotate pour le remplacer).');
            if ($this->option('show')) {
                $this->line(trim($m[1]));
            }

            return self::SUCCESS;
        }

        $secret = Str::random(64);
        $content = preg_match('/^PEPITE_SSO_SECRET=.*$/m', $content)
            ? preg_replace('/^PEPITE_SSO_SECRET=.*$/m', 'PEPITE_SSO_SECRET='.$secret, $content)
            : rtrim($content)."\n\n# Lien avec le panneau admin du site (admin.pepite-lausanne.ch)\nPEPITE_SSO_SECRET={$secret}\n";
        file_put_contents($envPath, $content);

        $this->components->info('PEPITE_SSO_SECRET enregistré. Lancez ensuite : php artisan optimize:clear && php artisan optimize');
        if ($this->option('show')) {
            $this->line($secret);
        }

        return self::SUCCESS;
    }
}
