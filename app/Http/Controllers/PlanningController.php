<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Availability\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
            ->orderBy('name')
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
        ]);
    }

    /**
     * Feuille d'affiches imprimables : un QR par salle (→ sa fiche) + un QR
     * global (→ le planning), à poser sur les portes et à l'entrée.
     */
    public function posters(): View
    {
        $rooms = Room::where('active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        return view('planning.posters', ['rooms' => $rooms]);
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
            ->orderBy('name')
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
