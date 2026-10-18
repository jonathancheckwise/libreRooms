<?php

namespace App\Services\Bexio;

use App\Enums\ReservationStatus;
use App\Models\BexioMonthlyDraft;
use App\Models\Contact;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Facturation bexio INCRÉMENTALE.
 *
 * Au lieu de créer une facture d'un coup en fin de mois, on tient à jour un
 * brouillon GLOBAL par entité (contact racine) et par mois : chaque fois qu'une
 * réservation est PASSÉE (tous ses créneaux terminés), on ajoute sa ligne au
 * brouillon du mois de l'entité (créé au besoin). Les admins voient donc dans
 * bexio la facture du mois se construire et ne la refont pas à la main.
 *
 * Règles :
 *  - on ne facture qu'une réservation déjà passée (avant, elle peut être
 *    modifiée / déplacée / annulée) et dont le prix est > 0 (exclut l'interne) ;
 *  - seulement les réservations à partir de la date de bascule et pas encore
 *    facturées (billed_at null) → aucune double facturation ;
 *  - salle on_request (demande spéciale) → une note « compléter catering » est
 *    ajoutée une fois au brouillon (positions catering saisies à la main) ;
 *  - TVA exonérée (tax_id configuré), compte « Location de salles ».
 */
class BexioInvoiceGenerator
{
    public function __construct(private BexioClient $bexio, private BexioContactSync $contacts) {}

    /**
     * Passe incrémentale : ajoute aux brouillons mensuels toutes les
     * réservations devenues facturables depuis le dernier passage.
     *
     * @return array<int, array<string,mixed>>  un résumé par (entité, mois) touché
     */
    public function accrue(bool $dryRun = false): array
    {
        $tz = config('app.timezone', 'Europe/Zurich');
        $cutover = Carbon::parse(config('services.bexio.billing_start'), $tz)->startOfDay();
        $taxId = (int) config('services.bexio.tax_id');
        $accountId = (int) config('services.bexio.account_id');

        $reservations = Reservation::query()
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::FINISHED])
            ->where('is_free', false)
            ->whereNull('billed_at')
            ->whereHas('events', fn ($q) => $q->where('start', '>=', $cutover->copy()->utc()))
            ->with(['tenant.mergedInto', 'room', 'events.options'])
            ->get()
            ->filter(fn (Reservation $r) => $r->finalPrice() > 0
                && $r->events->isNotEmpty()
                && $r->events->every(fn ($e) => $e->end->isPast()));

        // Groupe par (entité racine, mois de la réservation).
        $groups = $reservations->groupBy(
            fn (Reservation $r) => optional($r->tenant)?->root()?->id.'|'.$this->periodOf($r, $tz)
        );

        $results = [];
        foreach ($groups as $key => $group) {
            [$rootId, $period] = explode('|', (string) $key);
            if (! $rootId) {
                continue; // réservation sans entité : rien à facturer
            }
            $results[] = $this->accrueGroup($group->first()->tenant->root(), $period, $group, $taxId, $accountId, $tz, $dryRun);
        }

        return $results;
    }

    /** Ajoute (ou simule) les lignes d'un groupe (entité + mois) à son brouillon. */
    private function accrueGroup(Contact $entity, string $period, Collection $group, int $taxId, int $accountId, string $tz, bool $dryRun): array
    {
        $sorted = $group->sortBy(fn (Reservation $r) => optional($r->events->min('start')))->values();
        $total = round($sorted->sum(fn (Reservation $r) => round($r->finalPrice(), 2)), 2);
        $hasSpecial = $sorted->contains(fn (Reservation $r) => (bool) optional($r->room)->on_request);

        $summary = [
            'entity' => $entity->display_name(),
            'entity_id' => $entity->id,
            'period' => $period,
            'count' => $sorted->count(),
            'total' => $total,
            'special' => $hasSpecial,
            'reservation_ids' => $sorted->pluck('id')->values()->all(),
            'lines' => $sorted->map(fn (Reservation $r) => $this->lineText($r, $tz))->all(),
        ];

        if ($dryRun) {
            return $summary + ['dry_run' => true];
        }

        $draft = BexioMonthlyDraft::where('contact_id', $entity->id)->where('period', $period)->first();

        // Brouillon déjà validé dans bexio → on n'y touche plus ; les résas
        // restent non facturées et ressortiront dans un aperçu (dry-run).
        if ($draft && $draft->finalized_at) {
            return $summary + ['skipped' => 'facture déjà finalisée dans bexio'];
        }

        $toAppend = $sorted;

        if (! $draft) {
            // Création du brouillon avec la 1re ligne en ligne (jamais vide).
            $first = $sorted->first();
            $contactId = $this->contacts->sync($entity);
            $payload = $this->headerPayload($entity, $period, $tz) + [
                'contact_id' => $contactId,
                'positions' => [$this->inlinePosition($this->positionFor($first, $taxId, $accountId, $tz))],
            ];
            $created = $this->bexio->post('2.0/kb_invoice', $payload);
            $invoiceId = (int) ($created['id'] ?? 0);

            $draft = BexioMonthlyDraft::create([
                'contact_id' => $entity->id,
                'period' => $period,
                'bexio_invoice_id' => $invoiceId,
                'bexio_document_nr' => $created['document_nr'] ?? null,
                'has_special' => false,
            ]);
            $this->markBilled($first, $invoiceId);
            $toAppend = $sorted->slice(1);
        }

        $invoiceId = $draft->bexio_invoice_id;

        foreach ($toAppend as $r) {
            try {
                $this->bexio->post("2.0/kb_invoice/{$invoiceId}/kb_position_custom", $this->positionFor($r, $taxId, $accountId, $tz));
                $this->markBilled($r, $invoiceId);
            } catch (\Throwable $e) {
                // On laisse la résa non facturée : réessai au prochain passage.
                report($e);
            }
        }

        // Note « demande spéciale / catering » : ajoutée une seule fois.
        if ($hasSpecial && ! $draft->has_special) {
            try {
                $this->bexio->post("2.0/kb_invoice/{$invoiceId}/kb_position_custom", $this->specialNote($taxId, $accountId));
                $draft->has_special = true;
                $draft->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $summary + [
            'bexio_invoice_id' => $invoiceId,
            'document_nr' => $draft->bexio_document_nr,
        ];
    }

    /**
     * SIMULATION : crée dans bexio un brouillon étiqueté « SIMULATION
     * (à supprimer) » pour un groupe de réservations, SANS marquer quoi que ce
     * soit comme facturé et SANS créer de ligne de suivi. Sert uniquement à
     * visualiser le rendu dans l'interface bexio. À supprimer ensuite.
     *
     * @return array<string,mixed>
     */
    public function simulateDraft(Contact $entity, string $period, Collection $group): array
    {
        $tz = config('app.timezone', 'Europe/Zurich');
        $taxId = (int) config('services.bexio.tax_id');
        $accountId = (int) config('services.bexio.account_id');

        $sorted = $group->sortBy(fn (Reservation $r) => optional($r->events->min('start')))->values();
        $hasSpecial = $sorted->contains(fn (Reservation $r) => (bool) optional($r->room)->on_request);

        $positions = $sorted->map(fn (Reservation $r) => $this->inlinePosition($this->positionFor($r, $taxId, $accountId, $tz)))->values()->all();
        if ($hasSpecial) {
            $positions[] = $this->inlinePosition($this->specialNote($taxId, $accountId));
        }

        $payload = $this->headerPayload($entity, $period, $tz);
        $payload['title'] = 'SIMULATION (à supprimer) — '.$payload['title'];
        $payload['contact_id'] = $this->contacts->sync($entity);
        $payload['positions'] = $positions;

        $created = $this->bexio->post('2.0/kb_invoice', $payload);

        return [
            'entity' => $entity->display_name(),
            'period' => $period,
            'count' => $sorted->count(),
            'total' => round($sorted->sum(fn (Reservation $r) => round($r->finalPrice(), 2)), 2),
            'special' => $hasSpecial,
            'bexio_invoice_id' => $created['id'] ?? null,
            'document_nr' => $created['document_nr'] ?? null,
        ];
    }

    /** Supprime tous les brouillons de SIMULATION restés dans bexio. */
    public function deleteSimulations(): array
    {
        $deleted = [];
        $invoices = $this->bexio->get('2.0/kb_invoice');
        foreach ($invoices as $inv) {
            $title = (string) ($inv['title'] ?? '');
            if (str_starts_with($title, 'SIMULATION (à supprimer)')) {
                $id = (int) ($inv['id'] ?? 0);
                if ($id > 0) {
                    $this->bexio->delete('2.0/kb_invoice/'.$id);
                    $deleted[] = ['id' => $id, 'document_nr' => $inv['document_nr'] ?? '', 'title' => $title];
                }
            }
        }

        return $deleted;
    }

    private function markBilled(Reservation $r, int $invoiceId): void
    {
        $r->billed_at = now();
        $r->bexio_invoice_id = $invoiceId;
        $r->save();
    }

    private function periodOf(Reservation $r, string $tz): string
    {
        return $r->events->min('start')->copy()->setTimezone($tz)->format('Y-m');
    }

    /** En-tête de facture (sans contact_id ni positions). */
    private function headerPayload(Contact $entity, string $period, string $tz): array
    {
        $monthLabel = Carbon::createFromFormat('Y-m', $period, $tz)->locale('fr')->isoFormat('MMMM YYYY');
        $invoiceDate = Carbon::now($tz);
        $term = (int) config('services.bexio.payment_term_days', 30);

        return array_filter([
            'title' => 'Réservations La Pépite — '.$monthLabel,
            'user_id' => (int) config('services.bexio.user_id') ?: null,
            'language_id' => (int) config('services.bexio.language_id') ?: null,
            'bank_account_id' => (int) config('services.bexio.bank_account_id') ?: null,
            'currency_id' => (int) config('services.bexio.currency_id') ?: null,
            'payment_type_id' => 4,
            'is_valid_from' => $invoiceDate->toDateString(),
            'is_valid_to' => $invoiceDate->copy()->addDays($term)->toDateString(),
            'mwst_type' => 0,
            'mwst_is_net' => false,
        ], fn ($v) => $v !== null);
    }

    /** Position « custom » d'une réservation (sans le champ type). */
    private function positionFor(Reservation $r, int $taxId, int $accountId, string $tz): array
    {
        return [
            'amount' => '1',
            'unit_price' => number_format(round($r->finalPrice(), 2), 2, '.', ''),
            'tax_id' => $taxId,
            'account_id' => $accountId,
            'text' => $this->lineText($r, $tz),
        ];
    }

    private function specialNote(int $taxId, int $accountId): array
    {
        return [
            'amount' => '1',
            'unit_price' => '0.00',
            'tax_id' => $taxId,
            'account_id' => $accountId,
            'text' => '⚠️ À COMPLÉTER : ajouter manuellement les positions catering / services (demande spéciale — non saisies dans l\'app de réservation).',
        ];
    }

    /** Ajoute le champ type pour une position créée EN LIGNE (à la création). */
    private function inlinePosition(array $position): array
    {
        return ['type' => 'KbPositionCustom'] + $position;
    }

    private function lineText(Reservation $r, string $tz): string
    {
        $room = optional($r->room)->name ?? 'Salle';
        $slots = $r->events->sortBy('start')->map(function ($e) use ($tz) {
            $s = $e->start->copy()->setTimezone($tz);
            $en = $e->end->copy()->setTimezone($tz);
            $opts = $e->options->pluck('name')->filter()->implode(', ');

            return $s->format('d.m.Y H:i').'–'.$en->format('H:i').($opts ? ' (options : '.$opts.')' : '');
        })->implode(' / ');

        return $room.($r->title ? ' — '.$r->title : '').' : '.$slots;
    }
}
