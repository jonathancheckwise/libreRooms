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
            <div id="planning-qr" data-url="{{ route('planning.index') }}" aria-label="QR"></div>
            <div style="font-size:.85rem;line-height:1.3">
                <strong>{{ __('Scan to view') }}</strong><br>
                <span style="color:#6b7280">{{ __('all rooms availability') }}</span><br>
                <a href="{{ route('planning.posters') }}" style="color:#2563eb">{{ __('Print door posters') }}</a>
            </div>
        </div>
    </div>

    {{-- Grille couleur : état du jour par salle --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem;margin-bottom:2rem">
        @foreach($cards as $card)
            @php $room = $card['room']; $c = $statusColor[$card['status']]; @endphp
            <a href="{{ ($demo ?? false) ? '#' : route('rooms.show', $room) }}"
               class="planning-card" data-slug="{{ $room->slug }}"
               style="display:block;background:#fff;border:1px solid #e5e7eb;border-left:6px solid {{ $c }};border-radius:10px;padding:.85rem 1rem;text-decoration:none;color:inherit">
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
            </a>
        @endforeach
    </div>

    {{-- Calendrier toutes salles : Jour / Semaine / Mois --}}
    <h2 style="font-size:1.15rem;font-weight:700;margin:0 0 .5rem">{{ __('Calendar') }}</h2>

    {{-- Filtres par salle (légende cliquable) --}}
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
</div>

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

    // --- Calendrier toutes salles ---
    var el = document.getElementById('planning-calendar');
    if (!el || !window.FullCalendar) return;

    var hidden = new Set(); // salles masquées par la légende
    var DEMO_EVENTS = @js($demoEvents ?? null); // aperçu de démo : events factices
    var calendar = new FullCalendar.Calendar(el, {
        initialView: 'timeGridWeek',
        locale: @js(str_replace('_', '-', app()->getLocale())),
        firstDay: 1,
        allDaySlot: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',
        nowIndicator: true,
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
        },
        buttonText: {
            today: @js(__('Today')),
            month: @js(__('Month')),
            week: @js(__('Week')),
            day: @js(__('Day')),
        },
        events: function (info, success, failure) {
            var keep = function (ev) { return !hidden.has(ev.extendedProps && ev.extendedProps.room); };
            if (DEMO_EVENTS) { success(DEMO_EVENTS.filter(keep)); return; }
            fetch('{{ route('planning.events') }}')
                .then(function (r) { return r.json(); })
                .then(function (data) { success((data.events || []).filter(keep)); })
                .catch(failure);
        },
        eventDisplay: 'block',
    });
    calendar.render();

    // Légende cliquable = filtre par salle
    document.querySelectorAll('.pl-legend').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = btn.dataset.name;
            if (hidden.has(name)) { hidden.delete(name); btn.style.opacity = '1'; }
            else { hidden.add(name); btn.style.opacity = '.35'; }
            calendar.refetchEvents();
        });
    });
});
</script>
@endsection
