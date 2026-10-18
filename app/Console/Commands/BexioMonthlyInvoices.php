<?php

namespace App\Console\Commands;

use App\Services\Bexio\BexioInvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Génère les factures mensuelles globales bexio (1 brouillon par entité).
 * Par défaut : mois PRÉCÉDENT, en DRY-RUN (rien écrit). Ajouter --write pour
 * créer réellement les brouillons dans bexio.
 */
class BexioMonthlyInvoices extends Command
{
    protected $signature = 'pepite:bexio-monthly-invoices {--month= : AAAA-MM (défaut : mois précédent)} {--write : créer réellement (sinon dry-run)}';

    protected $description = 'Génère les factures mensuelles bexio (brouillons) à partir des réservations';

    public function handle(BexioInvoiceGenerator $gen): int
    {
        $month = $this->option('month') ?: Carbon::now(config('app.timezone'))->subMonth()->format('Y-m');
        $write = (bool) $this->option('write');

        $this->line(($write ? '' : '[DRY-RUN] ').'Facturation du mois '.$month.' (bascule : '.config('services.bexio.billing_start').')');

        try {
            $results = $gen->generateForMonth($month, ! $write);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (empty($results)) {
            $this->info('Aucune réservation facturable pour ce mois.');

            return self::SUCCESS;
        }

        $grand = 0.0;
        foreach ($results as $r) {
            $this->newLine();
            $this->line('<options=bold>'.$r['entity'].'</> — '.$r['count'].' résa(s) · '.number_format($r['total'], 2, '.', '\'').' CHF'
                .($r['special'] ? ' · <fg=yellow>DEMANDE SPÉCIALE (à valider manuellement)</>' : '')
                .(isset($r['bexio_invoice_id']) ? ' · bexio '.($r['document_nr'] ?: '#'.$r['bexio_invoice_id']) : ''));
            foreach ($r['lines'] as $line) {
                $this->line('   • '.$line);
            }
            $grand += $r['total'];
        }

        $this->newLine();
        $this->info(count($results).' facture(s), total '.number_format($grand, 2, '.', '\'').' CHF.'
            .($write ? '' : ' — DRY-RUN : rien créé. Relance avec --write pour créer les brouillons.'));

        return self::SUCCESS;
    }
}
