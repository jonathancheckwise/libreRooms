@extends('layouts.app')

@php
    $isCreate = request()->routeIs('reservations.create');
    $isEdit = request()->routeIs('reservations.edit');
    $isAdmin = auth()->user()?->can('manageReservations', $room);
@endphp

@section('title', ($isCreate ? __('New reservation') : __('Edit reservation')) . ' - ' . $room->name)

@section('page-script')
    @vite(['resources/js/reservations/reservation-form.js'])
    <script>
        window.translations = {
            empty: @json(__('Empty')),
            available: @json(__('Available')),
            occupied: @json(__('Occupied')),
            past: @json(__('Past')),
            too_close: @json(__('Too close')),
            too_far: @json(__('Too far')),
            invalid: @json(__('Invalid')),
            overlap: @json(__('Overlap')),
            non_bookable: @json(__('Non-bookable')),
            short_booking: @json(__('short booking')),
            full_day_booking: @json(__('full day booking')),
            morning_half_day: @json(__('Morning half-day')),
            afternoon_half_day: @json(__('Afternoon half-day')),
            evening_half_day: @json(__('Evening half-day')),
            hourly_booking: @json(__('Hourly booking')),
            to: @json(__('to')),
            error_no_dates: @json(__('Error: You must add at least one reservation date.')),
            error_invalid_dates: @json(__('Error: Some reservation dates are not valid:')),
            error_fix_dates: @json(__('Please fix these dates before submitting the form.')),
        };
        window.IsAdmin = @json($isAdmin);
        window.RoomConfig = @json($roomConfig);
        window.EnabledDiscounts = @json($enabledDiscounts);
        window.ResEvents = @json($events);
        @php
        $contactsArray = $contacts->map->only([
            'id',
            'type',
            'first_name',
            'last_name',
            'entity_name',
            'email',
            'invoice_email',
            'phone',
            'street',
            'zip',
            'city',
        ]);
        @endphp
        window.Contacts = @json($contactsArray);
    </script>
@endsection

@section('content')
        <div class="container-full-form">
            <div class="form-header">
                <h1 class="form-title">{{ $isCreate ? __('New reservation') : __('Edit reservation') }}</h1>
                <a href="{{ route('rooms.show', $room) }}"><p class="form-subtitle">{{ $room->name }}</p></a>
            </div>
            <form method="POST" class="reservation-form styled-form"
                  action="{{$isCreate ? route('reservations.store', [$room] + redirect_back_query()) : ($isEdit ? route('reservations.update', [$reservation] + redirect_back_query()) : "")}}">
        @if ($isEdit)
            @method('PUT')
        @endif
        @csrf
        @if($errors->any())
            <ul class="px-4 py-2 bg-red-100">
                @foreach($errors->all() as $error)
                    <li class="my-2 text-red-500">{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        {{-- 1. Contact --}}
        @include('reservations.partials.contact',['contacts'=>$contacts,'tenant'=>$reservation?->tenant])

        {{-- 1bis. Déclaration de statut (La Pépite, invités) : détermine le tarif, MàJ en direct --}}
        @guest
        <div class="form-group" id="pep-status-group">
            <h3 class="form-group-title">{{ __('Your status (sets your rate)') }}</h3>
            <div class="form-element">
                <label class="form-element-title">{{ __('Type of organization') }} *</label>
                <div style="display:flex;flex-direction:column;gap:.4rem">
                    <label class="flex items-center gap-2"><input type="radio" name="org_type" value="non_profit" @checked(old('org_type')==='non_profit')> {{ __('Non-profit organization') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" name="org_type" value="for_profit" @checked(old('org_type','for_profit')==='for_profit')> {{ __('For-profit organization') }}</label>
                </div>
                @error('org_type')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
            </div>
            <div class="form-element mt-2">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_pepite_member" value="0">
                    <input type="checkbox" name="is_pepite_member" value="1" id="pep_is_member" @checked(old('is_pepite_member'))>
                    <span>{{ __('I am a member of La Pépite') }}</span>
                </label>
                <small class="text-gray-600 block">{{ __('Members: 1 free hour per month and −10% (verified by the team).') }}</small>
            </div>
        </div>
        @endguest

        {{-- 2. Discounts --}}
        @include('reservations.partials.discounts',[
            'discounts' => $room->discounts->where('active', true),
            'enabledDiscounts' => $enabledDiscounts,
            'tenantType' => $reservation?->tenant->type,
            'owner' => $room->owner,
            ])

        {{-- 3. Event infos --}}
        @include('reservations.partials.event-info',[
            'title' => $reservation?->title,
            'description' => $reservation?->description,
            ])

        {{-- 4. Custom fields --}}
        @include('reservations.partials.custom-fields',['customFields' => $room->customFields->where('active', true),
                                                            'customFieldValues' => $reservation?->customFieldValues])

            {{-- 5. Calendar --}}
        @if($room->embed_calendar_mode === App\Enums\EmbedCalendarModes::ADMIN_ONLY && $isAdmin ||
            $room->embed_calendar_mode === App\Enums\EmbedCalendarModes::ENABLED)
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Availability calendar') }}</h3>
                <div class="form-element">
                    <div class="form-field">
                        @include('rooms._calendar', ['room' => $room])
                    </div>
                </div>
            </div>
        @endif

        {{-- 6.0 Mode de réservation (La Pépite) : pilote les champs de créneau ci-dessous --}}
        @php
            $pep = app(\App\Models\SystemSettings::class);
            $pepWindows = [
                'hourly_max' => (int) $pep->hourly_max_hours,
                'morning_start' => substr($pep->half_day_morning_start, 0, 5),
                'morning_end' => substr($pep->half_day_morning_end, 0, 5),
                'afternoon_start' => substr($pep->half_day_afternoon_start, 0, 5),
                'afternoon_end' => substr($pep->half_day_afternoon_end, 0, 5),
                'evening_start' => substr($pep->half_day_evening_start, 0, 5),
                'evening_end' => substr($pep->half_day_evening_end, 0, 5),
                'full_start' => substr($pep->full_day_start, 0, 5),
                'full_end' => substr($pep->full_day_end, 0, 5),
            ];
            // Salle à fenêtres de disponibilité (La Pépite) : réservation « au jour ».
            // La Pépite : les espaces communautaires (La Douce…) utilisent le
            // MÊME sélecteur de mode que les autres salles (demi-journées / à
            // l'heure) ; leurs fenêtres de disponibilité par jour bloquent
            // automatiquement les créneaux hors horaires (via isNonBookable +
            // serveur). Donc plus de mode « jour seul ».
            $isWindowed = false;
            // Aujourd'hui dans le fuseau de la salle : borne basse du calendrier.
            $pepToday = \Carbon\Carbon::now($room->timezone ?? config('app.timezone'))->toDateString();
            $pepDows = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];
        @endphp
        <div class="form-group" id="pep-mode-group" data-windowed="{{ $isWindowed ? '1' : '0' }}">
            <h3 class="form-group-title">{{ __('Booking mode') }}</h3>
            <div class="form-element">
                <label for="pep-date" class="form-element-title">{{ __('Date') }} *</label>
                <input type="date" id="pep-date" min="{{ $pepToday }}">
                {{-- Calendrier toujours déplié : sans date choisie, aucun prix ne peut
                     s'afficher, et rien ne le signalait. --}}
                <div class="pep-cal" id="pep-cal" data-today="{{ $pepToday }}">
                    <div class="pep-cal-head">
                        <button type="button" class="pep-cal-nav" id="pep-cal-prev" aria-label="{{ __('Previous month') }}">‹</button>
                        <span class="pep-cal-title" id="pep-cal-title"></span>
                        <button type="button" class="pep-cal-nav" id="pep-cal-next" aria-label="{{ __('Next month') }}">›</button>
                    </div>
                    <div class="pep-cal-dows" id="pep-cal-dows"></div>
                    <div class="pep-cal-grid" id="pep-cal-grid"></div>
                    <div class="pep-cal-legend">
                        <span><i class="pep-dot pep-dot-sel"></i>{{ __('Selected date') }}</span>
                        <span><i class="pep-dot pep-dot-busy"></i>{{ __('Already booked') }}</span>
                    </div>
                </div>
                <p class="pep-warn" id="pep-date-warning">{{ __('Start by choosing a date in the calendar.') }}</p>
            </div>
            @if($isWindowed)
                {{-- On choisit uniquement le jour : le créneau = la fenêtre de dispo de ce jour --}}
                <div class="form-element" id="pep-window-choice-wrap" style="display:none">
                    <label class="form-element-title">{{ __('Time slot') }}</label>
                    <div id="pep-window-choice" style="display:flex;flex-direction:column;gap:.4rem"></div>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ __('Pick a day: the booking covers this room\'s available slot for that day. For several days, make one booking per day.') }}</p>
            @else
                <div class="form-element" style="display:flex;flex-direction:column;gap:.5rem">
                    <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="hourly"> {{ __('Hourly') }} <span class="text-gray-500 text-sm">({{ __('max') }} {{ $pepWindows['hourly_max'] }}h)</span></label>
                    <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="morning"> {{ __('Morning half-day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['morning_start'] }}–{{ $pepWindows['morning_end'] }})</span></label>
                    <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="afternoon"> {{ __('Afternoon half-day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['afternoon_start'] }}–{{ $pepWindows['afternoon_end'] }})</span></label>
                    <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="evening"> {{ __('Evening half-day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['evening_start'] }}–{{ $pepWindows['evening_end'] }})</span></label>
                    <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="full"> {{ __('Full day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['full_start'] }}–{{ $pepWindows['full_end'] }})</span></label>
                </div>
                {{-- Mode « à l'heure » : heure de début + durée en heures (pas d'édition à la minute) --}}
                <div class="form-element" id="pep-hourly-panel" style="display:none;flex-direction:row;gap:1rem;flex-wrap:wrap;align-items:flex-end">
                    <div class="form-field">
                        <label for="pep-hour-start" class="form-element-title">{{ __('Start time') }}</label>
                        <select id="pep-hour-start"></select>
                    </div>
                    <div class="form-field">
                        <label for="pep-hour-duration" class="form-element-title">{{ __('Duration') }}</label>
                        <select id="pep-hour-duration"></select>
                    </div>
                </div>
            @endif
            {{-- Heure offerte : membre OU responsable, disponible pour TOUS les modes (à l'heure et forfaits). --}}
            @auth
                @if($isAdmin || auth()->user()->is_pepite_member)
                    <div class="form-element" id="pep-free-hour-field" style="margin-top:.5rem">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="use_free_hour" value="0">
                            <input type="checkbox" name="use_free_hour" value="1" id="pep_use_free_hour">
                            <span>{{ __('Use my free hour (1 h/month)') }}</span>
                        </label>
                        <small id="pep-free-hour-note" class="text-gray-600 block"></small>
                    </div>
                @endif
            @endauth
            <p id="pep-mode-hint" class="text-sm text-gray-600 mt-1"></p>
        </div>
        <style>
            /* Mini-calendrier de choix du jour (La Pépite). Toujours déplié.
               Sélecteurs d'identifiant : la feuille de style de l'application
               impose sinon ses propres couleurs et largeurs aux <button>. */
            #pep-cal { margin-top:.6rem; max-width:22rem; border:1px solid #e5e7eb; border-radius:.6rem; padding:.6rem; background:#fff; }
            #pep-cal .pep-cal-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:.4rem; }
            #pep-cal .pep-cal-title { font-weight:600; text-transform:capitalize; font-size:.95rem; color:#111827; }
            #pep-cal .pep-cal-nav { border:1px solid #e5e7eb; background:#fff; color:#374151; border-radius:.4rem; width:1.9rem; height:1.9rem; min-width:0; padding:0; line-height:1; cursor:pointer; font-size:1.05rem; }
            #pep-cal .pep-cal-nav:hover:not(:disabled) { background:#f9fafb; }
            #pep-cal .pep-cal-nav:disabled { opacity:.35; cursor:default; }
            #pep-cal .pep-cal-dows, #pep-cal .pep-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
            #pep-cal .pep-cal-dows span { text-align:center; font-size:.68rem; color:#6b7280; padding:.2rem 0; }
            #pep-cal .pep-day { position:relative; height:2.4rem; width:100%; min-width:0; border:none; background:none; border-radius:50%; cursor:pointer; font-size:.85rem; font-weight:400; color:#111827; padding:0; }
            #pep-cal .pep-day:hover:not(:disabled) { background:#f3f4f6; }
            #pep-cal .pep-day:disabled { color:#d1d5db; cursor:default; background:none; }
            #pep-cal .pep-day.is-empty { visibility:hidden; }
            #pep-cal .pep-day.is-today { font-weight:700; }
            #pep-cal .pep-day.is-selected { background:#F2A83C; color:#2D2318; font-weight:700; }
            #pep-cal .pep-day .pep-day-dot { position:absolute; left:50%; transform:translateX(-50%); bottom:.3rem; width:.3rem; height:.3rem; border-radius:50%; background:#C2410C; }
            #pep-cal .pep-day.is-selected .pep-day-dot { background:#2D2318; }
            #pep-cal .pep-cal-legend { display:flex; gap:1rem; flex-wrap:wrap; margin-top:.5rem; font-size:.7rem; color:#6b7280; }
            #pep-cal .pep-cal-legend span { display:inline-flex; align-items:center; gap:.3rem; }
            #pep-cal .pep-dot { width:.5rem; height:.5rem; border-radius:50%; display:inline-block; }
            #pep-cal .pep-dot-sel { background:#F2A83C; }
            #pep-cal .pep-dot-busy { background:#C2410C; }
            #pep-date-warning { margin-top:.5rem; font-size:.85rem; color:#B45309; }
            #pep-date-warning.is-hidden { display:none; }
        </style>
        <script>
        (function () {
            const W = @json($pepWindows);
            const hintTxt = {
                hourly: @json(__('Choose your start and end times below (max :h h).', ['h' => $pepWindows['hourly_max']])),
                morning: @json(__('Times are locked to the morning window.')),
                afternoon: @json(__('Times are locked to the afternoon window.')),
                evening: @json(__('Times are locked to the evening window.')),
                full: @json(__('Times are locked to the full-day window.')),
            };
            function hm(t){ const [h,m]=t.split(':').map(Number); return h*60+m; }
            function toDT(d,t){ return d + 'T' + t; }
            function addMinutes(t,mins){ let x=hm(t)+mins; x=Math.max(0,Math.min(24*60-1,x)); const h=String(Math.floor(x/60)).padStart(2,'0'); const m=String(x%60).padStart(2,'0'); return h+':'+m; }
            const dateEl = () => document.getElementById('pep-date');
            const firstStart = () => document.querySelector('.event-start');
            const firstEnd = () => document.querySelector('.event-end');
            function currentMode(){ const r=document.querySelector('input[name="pep_mode"]:checked'); return r?r.value:null; }

            function fire(el){ el.dispatchEvent(new Event('input', {bubbles:true})); }

            // La Pépite : réservation par CRÉNEAU. On masque les champs date/heure
            // bruts (pilotés par le sélecteur ci-dessus), le bouton « Ajouter une
            // date » (une réservation = un créneau, un jour) et la suppression.
            function hidePepRaw() {
                document.querySelectorAll('#events-container .event-start, #events-container .event-end').forEach((inp) => {
                    const ff = inp.closest('.form-field'); if (ff) ff.style.display = 'none';
                    inp.required = false; // masqué → la validation JS (initFormValidation) prend le relais
                });
                document.querySelectorAll('#events-container .event-remove').forEach((b) => { b.style.display = 'none'; });
                const add = document.getElementById('add-event'); if (add) add.style.display = 'none';
            }
            document.addEventListener('DOMContentLoaded', hidePepRaw);

            // Salle à fenêtres (La Pépite) : on choisit le jour, le créneau = la fenêtre.
            const WINDOWED = @json($isWindowed);
            const AVAIL = (window.RoomConfig && window.RoomConfig.settings.availability_windows) || [];
            if (WINDOWED) {
                const choiceWrap = () => document.getElementById('pep-window-choice-wrap');
                const choiceBox = () => document.getElementById('pep-window-choice');
                const hintEl = () => document.getElementById('pep-mode-hint');
                const isoWeekday = (d) => { const g = new Date(d + 'T00:00:00').getDay(); return g === 0 ? 7 : g; };
                function setWindow(date, w) {
                    const s = firstStart(), e = firstEnd();
                    if (!s || !e) return;
                    s.value = date + 'T' + w.start; e.value = date + 'T' + w.end;
                    s.readOnly = true; e.readOnly = true; s.style.background = '#f3f4f6'; e.style.background = '#f3f4f6';
                    fire(s); fire(e);
                    hintEl().textContent = @json(__('Slot booked:')) + ' ' + w.start + '–' + w.end;
                }
                function applyWindowed() {
                    const date = dateEl().value, s = firstStart(), e = firstEnd();
                    if (!s || !e || !date) return;
                    const day = AVAIL.filter(w => Number(w.weekday) === isoWeekday(date));
                    if (!day.length) {
                        choiceWrap().style.display = 'none'; choiceBox().innerHTML = '';
                        s.value = ''; e.value = ''; fire(s); fire(e);
                        hintEl().textContent = @json(__('No slot available on this day for this room.'));
                        return;
                    }
                    if (day.length === 1) {
                        choiceWrap().style.display = 'none'; choiceBox().innerHTML = '';
                        setWindow(date, day[0]);
                    } else {
                        choiceWrap().style.display = '';
                        choiceBox().innerHTML = day.map((w, i) => `<label class="flex items-center gap-2"><input type="radio" name="pep_win" value="${i}" ${i === 0 ? 'checked' : ''}> ${w.start}–${w.end}</label>`).join('');
                        choiceBox().querySelectorAll('input[name="pep_win"]').forEach(r => r.addEventListener('change', () => {
                            setWindow(date, day[Number(choiceBox().querySelector('input[name="pep_win"]:checked').value)]);
                        }));
                        setWindow(date, day[0]);
                    }
                }
                document.addEventListener('DOMContentLoaded', function () {
                    // Les déclarations de fonction sont remontées : pepCalInit reste
                    // disponible malgré le return ci-dessous.
                    pepCalInit();
                    const d = dateEl(); if (d) d.addEventListener('change', applyWindowed);
                    const addBtn = document.getElementById('add-event'); if (addBtn) addBtn.style.display = 'none';
                });
                return;
            }


            /* ---------------------------------------------------------------
               Mini-calendrier : jours passés non cliquables, pastille sur les
               jours déjà réservés, cercle plein sur la sélection.
               --------------------------------------------------------------- */
            const PEP_DOWS = @json($pepDows);
            const PEP_LOCALE = @json(str_replace('_', '-', app()->getLocale()));

            function pepCalInit() {
                const box = document.getElementById('pep-cal');
                const grid = document.getElementById('pep-cal-grid');
                const dows = document.getElementById('pep-cal-dows');
                const title = document.getElementById('pep-cal-title');
                const prev = document.getElementById('pep-cal-prev');
                const next = document.getElementById('pep-cal-next');
                const input = dateEl();
                if (!box || !grid || !input) return;

                const today = box.dataset.today;                 // AAAA-MM-JJ
                const busy = new Set();                          // jours déjà réservés
                let view = new Date((input.value || today) + 'T12:00:00');
                view.setDate(1);

                dows.innerHTML = PEP_DOWS.map(d => '<span>' + d + '</span>').join('');

                const iso = (d) => d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');

                function render() {
                    title.textContent = view.toLocaleDateString(PEP_LOCALE, { month: 'long', year: 'numeric' });
                    // On ne recule pas avant le mois courant.
                    const firstOfThisMonth = today.slice(0, 7);
                    prev.disabled = iso(view).slice(0, 7) <= firstOfThisMonth;

                    const start = (new Date(view).getDay() + 6) % 7;   // lundi = 0
                    const nbDays = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
                    let html = '';
                    for (let i = 0; i < start; i++) { html += '<button type="button" class="pep-day is-empty" disabled></button>'; }
                    for (let d = 1; d <= nbDays; d++) {
                        const cell = new Date(view.getFullYear(), view.getMonth(), d);
                        const key = iso(cell);
                        const past = key < today;
                        const cls = ['pep-day'];
                        if (past) cls.push('is-past');
                        if (key === today) cls.push('is-today');
                        if (key === input.value) cls.push('is-selected');
                        html += '<button type="button" class="' + cls.join(' ') + '" data-date="' + key + '"'
                              + (past ? ' disabled' : '') + '>' + d
                              + (busy.has(key) ? '<span class="pep-day-dot"></span>' : '')
                              + '</button>';
                    }
                    grid.innerHTML = html;
                    grid.querySelectorAll('.pep-day[data-date]:not([disabled])').forEach(function (b) {
                        b.addEventListener('click', function () {
                            input.value = b.dataset.date;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            render();
                        });
                    });
                }

                prev.addEventListener('click', function () { view.setMonth(view.getMonth() - 1); render(); });
                next.addEventListener('click', function () { view.setMonth(view.getMonth() + 1); render(); });
                input.addEventListener('change', function () {
                    // Saisie clavier d'une date passée : on la refuse.
                    if (input.value && input.value < today) { input.value = ''; }
                    if (input.value) { view = new Date(input.value + 'T12:00:00'); view.setDate(1); }
                    render();
                    pepUpdateDateWarning();
                });

                render();
                pepUpdateDateWarning();

                // Jours déjà réservés : on interroge la même source que le calendrier
                // de disponibilité, indépendamment du module compilé.
                const route = window.RoomConfig && window.RoomConfig.settings && window.RoomConfig.settings.availability_route;
                if (route) {
                    fetch(route).then(r => r.json()).then(function (data) {
                        const events = (data && data.events) || data || [];
                        events.forEach(function (ev) {
                            if (!ev || !ev.start) return;
                            const from = new Date(ev.start), to = new Date(ev.end || ev.start);
                            for (let c = new Date(from); c <= to; c.setDate(c.getDate() + 1)) {
                                busy.add(iso(c));
                                if (c.getTime() === to.getTime()) break;
                            }
                        });
                        render();
                    }).catch(function () { /* pastilles indisponibles : le calendrier reste utilisable */ });
                }
            }

            function pepUpdateDateWarning() {
                const w = document.getElementById('pep-date-warning');
                if (!w) return;
                const d = dateEl();
                w.classList.toggle('is-hidden', !!(d && d.value));
            }

            function apply(){
                const mode = currentMode(); const date = dateEl().value;
                const s = firstStart(), e = firstEnd();
                const hp = document.getElementById('pep-hourly-panel');
                if (hp) hp.style.display = (mode === 'hourly') ? 'flex' : 'none';
                pepUpdateDateWarning();
                if (!date) {
                    // Sans date, aucun créneau ni prix ne peut être calculé : on le dit,
                    // au lieu de laisser l'utilisateur devant un prix qui ne vient pas.
                    if (s && e) { s.value = ''; e.value = ''; fire(s); fire(e); }
                    const h = document.getElementById('pep-mode-hint');
                    if (h) h.textContent = @json(__('Start by choosing a date in the calendar.'));
                    return;
                }
                if (!s || !e || !mode) return;
                let start, end;
                if (mode==='morning'){ start=W.morning_start; end=W.morning_end; }
                else if (mode==='afternoon'){ start=W.afternoon_start; end=W.afternoon_end; }
                else if (mode==='evening'){ start=W.evening_start; end=W.evening_end; }
                else if (mode==='full'){ start=W.full_start; end=W.full_end; }
                else { // à l'heure : heure de début + durée (en heures)
                    const hs = document.getElementById('pep-hour-start');
                    const hd = document.getElementById('pep-hour-duration');
                    start = (hs && hs.value) ? hs.value : W.full_start;
                    const dur = (hd && hd.value) ? parseInt(hd.value, 10) : 1;
                    end = addMinutes(start, dur * 60);
                }
                s.value = toDT(date,start); e.value = toDT(date,end);
                s.readOnly = true; e.readOnly = true;
                fire(s); fire(e);
                const hintEl=document.getElementById('pep-mode-hint'); if(hintEl) hintEl.textContent = hintTxt[mode] || '';
                updateFreeHourAvailability();
            }

            // Heure offerte : dispo selon le MOIS DE LA RÉSERVATION (pas le mois
            // courant). Si déjà utilisée ce mois-là → case désactivée.
            function updateFreeHourAvailability(){
                const cb = document.getElementById('pep_use_free_hour');
                if (!cb) return;
                const note = document.getElementById('pep-free-hour-note');
                const date = dateEl().value;
                const used = (window.RoomConfig.settings.free_hour_used_months) || [];
                if (!date) { cb.disabled = true; if (note) note.textContent = ''; }
                else if (used.includes(date.slice(0,7))) {
                    cb.checked = false; cb.disabled = true;
                    if (note) note.textContent = @json(__('Free hour already used for this month.'));
                } else {
                    cb.disabled = false;
                    if (note) note.textContent = @json(__('1 free hour available this month.'));
                }
                cb.dispatchEvent(new Event('change', {bubbles:true}));
            }

            // Remplit « heure de début » (plage horaire de la salle) et « durée »
            // (1 h jusqu'au max), et recalcule à chaque changement.
            function initHourly(){
                const st = window.RoomConfig.settings;
                const startH = parseInt((st.day_start_time || '08:00').split(':')[0], 10);
                const endH = parseInt((st.day_end_time || '22:00').split(':')[0], 10);
                const startSel = document.getElementById('pep-hour-start');
                const durSel = document.getElementById('pep-hour-duration');
                if (!startSel || !durSel) return;
                startSel.innerHTML = ''; durSel.innerHTML = '';
                for (let h = startH; h < endH; h++) { startSel.insertAdjacentHTML('beforeend', `<option value="${String(h).padStart(2,'0')}:00">${h}h00</option>`); }
                for (let d = 1; d <= W.hourly_max; d++) { durSel.insertAdjacentHTML('beforeend', `<option value="${d}">${d} h</option>`); }
                startSel.addEventListener('change', apply);
                durSel.addEventListener('change', apply);
            }

            document.addEventListener('DOMContentLoaded', function(){
                initHourly();
                pepCalInit();
                document.querySelectorAll('input[name="pep_mode"]').forEach(r=>r.addEventListener('change', apply));
                const d = dateEl(); if (d) d.addEventListener('change', apply);
            });
        })();
        </script>

        {{-- 6. Events --}}
        @include('reservations.partials.events', [
            'availableOptions' => $room->options->where('active',true),
            'events' => $events,
            'owner' => $room->owner,
            'allowed_weekdays' => $room->allowed_weekdays,
            'day_start_time' => $room->day_start_time,
            'day_end_time' => $room->day_end_time,
            ])

        {{-- 7. Donation --}}
        @if ($room->use_donation && !($room->price_mode == App\Enums\PriceModes::FREE))
            @include('reservations.partials.donation',[
            'reservationDonation' => $reservation?->donation,
            'currency' => $roomConfig['settings']['currency'],
            ])
        @endif

        {{-- 8. Special discount --}}
        @if ($room->use_special_discount && $isAdmin && !($room->price_mode == App\Enums\PriceModes::FREE))
            @include('reservations.partials.special-discount',[
            'specialDiscount' => $reservation?->special_discount,
            'currency' => $roomConfig['settings']['currency'],
            ])
        @endif

        {{-- 9. Price summary --}}
        @include('reservations.partials.price-summary', [
            'discounts' => $room->discounts->where('active', true),
            'reservationDiscounts' => $reservation?->discountIds() ?? [],
            'specialDiscount' => $reservation?->special_discount ?? 0,
            'donation' => $reservation?->donation ?? 0,
            'useFreePrice' => $room->price_mode == App\Enums\PriceModes::FREE,
            'owner' => $room->owner,
            ])

        {{-- 10. Free price --}}
        @if ($room->price_mode == App\Enums\PriceModes::FREE)
            @include('reservations.partials.free-price',[
            'freePrice' => $reservation?->donation,
            'currency' => $roomConfig['settings']['currency'],
            ])
        @endif

        {{-- 11. Charter --}}
        @include('reservations.partials.charter',[
        'charter_mode' => $room->charter_mode,
        'charter_str' => $room->charter_str,
        'isCreate' => $isCreate,
        ])

        {{-- 12. Custom message --}}
        @if ($isAdmin)
            @include('reservations.partials.custom-message', ['customMessage' => $reservation?->custom_message])
        @endif

        {{-- Compte obligatoire pour finaliser (La Pépite, invités) --}}
        @guest
        <div class="form-group" id="pep-account-group">
            <h3 class="form-group-title">{{ __('Finalise: create your account') }}</h3>
            <p class="text-sm text-gray-600 mb-2">
                {{ __('A quick account lets you and the team follow your booking and billing. The email you entered above is used.') }}
                {{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a>.
            </p>
            <div class="form-element-row">
                <div class="form-field">
                    <label for="password" class="form-element-title">{{ __('Password') }} *</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    @error('password')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
                </div>
                <div class="form-field">
                    <label for="password_confirmation" class="form-element-title">{{ __('Confirm password') }} *</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
        </div>
        @endguest

        {{-- Conditions générales : acceptation obligatoire avant de réserver (La Pépite).
             Case JAMAIS pré-cochée, et lien consultable AVANT de cocher : une case
             déjà remplie ne vaut pas acceptation. L'horodatage, la version et l'IP
             sont enregistrés sur la réservation (voir ReservationService). --}}
        @if($isCreate)
        @php
            $pepTerms = app(\App\Models\SystemSettings::class);
            $pepTermsUrl = $pepTerms->terms_url;
            $pepTermsVersion = $pepTerms->terms_version;
        @endphp
        <div class="form-group" id="pep-terms-group" @if($pepTermsUrl) data-terms-url="{{ $pepTermsUrl }}" @endif>
            <label class="flex items-start gap-2" style="cursor:pointer">
                <input type="checkbox" name="accept_terms" id="pep-accept-terms" value="1" required @checked(old('accept_terms')) style="margin-top:.25rem">
                <span>
                    @if($pepTermsUrl)
                        {!! __('I have read and accept the :link of La Pépite.', ['link' => '<a id="pep-terms-link" href="'.e($pepTermsUrl).'" target="_blank" rel="noopener">'.__('general terms of booking and use of the spaces').'</a>']) !!}
                    @else
                        {{ __('I have read and accept the general terms of booking and use of the spaces of La Pépite.') }}
                    @endif
                    *
                </span>
            </label>
            <small class="text-gray-600 block mt-1">
                {{ __('Available at any time on the website.') }}
                @if($pepTermsVersion) {{ __('Version in force:') }} {{ $pepTermsVersion }} @endif
            </small>
            <p id="pep-terms-hint" class="text-sm mt-1" style="display:none;color:#B45309"></p>
            @error('accept_terms')<span class="text-red-600 text-sm block mt-1">{{ $message }}</span>@enderror
        </div>
        <script>
        // On ne peut pas cocher sans avoir ouvert les CG. Le premier clic sur la
        // case les ouvre dans un nouvel onglet au lieu de cocher ; le clic suivant
        // coche. Cliquer soi-même sur le lien lève aussi la condition.
        (function () {
            var groupe = document.getElementById('pep-terms-group');
            if (!groupe) return;
            var url = groupe.dataset.termsUrl;
            if (!url) return;                       // sans lien publié, pas de blocage
            var caseCG = document.getElementById('pep-accept-terms');
            var lien = document.getElementById('pep-terms-link');
            var indice = document.getElementById('pep-terms-hint');
            var ouvertes = caseCG.checked;          // retour d'erreur : déjà consultées

            function dire(texte) {
                indice.textContent = texte;
                indice.style.display = texte ? '' : 'none';
            }
            if (lien) {
                lien.addEventListener('click', function () {
                    ouvertes = true;
                    dire('');
                });
            }
            caseCG.addEventListener('click', function (e) {
                // Au moment du clic, « checked » porte déjà le nouvel état :
                // false quand on décoche, ce qui reste toujours permis.
                if (!caseCG.checked) { dire(''); return; }
                if (ouvertes) { return; }
                e.preventDefault();                 // la case ne se coche pas encore
                window.open(url, '_blank', 'noopener');
                ouvertes = true;
                dire(@json(__('The general terms have just opened in a new tab. Read them, then tick the box.')));
            });
        })();
        </script>
        @endif

        <div class="btn-group">
            <a class="btn btn-secondary" href="{{ url()->previous() }}">{{ __('Cancel') }}</a>
        @if ($isCreate)
            <button type="submit" class="btn btn-primary" name="action" value="prepare">{{ __('Send request') }}</button>
        @elseif ($isEdit)
            <button type="submit" class="btn btn-primary" name="action" value="prepare">{{ __('Update request') }}</button>
        @endif
        @if ($isAdmin && $isCreate)
            <button type="submit" class="btn btn-confirm" name="action" value="confirm">{{ __('Confirm request directly') }}</button>
        @elseif ($isAdmin && $isEdit)
            <button type="submit" class="btn btn-confirm" name="action" value="confirm">{{ __('Confirm request') }}</button>
        @endif
        @if($isEdit && $reservation->status !== App\Enums\ReservationStatus::CANCELLED)
            <button type="button" onclick="openCancelModal()" class="btn btn-delete">
                {{ __('Cancel request') }}
            </button>
        @endif
        </div>
    </form>
    </div>

    <!-- Modal de chargement -->
    <div id="loader-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 shadow-xl flex flex-col items-center gap-4">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
            <p class="text-gray-700 font-medium">{{ __('Processing...') }}</p>
        </div>
    </div>

    @if($isEdit && $reservation->status !== App\Enums\ReservationStatus::CANCELLED)
        <!-- Modal d'annulation -->
        <div id="cancel-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('Cancel reservation') }}</h3>
                <form id="cancel-form" method="POST" action="{{ route('reservations.cancel', [$reservation] + redirect_back_query()) }}">
                    @csrf
                    <div class="mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="send_email" value="1" checked
                                   id="cancel-send-email"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">{{ __('Send cancellation email') }}</span>
                        </label>
                    </div>
                    <div class="mb-4">
                        <label for="cancel-reason" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Cancellation reason (optional)') }}
                        </label>
                        <textarea name="cancellation_reason"
                                  id="cancel-reason"
                                  class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                  rows="3"
                                  placeholder="{{ __('Explain the reason for the cancellation...') }}"></textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('This reason will be included in the cancellation email if the box above is checked.') }}</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button"
                                onclick="closeCancelModal()"
                                class="btn btn-secondary">
                            {{ __('Back') }}
                        </button>
                        <button type="submit" class="btn btn-delete">
                            {{ __('Confirm cancellation') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openCancelModal() {
                document.getElementById('cancel-modal').classList.remove('hidden');
                document.getElementById('cancel-reason').value = '';
                document.getElementById('cancel-send-email').checked = true;
                updateCancelReasonState();
            }

            function closeCancelModal() {
                document.getElementById('cancel-modal').classList.add('hidden');
            }

            function updateCancelReasonState() {
                const checkbox = document.getElementById('cancel-send-email');
                const textarea = document.getElementById('cancel-reason');
                textarea.disabled = !checkbox.checked;
                textarea.classList.toggle('bg-gray-100', !checkbox.checked);
            }

            // Initialize event listener for checkbox
            document.getElementById('cancel-send-email').addEventListener('change', updateCancelReasonState);

            // Show loader when cancel form is submitted
            document.getElementById('cancel-form').addEventListener('submit', function() {
                closeCancelModal();
                if (window.showLoaderModal) {
                    window.showLoaderModal();
                }
            });

            // Close modal on backdrop click
            document.getElementById('cancel-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCancelModal();
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeCancelModal();
                }
            });
        </script>
    @endif
@endsection
