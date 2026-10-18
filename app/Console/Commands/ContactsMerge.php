<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

/**
 * Fusion NON destructive de contacts : les fiches « absorbées » deviennent des
 * alias du survivant (merged_into_id). Rien n'est supprimé, aucune réservation
 * déplacée → réversible avec pepite:contacts-unmerge.
 *
 * Ex. : php artisan pepite:contacts-merge 18 46
 *       (46 « Campus Démocratie - Fondation » devient un alias de 18)
 */
class ContactsMerge extends Command
{
    protected $signature = 'pepite:contacts-merge {survivor : ID du contact à GARDER} {absorbed* : ID(s) à fusionner dedans}';

    protected $description = 'Fusionne des contacts dans un survivant (réversible, non destructif)';

    public function handle(): int
    {
        $survivor = Contact::find($this->argument('survivor'));
        if (! $survivor) {
            $this->error('Contact survivant introuvable.');

            return self::FAILURE;
        }
        if ($survivor->isMerged()) {
            $this->error("Le survivant #{$survivor->id} est lui-même déjà fusionné (dans #{$survivor->merged_into_id}). Choisis la racine.");

            return self::FAILURE;
        }

        $this->line("Survivant : <options=bold>#{$survivor->id} ".$survivor->display_name()."</> | ".($survivor->email ?: '—')." | {$survivor->zip} {$survivor->city} | résas:".$survivor->reservations()->count());

        $toMerge = [];
        foreach ($this->argument('absorbed') as $id) {
            $c = Contact::find($id);
            if (! $c) {
                $this->warn("  #{$id} introuvable — ignoré.");

                continue;
            }
            if ($c->id === $survivor->id) {
                $this->warn('  On ne fusionne pas un contact avec lui-même — ignoré.');

                continue;
            }
            if ($c->aliases()->exists()) {
                $this->warn("  #{$c->id} a déjà des alias — défusionne-les d'abord. Ignoré.");

                continue;
            }
            $this->line("  → absorbé : #{$c->id} ".$c->display_name()." | ".($c->email ?: '—')." | {$c->zip} {$c->city} | résas:".$c->reservations()->count().($c->isMerged() ? " (déjà fusionné dans #{$c->merged_into_id})" : ''));
            $toMerge[] = $c;
        }

        if (empty($toMerge)) {
            $this->error('Rien à fusionner.');

            return self::FAILURE;
        }

        if (! $this->confirm('Confirmer la fusion ?', true)) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        foreach ($toMerge as $c) {
            $c->merged_into_id = $survivor->id;
            $c->save();
        }

        $this->info(count($toMerge).' contact(s) fusionné(s) dans #'.$survivor->id.'. Réversible via : php artisan pepite:contacts-unmerge '.collect($toMerge)->pluck('id')->implode(' '));

        return self::SUCCESS;
    }
}
