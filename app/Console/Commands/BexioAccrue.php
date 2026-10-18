<?php

namespace App\Console\Commands;

use App\Services\Bexio\BexioInvoiceGenerator;
use Illuminate\Console\Command;

/**
 * Passe de facturation INCRÉMENTALE bexio. À chaque exécution, ajoute aux
 * brouillons mensuels (1 par entité et par mois) les réservations devenues
 * facturables (passées, non gratuites, non déjà facturées) depuis le dernier
 * passage. Planifiée régulièrement → la facture du mois se construit au fil
 * de l'eau dans bexio. Par défaut écrit ; --dry-run pour un simple aperçu.
 */
class BexioAccrue extends Command
{
    protected $signature = 'pepite:bexio-accrue {--dry-run : aperçu sans rien écrire dans bexio}';

    protected $description = 'Ajoute au fil de l\'eau les réservations passées aux brouillons de facture mensuels bexio';

    public function handle(BexioInvoiceGenerator $gen): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->line(($dry ? '[DRY-RUN] ' : '').'Accumulation bexio (bascule : '.config('services.bexio.billing_start').')');

        try {
            $results = $gen->accrue($dry);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (empty($results)) {
            $this->info('Aucune nouvelle réservation facturable.');

            return self::SUCCESS;
        }

        $grand = 0.0;
        foreach ($results as $r) {
            $this->newLine();
            $tag = isset($r['dry_run']) ? ' (aperçu)'
                : (isset($r['skipped']) ? ' · <fg=red>IGNORÉ : '.$r['skipped'].'</>'
                    : ' · bexio '.($r['document_nr'] ?: '#'.($r['bexio_invoice_id'] ?? '?')));
            $this->line('<options=bold>'.$r['entity'].'</> — '.$r['period'].' — '.$r['count'].' résa(s) · '
                .number_format($r['total'], 2, '.', '\'').' CHF'
                .($r['special'] ? ' · <fg=yellow>DEMANDE SPÉCIALE</>' : '').$tag);
            foreach ($r['lines'] as $line) {
                $this->line('   • '.$line);
            }
            $grand += $r['total'];
        }

        $this->newLine();
        $this->info(count($results).' brouillon(s) touché(s), total '.number_format($grand, 2, '.', '\'').' CHF.'
            .($dry ? ' — DRY-RUN : rien écrit.' : ''));

        return self::SUCCESS;
    }
}
