<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\Bexio\BexioInvoiceGenerator;
use Illuminate\Console\Command;

/**
 * Crée dans bexio quelques brouillons de facture ÉTIQUETÉS « SIMULATION
 * (à supprimer) » à partir de vraies réservations passées, pour visualiser le
 * rendu dans l'interface bexio. Ne marque RIEN comme facturé (la vraie
 * facturation reste intacte). --cleanup supprime toutes les simulations.
 */
class BexioSimulate extends Command
{
    protected $signature = 'pepite:bexio-simulate {--limit=3 : nombre d\'entités à simuler} {--cleanup : supprimer les brouillons de simulation}';

    protected $description = 'Crée (ou supprime) des brouillons de facture SIMULATION dans bexio pour visualiser le rendu';

    public function handle(BexioInvoiceGenerator $gen): int
    {
        if ($this->option('cleanup')) {
            $deleted = $gen->deleteSimulations();
            if (empty($deleted)) {
                $this->info('Aucun brouillon de simulation à supprimer.');

                return self::SUCCESS;
            }
            foreach ($deleted as $d) {
                $this->line('🗑  supprimé : '.($d['document_nr'] ?: '#'.$d['id']).' — '.$d['title']);
            }
            $this->info(count($deleted).' brouillon(s) de simulation supprimé(s).');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $tz = config('app.timezone', 'Europe/Zurich');

        // Vraies réservations déjà passées, payantes (on ignore la bascule et
        // billed_at : c'est une simulation en lecture seule côté facturation).
        $reservations = Reservation::query()
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::FINISHED])
            ->where('is_free', false)
            ->with(['tenant.mergedInto', 'room', 'events.options'])
            ->get()
            ->filter(fn (Reservation $r) => $r->finalPrice() > 0
                && $r->events->isNotEmpty()
                && $r->events->every(fn ($e) => $e->end->isPast()));

        if ($reservations->isEmpty()) {
            $this->warn('Aucune réservation passée payante trouvée : rien à simuler.');

            return self::SUCCESS;
        }

        // On prend quelques entités, et pour chacune le mois le plus récent où
        // elle a des réservations passées → un brouillon représentatif.
        $byEntity = $reservations
            ->groupBy(fn (Reservation $r) => optional($r->tenant)?->root()?->id)
            ->filter(fn ($g, $k) => (bool) $k)
            ->take($limit);

        $this->line('Création de brouillons de SIMULATION dans bexio…');
        $created = 0;
        $first = true;
        foreach ($byEntity as $group) {
            $entity = $group->first()->tenant->root();
            $byPeriod = $group->groupBy(fn (Reservation $r) => $r->events->min('start')->copy()->setTimezone($tz)->format('Y-m'));
            $periods = $byPeriod->keys()->sort()->values();
            $latest = $periods->last();

            // Toutes les entités : un brouillon « EN COURS » (mois le plus récent) avec la note.
            $created += $this->makeDraft($gen, $entity, $latest, $byPeriod->get($latest), 'en_cours');

            // Pour la 1re entité, on montre AUSSI l'état « À VALIDER » (mois précédent
            // s'il existe, sinon le même mois) pour voir les deux états côte à côte.
            if ($first) {
                $valPeriod = $periods->count() > 1 ? $periods->get($periods->count() - 2) : $latest;
                $created += $this->makeDraft($gen, $entity, $valPeriod, $byPeriod->get($valPeriod), 'a_valider');
                $first = false;
            }
        }

        $this->newLine();
        $this->info($created.' brouillon(s) de simulation créé(s) dans bexio.');
        $this->comment('→ Va les voir dans bexio (Ventes › Factures, filtre « Brouillons ») :');
        $this->comment('   ⏳ EN COURS  = mois en cours (avec note « NE PAS ENVOYER »)');
        $this->comment('   ✅ À VALIDER = mois figé, prêt à revoir/envoyer');
        $this->comment('→ Puis supprime tout avec : php artisan pepite:bexio-simulate --cleanup');

        return self::SUCCESS;
    }

    private function makeDraft(BexioInvoiceGenerator $gen, $entity, string $period, $slice, string $state): int
    {
        try {
            $res = $gen->simulateDraft($entity, $period, $slice, $state);
            $tag = $state === 'en_cours' ? '⏳ EN COURS ' : '✅ À VALIDER';
            $this->newLine();
            $this->line($tag.' <options=bold>'.$res['entity'].'</> — '.$res['period'].' — '.$res['count'].' résa(s) · '
                .number_format($res['total'], 2, '.', '\'').' CHF'
                .($res['special'] ? ' · DEMANDE SPÉCIALE' : '')
                .' → bexio '.($res['document_nr'] ?: '#'.$res['bexio_invoice_id']));

            return 1;
        } catch (\Throwable $e) {
            $this->error('Échec pour '.$entity->display_name().' : '.$e->getMessage());

            return 0;
        }
    }
}
