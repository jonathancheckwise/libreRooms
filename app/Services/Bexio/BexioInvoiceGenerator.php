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
    /** Marqueurs de titre (version « emojis ») selon l'état du brouillon. */
    private const P_ENCOURS = '⏳ EN COURS · ';

    private const P_AVALIDER = '✅ À VALIDER · ';

    /** kb_item_status_id d'un brouillon bexio (tout autre = émise/en suspens/…). */
    private const STATUS_DRAFT = 7;

    /**
     * Fuseau d'AFFICHAGE des heures sur les factures. L'app stocke tout en UTC
     * (config app.timezone = UTC) ; les factures doivent montrer l'heure locale
     * de Lausanne, sinon décalage de 1–2 h.
     */
    private const DISPLAY_TZ = 'Europe/Zurich';

    public function __construct(private BexioClient $bexio, private BexioContactSync $contacts) {}

    /**
     * Passe incrémentale complète, idempotente (planifiée toutes les heures) :
     *   1) ACCUMULATION : ajoute les réservations passées aux brouillons « en cours » ;
     *   2) CLÔTURE : les brouillons d'un mois écoulé passent en « à valider »
     *      (titre ✅, note « ne pas envoyer » retirée) ;
     *   3) NETTOYAGE : un brouillon « à valider » qui a été émis/envoyé dans bexio
     *      voit son titre nettoyé (préfixe/emoji retiré).
     *
     * @return array<int, array<string,mixed>>
     */
    public function accrue(bool $dryRun = false): array
    {
        $tz = self::DISPLAY_TZ;
        $cutover = Carbon::parse(config('services.bexio.billing_start'), $tz)->startOfDay();
        $taxId = (int) config('services.bexio.tax_id');
        $accountId = (int) config('services.bexio.account_id');
        $currentPeriod = Carbon::now($tz)->format('Y-m');

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

        // 1) ACCUMULATION
        foreach ($groups as $key => $group) {
            [$rootId, $period] = explode('|', (string) $key);
            if (! $rootId) {
                continue; // réservation sans entité : rien à facturer
            }
            $results[] = $this->accrueGroup($group->first()->tenant->root(), $period, $group, $taxId, $accountId, $tz, $dryRun);
        }

        if ($dryRun) {
            return $results; // on ne touche à rien en aperçu
        }

        // 2) CLÔTURE des brouillons d'un mois désormais écoulé → « à valider »
        BexioMonthlyDraft::where('status', 'en_cours')
            ->where('period', '<', $currentPeriod)
            ->get()
            ->each(function (BexioMonthlyDraft $d) use ($tz, &$results) {
                try {
                    $this->renameInvoice($d->bexio_invoice_id, self::P_AVALIDER.$this->baseTitle($d->period, $tz));
                    $this->removeDoNotSendNote($d->bexio_invoice_id);
                    $d->update(['status' => 'a_valider', 'finalized_at' => now()]);
                    $results[] = ['event' => 'clôture → à valider', 'entity' => optional($d->contact)->display_name(), 'period' => $d->period, 'document_nr' => $d->bexio_document_nr];
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        // 3) NETTOYAGE du titre quand la facture a été émise/envoyée dans bexio
        BexioMonthlyDraft::where('status', 'a_valider')
            ->get()
            ->each(function (BexioMonthlyDraft $d) use ($tz, &$results) {
                try {
                    $inv = $this->bexio->get('2.0/kb_invoice/'.$d->bexio_invoice_id);
                    if ((int) ($inv['kb_item_status_id'] ?? self::STATUS_DRAFT) !== self::STATUS_DRAFT) {
                        $this->renameInvoice($d->bexio_invoice_id, $this->baseTitle($d->period, $tz));
                        $d->update(['status' => 'envoyee', 'cleared_at' => now()]);
                        $results[] = ['event' => 'titre nettoyé (émise)', 'entity' => optional($d->contact)->display_name(), 'period' => $d->period, 'document_nr' => $d->bexio_document_nr];
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });

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

        // Mois déjà clôturé (à valider / envoyée) → on n'ajoute plus rien : la
        // facture est figée. Les éventuels retardataires restent non facturés et
        // ressortent dans un aperçu (dry-run) pour traitement manuel.
        if ($draft && $draft->status !== 'en_cours') {
            return $summary + ['skipped' => 'mois clôturé ('.$draft->status.')'];
        }

        $toAppend = $sorted;

        if (! $draft) {
            // Création du brouillon « ⏳ EN COURS » : note « ne pas envoyer » en
            // tête + la 1re ligne de réservation (jamais vide).
            $first = $sorted->first();
            $contactId = $this->contacts->sync($entity);
            $payload = $this->headerPayload($entity, $period, $tz, self::P_ENCOURS) + [
                'contact_id' => $contactId,
                'positions' => [
                    $this->inlinePosition($this->doNotSendNote($taxId, $accountId)),
                    $this->inlinePosition($this->positionFor($first, $taxId, $accountId, $tz)),
                ],
            ];
            $created = $this->bexio->post('2.0/kb_invoice', $payload);
            $invoiceId = (int) ($created['id'] ?? 0);

            $draft = BexioMonthlyDraft::create([
                'contact_id' => $entity->id,
                'period' => $period,
                'status' => 'en_cours',
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
    public function simulateDraft(Contact $entity, string $period, Collection $group, string $state = 'en_cours'): array
    {
        $tz = self::DISPLAY_TZ;
        $taxId = (int) config('services.bexio.tax_id');
        $accountId = (int) config('services.bexio.account_id');

        $sorted = $group->sortBy(fn (Reservation $r) => optional($r->events->min('start')))->values();
        $hasSpecial = $sorted->contains(fn (Reservation $r) => (bool) optional($r->room)->on_request);
        $enCours = $state === 'en_cours';

        $positions = [];
        // Mois en cours : note d'avertissement en tête pour éviter un envoi prématuré.
        if ($enCours) {
            $positions[] = $this->inlinePosition($this->doNotSendNote($taxId, $accountId));
        }
        foreach ($sorted as $r) {
            $positions[] = $this->inlinePosition($this->positionFor($r, $taxId, $accountId, $tz));
        }
        if ($hasSpecial) {
            $positions[] = $this->inlinePosition($this->specialNote($taxId, $accountId));
        }

        $prefix = $enCours ? '⏳ EN COURS · ' : '✅ À VALIDER · ';
        $payload = $this->headerPayload($entity, $period, $tz);
        $payload['title'] = $prefix.'SIMULATION (à supprimer) · '.$payload['title'];
        $payload['contact_id'] = $this->contacts->sync($entity);
        $payload['positions'] = $positions;

        $created = $this->bexio->post('2.0/kb_invoice', $payload);

        return [
            'entity' => $entity->display_name(),
            'period' => $period,
            'state' => $state,
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
            if (str_contains($title, 'SIMULATION (à supprimer)')) {
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

    /**
     * Renomme une facture bexio. L'API impose de renvoyer tout l'en-tête : on
     * relit l'objet, on garde les champs modifiables, on force les champs
     * techniques que le GET n'expose pas (décimales, vue), on change le titre.
     * Les positions ne sont PAS renvoyées → elles restent intactes.
     */
    private function renameInvoice(int $invoiceId, string $newTitle): void
    {
        $full = $this->bexio->get('2.0/kb_invoice/'.$invoiceId);

        $keys = ['contact_id', 'user_id', 'logopaper_id', 'language_id', 'bank_account_id',
            'currency_id', 'payment_type_id', 'header', 'footer', 'mwst_type', 'mwst_is_net',
            'is_valid_from', 'is_valid_to'];
        $payload = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $full) && $full[$k] !== null) {
                $payload[$k] = $full[$k];
            }
        }
        $payload = array_merge($payload, [
            'nb_decimals_amount' => 2,
            'nb_decimals_price' => 2,
            'is_compact_view' => false,
            'show_position_taxes' => false,
            'title' => $newTitle,
        ]);

        $this->bexio->put('2.0/kb_invoice/'.$invoiceId, $payload);
    }

    /** Retire la ligne note « NE PAS ENVOYER » d'un brouillon (au passage en « à valider »). */
    private function removeDoNotSendNote(int $invoiceId): void
    {
        $inv = $this->bexio->get('2.0/kb_invoice/'.$invoiceId);
        foreach (($inv['positions'] ?? []) as $pos) {
            if (str_contains((string) ($pos['text'] ?? ''), 'NE PAS ENVOYER') && isset($pos['id'])) {
                $this->bexio->delete('2.0/kb_invoice/'.$invoiceId.'/kb_position_custom/'.$pos['id']);
            }
        }
    }

    /** Titre de base d'une facture mensuelle (sans préfixe d'état). */
    private function baseTitle(string $period, string $tz): string
    {
        $monthLabel = Carbon::createFromFormat('Y-m', $period, $tz)->locale('fr')->isoFormat('MMMM YYYY');

        return 'Réservations La Pépite — '.$monthLabel;
    }

    /** En-tête de facture (sans contact_id ni positions). */
    private function headerPayload(Contact $entity, string $period, string $tz, string $prefix = ''): array
    {
        $invoiceDate = Carbon::now($tz);
        $term = (int) config('services.bexio.payment_term_days', 30);

        return array_filter([
            'title' => $prefix.$this->baseTitle($period, $tz),
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

    /** Note d'en-tête pour un brouillon du mois EN COURS (ne pas envoyer). */
    private function doNotSendNote(int $taxId, int $accountId): array
    {
        return [
            'amount' => '1',
            'unit_price' => '0.00',
            'tax_id' => $taxId,
            'account_id' => $accountId,
            'text' => '⚠️ NE PAS ENVOYER AU CLIENT — facture du mois EN COURS de constitution (se complète automatiquement au fil des réservations ; prête à valider le 1er du mois suivant).',
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
