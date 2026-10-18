<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

/**
 * Défusion : retire le lien merged_into_id → la/les fiche(s) redevient
 * indépendante(s), avec tout son historique intact (rien n'avait été déplacé).
 *
 * Ex. : php artisan pepite:contacts-unmerge 46
 */
class ContactsUnmerge extends Command
{
    protected $signature = 'pepite:contacts-unmerge {absorbed* : ID(s) de contact à défusionner}';

    protected $description = 'Défusionne des contacts (retire le lien de fusion, réversible)';

    public function handle(): int
    {
        $restored = 0;
        foreach ($this->argument('absorbed') as $id) {
            $c = Contact::find($id);
            if (! $c) {
                $this->warn("  #{$id} introuvable — ignoré.");

                continue;
            }
            if (! $c->isMerged()) {
                $this->warn("  #{$c->id} ".$c->display_name()." n'est pas fusionné — ignoré.");

                continue;
            }
            $from = $c->merged_into_id;
            $c->merged_into_id = null;
            $c->save();
            $this->line("  ✓ #{$c->id} ".$c->display_name()." défusionné (était dans #{$from}).");
            $restored++;
        }

        $this->info($restored.' contact(s) défusionné(s).');

        return self::SUCCESS;
    }
}
