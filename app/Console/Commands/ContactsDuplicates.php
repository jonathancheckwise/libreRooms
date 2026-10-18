<?php

namespace App\Console\Commands;

use App\Enums\ContactTypes;
use App\Models\Contact;
use Illuminate\Console\Command;

/**
 * Détection (LECTURE SEULE) des contacts en doublon, pour préparer la
 * déduplication. N'écrit RIEN : liste les groupes suspects à faire valider
 * avant toute fusion. Clés : même e-mail (fort), même nom d'organisation
 * (fort), même nom de personne (à vérifier).
 */
class ContactsDuplicates extends Command
{
    protected $signature = 'pepite:contacts-duplicates';

    protected $description = 'Liste les contacts potentiellement en doublon (aucune modification)';

    public function handle(): int
    {
        $contacts = Contact::with(['users:id,email', 'reservations:id,tenant_id'])->get();

        $norm = function (?string $s): string {
            $s = mb_strtolower(trim((string) $s));
            // retire accents + espaces multiples pour comparer « Campus Démokratie » = « campus demokratie »
            $s = preg_replace('/\s+/', ' ', $s);
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);

            return $translit !== false ? $translit : $s;
        };

        // Groupes par e-mail (tous types), par nom d'organisation, par nom de personne.
        $byEmail = [];
        $byOrg = [];
        $byPerson = [];
        foreach ($contacts as $c) {
            if ($c->email) {
                $byEmail[$norm($c->email)][] = $c;
            }
            if ($c->type === ContactTypes::ORGANIZATION && $c->entity_name) {
                $byOrg[$norm($c->entity_name)][] = $c;
            }
            if ($c->type === ContactTypes::INDIVIDUAL && ($c->first_name || $c->last_name)) {
                $byPerson[$norm($c->first_name.' '.$c->last_name)][] = $c;
            }
        }

        $dupes = fn (array $groups) => array_filter($groups, fn ($g) => count($g) > 1);

        $this->section('MÊME E-MAIL (doublon quasi certain)', $dupes($byEmail));
        $this->section("MÊME NOM D'ORGANISATION", $dupes($byOrg));
        $this->section('MÊME NOM DE PERSONNE (à vérifier)', $dupes($byPerson));

        $this->newLine();
        $this->info('Lecture seule : aucune fusion effectuée. Valide les groupes à fusionner, puis on lance la fusion sûre.');

        return self::SUCCESS;
    }

    /** @param  array<string, array<int, Contact>>  $groups */
    private function section(string $title, array $groups): void
    {
        $this->newLine();
        $this->line('<fg=yellow;options=bold>=== '.$title.' : '.count($groups).' groupe(s) ===</>');
        if (empty($groups)) {
            $this->line('  (aucun)');

            return;
        }
        foreach ($groups as $key => $group) {
            $this->line('• <options=bold>'.$key.'</> ('.count($group).' contacts) :');
            foreach ($group as $c) {
                $owners = $c->users->pluck('email')->implode(', ') ?: '—';
                $this->line(sprintf(
                    '   #%-4d %-28s | %-28s | %s %s | résas:%d | rattaché à: %s',
                    $c->id,
                    mb_strimwidth($c->display_name(), 0, 28),
                    mb_strimwidth((string) $c->email, 0, 28),
                    str_pad((string) $c->zip, 5),
                    mb_strimwidth((string) $c->city, 0, 14),
                    $c->reservations->count(),
                    $owners,
                ));
            }
        }
    }
}
