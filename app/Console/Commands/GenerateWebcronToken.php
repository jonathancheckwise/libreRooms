<?php
// app/Console/Commands/GenerateWebcronToken.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateWebcronToken extends Command
{
    protected $signature = 'webcron:token {--show : Afficher le token sans modifier le .env}';
    protected $description = 'Génère un token aléatoire pour sécuriser la route webcron et le sauvegarde dans le .env';

    public function handle(): int
    {
        $token = Str::random(40);

        if ($this->option('show')) {
            $this->line($token);
            return self::SUCCESS;
        }

        $this->setTokenInEnvironmentFile($token);

        $this->components->info("WEBCRON_TOKEN généré et enregistré dans .env");

        return self::SUCCESS;
    }

    protected function setTokenInEnvironmentFile(string $token): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->components->error('Fichier .env introuvable.');
            return;
        }

        $content = file_get_contents($envPath);

        if (preg_match('/^WEBCRON_TOKEN=.*/m', $content)) {
            $content = preg_replace('/^WEBCRON_TOKEN=.*/m', "WEBCRON_TOKEN={$token}", $content);
        } else {
            $content .= "\nWEBCRON_TOKEN={$token}\n";
        }

        file_put_contents($envPath, $content);
    }
}
