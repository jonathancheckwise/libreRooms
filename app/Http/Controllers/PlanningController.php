<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Availability\AvailabilityService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Planning public (La Pépite) : vue d'ensemble de toutes les salles,
 * accessible sans connexion (destination des QR codes affichés dans le lieu).
 * Grille couleur « aujourd'hui » (libre / partiel / complet) + calendrier
 * toutes salles (Jour / Semaine / Mois) alimenté par events().
 */
class PlanningController extends Controller
{
    /** Palette pour distinguer les salles (calendrier + pastilles). */
    private const PALETTE = [
        '#C8861F', '#2563eb', '#059669', '#db2777', '#7c3aed', '#0891b2',
        '#ea580c', '#4d7c0f', '#9333ea', '#0d9488', '#b45309', '#be123c',
    ];

    public function index(AvailabilityService $service): View
    {
        $rooms = Room::where('active', true)
            ->where('is_public', true)
            ->with(['availabilityWindows', 'unavailabilities'])
            ->pepiteOrder()
            ->get();

        $cards = $rooms->values()->map(function (Room $room, int $i) use ($service) {
            return array_merge(
                ['room' => $room, 'color' => self::PALETTE[$i % count(self::PALETTE)]],
                $this->todaySummary($room, $service),
            );
        });

        return view('planning.index', [
            'cards' => $cards,
            'rooms' => $rooms,
            'palette' => self::PALETTE,
            'slots' => $this->slotWindows(),
        ]);
    }

    /** Fenêtres horaires globales (pour mapper une sélection au bon forfait). */
    private function slotWindows(): array
    {
        $s = app(\App\Models\SystemSettings::class);
        $hm = fn ($v) => substr((string) $v, 0, 5);

        return [
            'hourly_max' => (int) $s->hourly_max_hours,
            'morning' => [$hm($s->half_day_morning_start), $hm($s->half_day_morning_end)],
            'afternoon' => [$hm($s->half_day_afternoon_start), $hm($s->half_day_afternoon_end)],
            'evening' => [$hm($s->half_day_evening_start), $hm($s->half_day_evening_end)],
            'full' => [$hm($s->full_day_start), $hm($s->full_day_end)],
        ];
    }

    /**
     * Aperçu de DÉMO : mêmes vues, mais avec des salles et un planning
     * FACTICES (en mémoire, rien en base) pour montrer le rendu quand il y a
     * des réservations. N'affecte ni les vraies salles ni le planning public.
     */
    public function demo(): View
    {
        $palette = self::PALETTE;

        $fake = [
            ['name' => 'Salle Démo', 'status' => 'partial', 'busyCount' => 2, 'occupiedNow' => true],
            ['name' => 'Atelier Créatif', 'status' => 'free', 'busyCount' => 0, 'occupiedNow' => false],
            ['name' => 'Grande Salle Événement', 'status' => 'full', 'busyCount' => 3, 'occupiedNow' => true],
            ['name' => 'Bulle Focus', 'status' => 'partial', 'busyCount' => 1, 'occupiedNow' => false],
            ['name' => 'Espace Coworking', 'status' => 'free', 'busyCount' => 0, 'occupiedNow' => false],
            ['name' => 'Petite Réunion', 'status' => 'full', 'busyCount' => 4, 'occupiedNow' => false],
            ['name' => 'Studio Créa', 'status' => 'closed', 'busyCount' => 0, 'occupiedNow' => false],
            ['name' => 'Salle Zen', 'status' => 'free', 'busyCount' => 0, 'occupiedNow' => false],
        ];

        $cards = collect($fake)->map(fn ($r, $i) => array_merge($r, [
            'room' => (object) ['name' => $r['name'], 'slug' => 'demo-'.$i],
            'color' => $palette[$i % count($palette)],
        ]));

        // Faux créneaux répartis sur la semaine courante (lun→ven).
        $tz = config('app.timezone', 'Europe/Zurich');
        $monday = Carbon::now($tz)->startOfWeek();
        $plan = [ // [jour 0=lun, heure début, durée h, index salle]
            [0, 9, 2, 0], [0, 14, 3, 2], [0, 10, 4, 5],
            [1, 9, 8, 2], [1, 13, 2, 3], [1, 11, 1, 0],
            [2, 8, 2, 5], [2, 15, 2, 2],
            [3, 9, 3, 0], [3, 14, 2, 5], [3, 10, 5, 2],
            [4, 9, 4, 3], [4, 13, 4, 5],
        ];
        $events = [];
        foreach ($plan as [$d, $h, $dur, $ri]) {
            $start = $monday->copy()->addDays($d)->setTime($h, 0);
            $events[] = [
                'title' => $fake[$ri]['name'],
                'start' => $start->format('Y-m-d\TH:i:sP'),
                'end' => $start->copy()->addHours($dur)->format('Y-m-d\TH:i:sP'),
                'color' => $palette[$ri % count($palette)],
                'extendedProps' => ['room' => $fake[$ri]['name']],
            ];
        }

        return view('planning.index', [
            'cards' => $cards,
            'palette' => $palette,
            'demo' => true,
            'demoEvents' => $events,
            'slots' => $this->slotWindows(),
        ]);
    }

    /**
     * Planning d'une salle : vue du jour (grille horaire) directement, avec
     * navigation jour/semaine. Publique — destination du QR de la salle.
     */
    public function room(Room $room): View
    {
        abort_unless($room->active && $room->is_public, 404);
        $room->load(['availabilityWindows', 'unavailabilities', 'owner.contact']);

        return view('rooms.planning', ['room' => $room]);
    }

    /**
     * Feuille d'affiches imprimables (HTML) : un QR par salle + un QR global.
     * Chaque QR renvoie vers son affiche PDF, prête à imprimer.
     */
    public function posters(): View
    {
        $rooms = Room::where('active', true)
            ->where('is_public', true)
            ->pepiteOrder()
            ->get();

        return view('planning.posters', ['rooms' => $rooms]);
    }

    /** Affiche PDF A4 d'une salle (QR → planning de la salle). */
    public function roomPoster(Room $room): Response
    {
        abort_unless($room->active && $room->is_public, 404);

        $html = view('pdf.qr-poster', [
            'title' => $room->name,
            'subtitle' => __('La Pépite'),
            'qr' => $this->qrDataUri(route('rooms.planning', $room)),
            'hint' => __('Scan to see availability and book this room'),
        ])->render();

        return $this->streamPdf($html, 'affiche-'.$room->slug.'.pdf');
    }

    /** Affiche PDF A4 globale (QR → planning de toutes les salles). */
    public function globalPoster(): Response
    {
        $html = view('pdf.qr-poster', [
            'title' => __('All rooms'),
            'subtitle' => __('La Pépite'),
            'qr' => $this->qrDataUri(route('planning.index')),
            'hint' => __('Scan to see live availability and book'),
        ])->render();

        return $this->streamPdf($html, 'affiche-planning.pdf');
    }

    /** QR code d'une URL en data URI PNG (endroid, déjà installé). */
    private function qrDataUri(string $url): string
    {
        return Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->size(700)
            ->margin(16)
            ->build()
            ->getDataUri();
    }

    /** Rend une chaîne HTML en PDF A4 (dompdf) et la renvoie inline. */
    private function streamPdf(string $html, string $filename): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * Flux d'occupation de toutes les salles pour FullCalendar.
     * Public : on n'expose que « Occupé » + le nom de la salle (aucun détail
     * de réservation).
     */
    public function events(AvailabilityService $service): JsonResponse
    {
        $rooms = Room::where('active', true)
            ->where('is_public', true)
            ->with(['unavailabilities'])
            ->pepiteOrder()
            ->get();

        $from = now('UTC')->copy()->subDay();
        $to = now('UTC')->copy()->addWeeks(4);

        $events = [];
        foreach ($rooms->values() as $i => $room) {
            $color = self::PALETTE[$i % count(self::PALETTE)];
            $tz = $room->getTimezone();

            foreach ($service->loadBusySlots($room, $tz, $from, $to) as $slot) {
                $events[] = [
                    'title' => $room->name,
                    'start' => $slot['start']->format('Y-m-d\TH:i:sP'),
                    'end' => $slot['end']->format('Y-m-d\TH:i:sP'),
                    'color' => $color,
                    'extendedProps' => ['room' => $room->name, 'slug' => $room->slug],
                ];
            }
        }

        return response()->json(['events' => $events]);
    }

    /**
     * Résumé de la journée courante d'une salle : couleur (libre / partiel /
     * complet / fermé) et nombre de créneaux occupés.
     */
    private function todaySummary(Room $room, AvailabilityService $service): array
    {
        $tz = $room->getTimezone();
        $now = now($tz);
        $isoDay = (int) $now->isoWeekday(); // 1 = lundi … 7 = dimanche

        // Salle non réservable ou jour non ouvré → « fermé ».
        $allowed = array_map('intval', (array) ($room->allowed_weekdays ?? []));
        if (! $room->bookable || (! empty($allowed) && ! in_array($isoDay, $allowed, true))) {
            return ['status' => 'closed', 'busyCount' => 0, 'occupiedNow' => false];
        }

        // Fenêtre du jour : day_start/day_end, repli 09:00–21:00.
        $start = $now->copy()->setTimeFromTimeString(substr($room->day_start_time ?: '09:00', 0, 5));
        $end = $now->copy()->setTimeFromTimeString(substr($room->day_end_time ?: '21:00', 0, 5));
        $windowMin = max(1, $start->diffInMinutes($end));

        $nowUtc = now('UTC');
        $slots = $service->loadBusySlots(
            $room, $tz,
            $nowUtc->copy()->startOfDay(),
            $nowUtc->copy()->endOfDay(),
        );

        $busyMin = 0;
        $count = 0;
        $occupiedNow = false;
        foreach ($slots as $slot) {
            $s = $slot['start']->copy()->setTimezone($tz);
            $e = $slot['end']->copy()->setTimezone($tz);
            if ($e <= $start || $s >= $end) {
                continue; // hors fenêtre du jour
            }
            $count++;
            $clampedStart = $s->lessThan($start) ? $start : $s;
            $clampedEnd = $e->greaterThan($end) ? $end : $e;
            $busyMin += $clampedStart->diffInMinutes($clampedEnd);
            if ($slot['start'] <= $nowUtc && $slot['end'] > $nowUtc) {
                $occupiedNow = true;
            }
        }

        $status = match (true) {
            $count === 0 => 'free',
            $busyMin >= $windowMin - 15 => 'full', // ~complet (tolérance 15 min)
            default => 'partial',
        };

        return ['status' => $status, 'busyCount' => $count, 'occupiedNow' => $occupiedNow];
    }
}
