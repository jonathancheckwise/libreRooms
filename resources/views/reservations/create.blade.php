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
        @endphp
        <div class="form-group" id="pep-mode-group">
            <h3 class="form-group-title">{{ __('Booking mode') }}</h3>
            <div class="form-element">
                <label for="pep-date" class="form-element-title">{{ __('Date') }}</label>
                <input type="date" id="pep-date">
            </div>
            <div class="form-element" style="display:flex;flex-direction:column;gap:.5rem">
                <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="hourly"> {{ __('Hourly') }} <span class="text-gray-500 text-sm">({{ __('max') }} {{ $pepWindows['hourly_max'] }}h)</span></label>
                <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="morning"> {{ __('Morning half-day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['morning_start'] }}–{{ $pepWindows['morning_end'] }})</span></label>
                <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="afternoon"> {{ __('Afternoon half-day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['afternoon_start'] }}–{{ $pepWindows['afternoon_end'] }})</span></label>
                <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="evening"> {{ __('Evening half-day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['evening_start'] }}–{{ $pepWindows['evening_end'] }})</span></label>
                <label class="flex items-center gap-2"><input type="radio" name="pep_mode" value="full"> {{ __('Full day') }} <span class="text-gray-500 text-sm">({{ $pepWindows['full_start'] }}–{{ $pepWindows['full_end'] }})</span></label>
            </div>
            <p id="pep-mode-hint" class="text-sm text-gray-600 mt-1"></p>
        </div>
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

            function apply(){
                const mode = currentMode(); const date = dateEl().value;
                const s = firstStart(), e = firstEnd();
                if (!s || !e || !mode || !date) return;
                let start, end, lock = true;
                if (mode==='morning'){ start=W.morning_start; end=W.morning_end; }
                else if (mode==='afternoon'){ start=W.afternoon_start; end=W.afternoon_end; }
                else if (mode==='evening'){ start=W.evening_start; end=W.evening_end; }
                else if (mode==='full'){ start=W.full_start; end=W.full_end; }
                else { start=W.full_start; end=addMinutes(W.full_start,60); lock=false; }
                s.value = toDT(date,start); e.value = toDT(date,end);
                s.readOnly = lock; e.readOnly = lock;
                s.style.background = lock ? '#f3f4f6' : ''; e.style.background = lock ? '#f3f4f6' : '';
                fire(s); fire(e);
                const hintEl=document.getElementById('pep-mode-hint'); if(hintEl) hintEl.textContent = hintTxt[mode] || '';
            }

            // Bride la durée en mode "à l'heure" pour ne pas dépasser le max
            function clampHourly(target){
                if (currentMode()!=='hourly') return;
                const s = firstStart(), e = firstEnd();
                if (!s.value || !e.value) return;
                const sd = new Date(s.value), ed = new Date(e.value);
                const diffMin = (ed - sd)/60000;
                if (diffMin > W.hourly_max*60){
                    const capped = new Date(sd.getTime() + W.hourly_max*3600*1000);
                    const pad=n=>String(n).padStart(2,'0');
                    e.value = `${capped.getFullYear()}-${pad(capped.getMonth()+1)}-${pad(capped.getDate())}T${pad(capped.getHours())}:${pad(capped.getMinutes())}`;
                    const hintEl=document.getElementById('pep-mode-hint');
                    if(hintEl) hintEl.textContent = @json(__('Maximum :h h in hourly mode — pick a half-day or full-day for longer.', ['h' => $pepWindows['hourly_max']]));
                    fire(e);
                }
            }

            document.addEventListener('DOMContentLoaded', function(){
                document.querySelectorAll('input[name="pep_mode"]').forEach(r=>r.addEventListener('change', apply));
                const d = dateEl(); if (d) d.addEventListener('change', apply);
                const cont = document.getElementById('events-container');
                if (cont) cont.addEventListener('input', function(ev){
                    if (ev.target.matches('.event-end,.event-start')) clampHourly(ev.target);
                });
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
