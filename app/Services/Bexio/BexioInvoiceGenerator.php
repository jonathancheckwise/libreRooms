<?php

namespace App\Services\Bexio;

use App\Enums\ReservationStatus;
use App\Models\Contact;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Génère les factures mensuelles globales bexio (1 par entité) à partir des
 * réservations facturables d'un mois. Factures créées en BROUILLON (à valider).
 * Ne facture que les résas ≥ date de bascule et PAS encore facturées
 * (billed_at null) → aucune double facturation. Les résas sur salle on_request
 * (demande spéciale) ajoutent une note « compléter catering » et marquent la
 * facture comme « à valider ».
 */
class BexioInvoiceGenerator
{
    public function __construct(private BexioClient $bexio, private BexioContactSync $contacts) {}

    /**
     * @return array<int, array<string,mixed>>  un résumé par entité facturée
     */
    public function generateForMonth(string $month, bool $dryRun = true): array
    {
        $tz = config('app.timezone', 'Europe/Zurich');
        $monthStart = Carbon::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $cutover = Carbon::parse(config('services.bexio.billing_start'), $tz)->startOfDay();
        $periodStart = $monthStart->greaterThan($cutover) ? $monthStart : $cutover;

        if ($periodStart->greaterThan($monthEnd)) {
            return []; // mois entièrement avant la bascule
        }

        $reservations = Reservation::query()
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::FINISHED])
            ->where('is_free', false)
            ->whereNull('billed_at')
            ->whereHas('events', fn ($q) => $q->whereBetween('start', [$periodStart->copy()->utc(), $monthEnd->copy()->utc()]))
            ->with(['tenant.mergedInto', 'room', 'events.options'])
            ->get()
            ->filter(fn (Reservation $r) => $r->finalPrice() > 0);

        $groups = $reservations->groupBy(fn (Reservation $r) => optional($r->tenant)?->root()?->id);

        $results = [];
        foreach ($groups as $rootId => $group) {
            if (! $rootId) {
                continue;
            }
            $results[] = $this->buildForEntity($group->first()->tenant->root(), $group, $monthStart, $dryRun);
        }

        return $results;
    }

    private function buildForEntity(Contact $entity, Collection $group, Carbon $monthStart, bool $dryRun): array
    {
        $tz = config('app.timezone', 'Europe/Zurich');
        $hasSpecial = $group->contains(fn (Reservation $r) => (bool) optional($r->room)->on_request);
        $taxId = (int) config('services.bexio.tax_id');
        $accountId = (int) config('services.bexio.account_id');

        $positions = [];
        $total = 0.0;
        foreach ($group->sortBy(fn (Reservation $r) => optional($r->events->min('start')))->values() as $r) {
            $price = round($r->finalPrice(), 2);
            $total += $price;
            $positions[] = [
                'type' => 'KbPositionCustom',
                'amount' => '1',
                'unit_price' => number_format($price, 2, '.', ''),
                'tax_id' => $taxId,
                'account_id' => $accountId,
                'text' => $this->lineText($r, $tz),
            ];
        }
        if ($hasSpecial) {
            $positions[] = [
                'type' => 'KbPositionCustom',
                'amount' => '1',
                'unit_price' => '0.00',
                'tax_id' => $taxId,
                'account_id' => $accountId,
                'text' => '⚠️ À COMPLÉTER : ajouter manuellement les positions catering / services (demande spéciale — non saisies dans l\'app de réservation).',
            ];
        }

        $invoiceDate = Carbon::now($tz);
        $monthLabel = $monthStart->locale('fr')->isoFormat('MMMM YYYY');
        $term = (int) config('services.bexio.payment_term_days', 30);

        $payload = array_filter([
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
            'positions' => $positions,
        ], fn ($v) => $v !== null);

        $summary = [
            'entity' => $entity->display_name(),
            'entity_id' => $entity->id,
            'reservation_ids' => $group->pluck('id')->values()->all(),
            'count' => $group->count(),
            'total' => round($total, 2),
            'special' => $hasSpecial,
            'lines' => array_column($positions, 'text'),
        ];

        if ($dryRun) {
            return $summary + ['dry_run' => true];
        }

        // Écriture réelle : contact bexio (anti-doublon) + facture brouillon + marquage.
        $payload['contact_id'] = $this->contacts->sync($entity);
        $created = $this->bexio->post('2.0/kb_invoice', $payload);
        $invoiceId = (int) ($created['id'] ?? 0);

        if ($invoiceId > 0) {
            foreach ($group as $r) {
                $r->billed_at = now();
                $r->bexio_invoice_id = $invoiceId;
                $r->save();
            }
        }

        return $summary + [
            'bexio_invoice_id' => $invoiceId,
            'document_nr' => $created['document_nr'] ?? '',
        ];
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
