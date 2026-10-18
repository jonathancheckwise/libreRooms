<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\Bexio\BexioContactSync;
use Illuminate\Console\Command;

/**
 * Synchronise les entités (Contacts racines) vers bexio.
 * --dry-run : affiche le payload bexio SANS rien écrire (vérification).
 * --id=     : une seule entité ; sinon toutes les racines sans bexio_contact_id.
 */
class BexioSyncContacts extends Command
{
    protected $signature = 'pepite:bexio-sync-contacts {--id= : ID d\'une entité précise} {--dry-run : afficher le payload sans écrire} {--limit=50 : nb max en une fois}';

    protected $description = 'Crée/relie les contacts bexio pour les entités La Pépite';

    public function handle(BexioContactSync $sync): int
    {
        $query = Contact::roots();
        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        } else {
            $query->whereNull('bexio_contact_id');
        }
        $contacts = $query->limit((int) $this->option('limit'))->get();

        if ($contacts->isEmpty()) {
            $this->info('Aucune entité à synchroniser.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $this->line(($dry ? '[DRY-RUN] ' : '').$contacts->count().' entité(s) :');

        if ($dry) {
            foreach ($contacts as $c) {
                $this->newLine();
                $this->line('<options=bold>#'.$c->id.' '.$c->display_name().'</>'.($c->bexio_contact_id ? ' (déjà bexio #'.$c->bexio_contact_id.')' : ''));
                foreach ($sync->payload($c) as $k => $v) {
                    $this->line('   '.str_pad($k, 16).' = '.$v);
                }
            }
            $this->newLine();
            $this->info('Dry-run : rien écrit dans bexio. Relance sans --dry-run pour créer réellement.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Créer/relier ces '.$contacts->count().' contact(s) dans bexio ?', true)) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($contacts as $c) {
            try {
                $id = $sync->sync($c);
                $this->line('  ✓ #'.$c->id.' '.$c->display_name().' → bexio #'.$id);
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  ✗ #'.$c->id.' '.$c->display_name().' : '.$e->getMessage());
                $fail++;
            }
        }

        $this->info($ok.' synchronisé(s), '.$fail.' échec(s).');

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
