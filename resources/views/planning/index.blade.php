@extends('layouts.app')

@section('title', __('Rooms planning'))

@php
    $statusColor = [
        'free' => '#059669',
        'partial' => '#ea580c',
        'full' => '#dc2626',
        'closed' => '#9ca3af',
    ];
    $statusLabel = [
        'free' => __('Available'),
        'partial' => __('Partially booked'),
        'full' => __('Fully booked'),
        'closed' => __('Closed today'),
    ];
@endphp

@section('content')
@php $demo = $demo ?? false; $demoEvents = $demoEvents ?? null; @endphp
<div class="max-w-6xl mx-auto py-8 px-4" id="planning-root">

    @if($demo ?? false)
        <div style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:10px;padding:.6rem 1rem;margin-bottom:1.25rem;font-weight:600">
            👁️ {{ __('Preview with sample data — these rooms and bookings are fake.') }}
            <a href="{{ route('planning.index') }}" style="color:#2563eb;font-weight:600">{{ __('See the real planning') }}</a>
        </div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.6rem;font-weight:700;margin:0">{{ __('Rooms planning') }}</h1>
            <p style="color:#6b7280;margin:.25rem 0 0">{{ __('Live availability of all spaces. Tap a room to book.') }}</p>
        </div>
        {{-- QR global : à afficher / imprimer pour l'entrée --}}
        <div style="display:flex;align-items:center;gap:.75rem;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:.75rem 1rem">
            <a id="planning-qr" href="{{ route('planning.poster') }}" target="_blank" rel="noopener"
               data-url="{{ route('planning.index') }}" title="{{ __('Open printable PDF') }}" style="line-height:0"></a>
            <div style="font-size:.85rem;line-height:1.3">
                <strong>{{ __('Scan to view') }}</strong><br>
                <span style="color:#6b7280">{{ __('all rooms availability') }}</span><br>
                <a href="{{ route('planning.poster') }}" target="_blank" rel="noopener" style="color:#2563eb">⬇ {{ __('Printable PDF') }}</a>
                · <a href="{{ route('planning.posters') }}" style="color:#2563eb">{{ __('All posters') }}</a>
            </div>
        </div>
    </div>

    {{-- Grille couleur : état du jour par salle. Clic sur une tuile = focus. --}}
    <div id="planning-grid">
        @foreach($cards as $card)
            @php $room = $card['room']; $c = $statusColor[$card['status']]; @endphp
            <div class="planning-card" data-name="{{ $room->name }}" data-slug="{{ $room->slug }}"
                 role="button" tabindex="0"
                 style="background:#fff;border:1px solid #e5e7eb;border-left:6px solid {{ $c }};border-radius:10px;padding:.85rem 1rem;cursor:pointer">
                {{-- Flèche « voir tout » visible seulement quand la tuile est en focus --}}
                <button type="button" class="pl-card-back" hidden>← {{ __('See all') }}</button>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
                    <span style="font-weight:600">{{ $room->name }}</span>
                    <span style="width:11px;height:11px;border-radius:50%;background:{{ $c }};flex:none"
                          @if($card['occupiedNow']) title="{{ __('Occupied right now') }}" @endif></span>
                </div>
                <div style="font-size:.82rem;color:{{ $c }};font-weight:600;margin-top:.35rem">
                    {{ $statusLabel[$card['status']] }}
                </div>
                <div style="font-size:.78rem;color:#6b7280;margin-top:.1rem">
                    @if($card['status'] === 'closed')
                        {{ __('Not bookable today') }}
                    @elseif($card['busyCount'] === 0)
                        {{ __('No booking today') }}
                    @else
                        {{ trans_choice(':count booking today|:count bookings today', $card['busyCount'], ['count' => $card['busyCount']]) }}
                    @endif
                    @if($card['occupiedNow']) · <span style="color:#dc2626">● {{ __('now') }}</span> @endif
                </div>
            </div>
        @endforeach
    </div>

    <style>
        #planning-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.75rem; margin-bottom:2rem; }
        .planning-card:hover { box-shadow:0 2px 10px rgba(0,0,0,.08); }
        .pl-card-back { display:none; background:none; border:none; color:#6b7280; font-size:.85rem; cursor:pointer; padding:0 0 .4rem; }
        /* Mode focus : une seule tuile, agrandie, pleine largeur */
        #planning-grid.is-focus { grid-template-columns:1fr; }
        #planning-grid.is-focus .planning-card:not(.is-focus) { display:none; }
        #planning-grid.is-focus .planning-card.is-focus { padding:1.25rem 1.5rem; }
        #planning-grid.is-focus .planning-card.is-focus .pl-card-back { display:inline-block; }
        #planning-grid.is-focus .planning-card.is-focus > div:nth-of-type(1) span:first-child { font-size:1.4rem; }
    </style>

    {{-- Calendrier : Jour / Semaine / Mois --}}
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between;margin:0 0 .6rem">
        <h2 style="font-size:1.15rem;font-weight:700;margin:0" id="pl-cal-title">{{ __('Calendar') }}</h2>
        <div style="display:flex;gap:.5rem">
            <button id="pl-see-all" type="button" class="pl-btn" hidden>← {{ __('See all rooms') }}</button>
            <button id="pl-toggle-all" type="button" class="pl-btn">{{ __('Deselect all') }}</button>
        </div>
    </div>

    {{-- Barre de réservation (mode focus sur une salle) --}}
    <div id="pl-book-bar" hidden
         style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:.7rem 1rem;margin-bottom:.75rem">
        <span id="pl-book-label" style="font-size:.9rem;color:#9a3412"></span>
        <a id="pl-book-btn" href="#" class="pl-book-cta">{{ __('Book the next slot') }}</a>
    </div>

    {{-- Filtres par salle (légende cliquable) — mode global --}}
    <div id="planning-legend" style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.75rem">
        @foreach($cards as $card)
            <button type="button" class="pl-legend" data-name="{{ $card['room']->name }}"
                    style="border:1px solid #e5e7eb;background:#fff;border-radius:999px;padding:.2rem .7rem;font-size:.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
                <span style="width:10px;height:10px;border-radius:50%;background:{{ $card['color'] }};flex:none"></span>
                {{ $card['room']->name }}
            </button>
        @endforeach
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem">
        <div id="planning-calendar"></div>
    </div>

    <style>
        .pl-btn { border:1px solid #e5e7eb; background:#fff; border-radius:8px; padding:.35rem .8rem; font-size:.85rem; cursor:pointer; font-weight:600; }
        .pl-btn:hover { background:#f9fafb; }
        .pl-book-cta { background:#C8861F; color:#fff; text-decoration:none; border-radius:8px; padding:.5rem 1.1rem; font-weight:700; font-size:.9rem; white-space:nowrap; }
        .pl-book-cta:hover { background:#a86f19; }
    </style>
</div>

@php
    $roomsData = ($cards ?? collect())->map(function ($card) use ($demo) {
        $r = $card['room'];
        $isDemo = $demo ?? false;
        // Un responsable peut réserver directement une salle « sur demande ».
        $canManage = ! $isDemo && (bool) auth()->user()?->can('manageReservations', $r);
        $onRequest = ! $isDemo && (bool) data_get($r, 'on_request') && ! $canManage;
        return [
            'name' => data_get($r, 'name'),
            'slug' => data_get($r, 'slug'),
            'onRequest' => $onRequest,
            'bookUrl' => $isDemo ? '#' : ($onRequest
                ? route('special-requests.create', ['room' => data_get($r, 'id')])
                : route('reservations.create', $r)),
            'dayStart' => substr((string) (data_get($r, 'day_start_time') ?: '09:00'), 0, 5),
            'dayEnd' => substr((string) (data_get($r, 'day_end_time') ?: '21:00'), 0, 5),
        ];
    })->values();
@endphp
<script>
window.PlanningRooms = @json($roomsData);
window.PlanningSlots = @json($slots ?? null);
</script>

@once
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
@endonce

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- QR global ---
    try {
        var box = document.getElementById('planning-qr');
        if (box && window.qrcode) {
            var qr = qrcode(0, 'M');
            qr.addData(box.dataset.url);
            qr.make();
            box.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 1 });
            var svg = box.querySelector('svg');
            if (svg) { svg.style.width = '96px'; svg.style.height = '96px'; }
        }
    } catch (e) { /* QR non bloquant */ }

    // --- Calendrier ---
    var el = document.getElementById('planning-calendar');
    if (!el || !window.FullCalendar) return;

    var rooms = window.PlanningRooms || [];
    var roomByName = {};
    rooms.forEach(function (r) { roomByName[r.name] = r; });

    var hidden = new Set();      // salles masquées (mode global)
    var focusName = null;        // salle en focus (null = vue globale)
    var selectedSlot = null;     // créneau sélectionné dans le calendrier
    var lastEvents = [];         // derniers events chargés
    var DEMO_EVENTS = @js($demoEvents ?? null);

    var grid = document.getElementById('planning-grid');
    var legend = document.getElementById('planning-legend');
    var bookBar = document.getElementById('pl-book-bar');
    var bookBtn = document.getElementById('pl-book-btn');
    var bookLabel = document.getElementById('pl-book-label');
    var seeAllBtn = document.getElementById('pl-see-all');
    var toggleAllBtn = document.getElementById('pl-toggle-all');

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmtDate(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function fmtHM(d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()); }

    function eventVisible(ev) {
        var name = ev.extendedProps && ev.extendedProps.room;
        return focusName ? (name === focusName) : !hidden.has(name);
    }

    var calendar = new FullCalendar.Calendar(el, {
        initialView: 'timeGridWeek',
        locale: @js(str_replace('_', '-', app()->getLocale())),
        firstDay: 1,
        allDaySlot: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',
        nowIndicator: true,
        height: 'auto',
        selectable: false,
        selectMirror: true,
        selectOverlap: false,   // interdit de sélectionner par-dessus un créneau occupé
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridDay,timeGridWeek,dayGridMonth',
        },
        buttonText: {
            today: @js(__('Today')), month: @js(__('Month')),
            week: @js(__('Week')), day: @js(__('Day')),
        },
        events: function (info, success, failure) {
            var done = function (list) {
                lastEvents = list;
                success(list.filter(eventVisible));
                if (focusName) updateBookBar();
            };
            if (DEMO_EVENTS) { done(DEMO_EVENTS); return; }
            fetch('{{ route('planning.events') }}')
                .then(function (r) { return r.json(); })
                .then(function (data) { done(data.events || []); })
                .catch(failure);
        },
        select: function (info) {
            if (!focusName) { calendar.unselect(); return; }
            var startHM = info.allDay ? ((roomByName[focusName] || {}).dayStart || '09:00') : fmtHM(info.start);
            var endHM = info.allDay ? ((roomByName[focusName] || {}).dayEnd || '17:00') : fmtHM(info.end);
            var durH = info.allDay ? 1 : Math.max(1, Math.round((info.end - info.start) / 3600000));
            selectedSlot = {
                date: fmtDate(info.start),
                startHM: startHM,
                endHM: endHM,
                durationH: durH,
                mode: matchMode(startHM, endHM), // forfait si la sélection = une fenêtre
            };
            updateBookBar();
        },
        unselect: function () { if (selectedSlot) { selectedSlot = null; updateBookBar(); } },
        eventDisplay: 'block',
    });
    calendar.render();

    // --- Réservation depuis le focus ---
    var SLOTS = window.PlanningSlots || {};
    var MODE_LABELS = {
        hourly: @js(__('Hourly')), morning: @js(__('Morning half-day')),
        afternoon: @js(__('Afternoon half-day')), evening: @js(__('Evening half-day')),
        full: @js(__('Full day')),
    };
    // Si la sélection colle exactement à une fenêtre globale, on prend le forfait.
    function matchMode(startHM, endHM) {
        var eq = function (w) { return w && startHM === w[0] && endHM === w[1]; };
        if (eq(SLOTS.full)) return 'full';
        if (eq(SLOTS.morning)) return 'morning';
        if (eq(SLOTS.afternoon)) return 'afternoon';
        if (eq(SLOTS.evening)) return 'evening';
        return 'hourly';
    }
    function bookUrlWith(r, slot) {
        var mode = slot.mode || 'hourly';
        var u = new URL(r.bookUrl, window.location.origin);
        u.searchParams.set('mode', mode);
        u.searchParams.set('date', slot.date);
        if (mode === 'hourly') {
            u.searchParams.set('start', slot.startHM);
            u.searchParams.set('duration', slot.durationH || 1);
        }
        return u.toString();
    }
    function nextFreeHour(r) {
        var now = new Date();
        var busy = lastEvents.filter(function (ev) {
            return (ev.extendedProps && ev.extendedProps.room) === r.name;
        }).map(function (ev) { return { s: new Date(ev.start), e: new Date(ev.end) }; });
        var ds = parseInt(r.dayStart.split(':')[0], 10);
        var de = parseInt(r.dayEnd.split(':')[0], 10);
        for (var h = Math.max(ds, now.getHours() + 1); h < de; h++) {
            var s = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, 0, 0);
            var e = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h + 1, 0, 0);
            if (!busy.some(function (b) { return b.s < e && b.e > s; })) {
                return { date: fmtDate(now), startHM: pad(h) + ':00', durationH: 1 };
            }
        }
        return null;
    }
    function updateBookBar() {
        var r = roomByName[focusName];
        if (!r) return;
        if (r.onRequest || !r.bookUrl) {
            bookLabel.textContent = @js(__('This room is on request (quote).'));
            bookBtn.textContent = @js(__('Make a request'));
            bookBtn.href = r.bookUrl || '#';
            return;
        }
        if (selectedSlot) {
            var desc = (selectedSlot.mode && selectedSlot.mode !== 'hourly')
                ? MODE_LABELS[selectedSlot.mode]
                : (selectedSlot.startHM + ' · ' + selectedSlot.durationH + 'h');
            bookLabel.textContent = @js(__('Selected slot:')) + ' ' + selectedSlot.date + ' · ' + desc;
            bookBtn.textContent = @js(__('Book this slot'));
            bookBtn.href = bookUrlWith(r, selectedSlot);
        } else {
            var nxt = nextFreeHour(r);
            bookLabel.textContent = nxt
                ? (@js(__('Next available:')) + ' ' + nxt.date + ' ' + nxt.startHM)
                : @js(__('Pick a slot in the calendar to book it.'));
            bookBtn.textContent = @js(__('Book the next slot'));
            bookBtn.href = nxt ? bookUrlWith(r, nxt) : r.bookUrl;
        }
    }

    // --- Focus / retour global ---
    function focusRoom(name) {
        if (!roomByName[name]) return;
        focusName = name;
        selectedSlot = null;
        grid.classList.add('is-focus');
        document.querySelectorAll('.planning-card').forEach(function (c) {
            c.classList.toggle('is-focus', c.dataset.name === name);
        });
        legend.style.display = 'none';
        seeAllBtn.hidden = false;
        toggleAllBtn.hidden = true;
        bookBar.hidden = false;
        calendar.setOption('selectable', true);
        var rr = roomByName[name];
        calendar.setOption('selectConstraint', { startTime: rr.dayStart || '08:00', endTime: rr.dayEnd || '22:00' });
        calendar.changeView('timeGridDay');
        calendar.today();
        calendar.refetchEvents();
        updateBookBar();
    }
    function seeAll() {
        focusName = null;
        selectedSlot = null;
        grid.classList.remove('is-focus');
        document.querySelectorAll('.planning-card').forEach(function (c) { c.classList.remove('is-focus'); });
        legend.style.display = 'flex';
        seeAllBtn.hidden = true;
        toggleAllBtn.hidden = false;
        bookBar.hidden = true;
        calendar.unselect();
        calendar.setOption('selectable', false);
        calendar.setOption('selectConstraint', null);
        calendar.changeView('timeGridWeek');
        calendar.refetchEvents();
    }

    document.querySelectorAll('.planning-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('.pl-card-back')) return;
            if (card.classList.contains('is-focus')) return;
            focusRoom(card.dataset.name);
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); focusRoom(card.dataset.name); }
        });
    });
    document.querySelectorAll('.pl-card-back').forEach(function (b) {
        b.addEventListener('click', function (e) { e.stopPropagation(); seeAll(); });
    });
    seeAllBtn.addEventListener('click', seeAll);

    // --- Légende + tout (dé)sélectionner ---
    document.querySelectorAll('.pl-legend').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = btn.dataset.name;
            if (hidden.has(name)) { hidden.delete(name); btn.style.opacity = '1'; }
            else { hidden.add(name); btn.style.opacity = '.35'; }
            syncToggleLabel();
            calendar.refetchEvents();
        });
    });
    function syncToggleLabel() {
        var total = document.querySelectorAll('.pl-legend').length;
        toggleAllBtn.textContent = (hidden.size >= total && total > 0) ? @js(__('Select all')) : @js(__('Deselect all'));
    }
    toggleAllBtn.addEventListener('click', function () {
        var total = document.querySelectorAll('.pl-legend').length;
        var selectAll = hidden.size >= total; // tout masqué → on ré-affiche
        document.querySelectorAll('.pl-legend').forEach(function (btn) {
            if (selectAll) { hidden.delete(btn.dataset.name); btn.style.opacity = '1'; }
            else { hidden.add(btn.dataset.name); btn.style.opacity = '.35'; }
        });
        syncToggleLabel();
        calendar.refetchEvents();
    });
});
</script>
@endsection
