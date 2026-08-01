@extends('layouts.app')

@section('title', $room->name)

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ $room->name }}</h1>
        @if(!$room->is_public)
            <span class="tooltip-container tooltip-right ml-2">
                <i class="fa-solid fa-user-lock text-xl"></i>
                <span class="tooltip-content">
                    {{ __('Private') }}
                </span>
            </span>
        @endif
        @can('update', $room->owner)
            <a href="{{ route('owners.edit', $room->owner) }}">
                <p class="mt-2 text-sm text-gray-600">{{ $room->owner->contact->display_name() }}</p>
            </a>
        @else
            <p class="mt-2 text-sm text-gray-600">{{ $room->owner->contact->display_name() }}</p>
        @endcan
        <nav class="page-submenu">
            <a href="{{ route('rooms.index') }}"
               class="page-submenu-item page-submenu-nav">
                {{ __('Back to rooms') }}
            </a>
            <span class="page-submenu-separator"></span>
            @if($room->active)
                <!-- There is a bypass for global_admins in can directives, we need to check that the room is active -->
                @can('reserve', $room)
                    @if($room->on_request)
                        {{-- Salle « sur demande » (La Pépite) : pas de réservation directe, on passe par le devis --}}
                        <a href="{{ route('special-requests.create', ['room' => $room->id]) }}" class="page-submenu-item page-submenu-action">
                            {{ __('Special request') }}
                        </a>
                    @else
                        <a href="{{ route('reservations.create', $room) }}" class="page-submenu-item page-submenu-action">
                            {{ __('Reserve this room') }}
                        </a>
                    @endif
                @endcan
            @endif
            @can('update', $room)
                <span class="page-submenu-separator"></span>
                <a href="{{ route('room-discounts.index', ['room_id'=>$room->id]) }}"
                   class="page-submenu-item page-submenu-nav">
                    {{ __('Discounts') }}
                </a>
                <a href="{{ route('room-options.index', ['room_id'=>$room->id]) }}"
                   class="page-submenu-item page-submenu-nav">
                    {{ __('Options') }}
                </a>
                <a href="{{ route('custom-fields.index', ['room_id'=>$room->id]) }}"
                   class="page-submenu-item page-submenu-nav">
                    {{ __('Custom fields') }}
                </a>
            @endcan
            @can('manageUnavailabilities', $room)
                @cannot('update', $room)
                    <span class="page-submenu-separator"></span>
                @endcannot
                <a href="{{ route('room-unavailabilities.index', ['room_id'=>$room->id]) }}"
                   class="page-submenu-item page-submenu-nav">
                    {{ __('Unavailabilities') }}
                </a>
            @endcan
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Images gallery -->
            @if($room->images->count() > 0)
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="relative">
                        @if($room->images->count() === 1)
                            <img
                                src="{{ $room->images->first()->url }}"
                                alt="{{ $room->name }}"
                                class="w-full h-80 object-cover cursor-pointer"
                                onclick="openImageModal('{{ $room->images->first()->url }}')"
                            >
                        @else
                            <div class="grid grid-cols-2 gap-1">
                                @foreach($room->images->take(4) as $index => $image)
                                    <div class="{{ $index === 0 && $room->images->count() >= 3 ? 'row-span-2' : '' }}">
                                        <img
                                            src="{{ $image->url }}"
                                            alt="{{ $room->name }}"
                                            class="w-full {{ $room->images->count() <= 2 || ($index === 0 && $room->images->count() >= 3) ? 'h-80' : 'h-40' }} object-cover cursor-pointer"
                                            onclick="openImageModal('{{ $image->url }}')"
                                        >
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Description -->
            @if($room->description)
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Description') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        {!! nl2br(e($room->description)) !!}
                    </div>
                </div>
            @endif

            <!-- Équipements (La Pépite) -->
            @if(!empty($room->equipments))
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Equipment') }}</h2>
                    <ul style="display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;list-style:none;padding:0;margin:0">
                        @foreach($room->equipments as $eq)
                            <li class="text-gray-700">✓ {{ \App\Models\Room::equipmentLabel($eq) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Disponibilité (La Pépite) -->
            @php
                $roomWindows = $room->availabilityWindows->sortBy(['weekday','start_time']);
                $weekdayNames = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')];
            @endphp
            @if(! $room->bookable || $room->booking_optional || $roomWindows->isNotEmpty())
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Availability') }}</h2>
                    @unless($room->bookable)
                        <p class="text-sm" style="color:#b45309">🔒 {{ __('This space is not bookable online. Its opening hours are shown for information only.') }}</p>
                    @endunless
                    @if($room->booking_optional)
                        <p class="text-sm text-gray-600">ℹ️ {{ __('Booking is optional but advised if you need a quiet, reserved space.') }}</p>
                    @endif
                    @if($roomWindows->isNotEmpty())
                        <ul class="mt-2 text-gray-700" style="list-style:none;padding:0;margin:0">
                            @foreach($roomWindows->groupBy('weekday') as $wd => $wins)
                                <li>{{ $weekdayNames[$wd] ?? $wd }} :
                                    {{ $wins->map(fn($w) => substr($w->start_time,0,5).'–'.substr($w->end_time,0,5))->implode(', ') }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <!-- Calendar -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Calendar') }}</h2>
                @can('manageReservations', $room)
                    <p class="text-sm text-green-600 font-medium mb-4">{{ __('Administrator mode - You see all information') }}</p>
                @else
                    <p class="text-sm text-gray-500 mb-4">
                        @switch($room->calendar_view_mode->value)
                            @case('full')
                                {{ __('You see all reservation information') }}
                                @break
                            @case('title')
                                {{ __('You see reservation titles') }}
                                @break
                            @case('slot')
                                {{ __('You only see occupied slots') }}
                                @break
                        @endswitch
                    </p>
                @endcan
                @include('rooms._calendar', ['room' => $room])
            </div>

            <!-- Admin actions -->
            @can('update', $room)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-yellow-800 mb-4">{{ __('Administration') }}</h3>
                    <div class="space-y-2">
                        <a href="{{ route('rooms.edit', [$room] + redirect_back_params()) }}" class="block w-full btn btn-secondary text-center">
                            {{ __('Edit room') }}
                        </a>
                        <a href="{{ route('rooms.users.index', [$room] + redirect_back_params()) }}" class="block w-full btn btn-secondary text-center">
                            {{ __('Manage users') }}
                        </a>
                    </div>
                </div>
            @endcan
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            @can('manageReservations', $room)
                <div class="flex items-center space-x-2 bg-white rounded-lg shadow p-6">
                    <a class="flex items-center space-x-2" href="{{ route('rooms.available', $room) }}">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Live availability') }}</h3>
                    <i class="link-primary fa-solid fa-door-open"></i>
                    </a>
                </div>
            @endcan
            <!-- Address and map -->
            @if($room->hasAddress())
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Address') }}</h3>
                    <p class="text-sm text-gray-700 mb-4">
                        {{ $room->formattedAddress() }}
                    </p>

                    @if($room->hasCoordinates())
                        <div id="map" class="h-48 rounded-lg mb-4 z-0"></div>
                        <a
                            href="https://www.openstreetmap.org/?mlat={{ $room->latitude }}&mlon={{ $room->longitude }}#map=19/{{ $room->latitude }}/{{ $room->longitude }}&layers=N"
                            target="_blank"
                            class="text-sm text-blue-600 hover:underline"
                        >
                            {{ __('Open in OpenStreetMap') }}
                        </a>
                    @endif
                </div>
            @endif

            <!-- Pricing -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Pricing') }}</h3>

                @if($room->price_mode->value === 'free')
                    <span class="px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-full">
                        {{ __('Free contribution') }}
                    </span>
                    <h4 class="text-sm font-medium text-gray-900 mb-2 mt-4">{{ __('Recommended rate:') }}</h4>
                @endif
                <div class="space-y-3">
                    @if($room->price_hourly)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('Hourly booking') }}</span>
                            <span class="text-gray-900 font-medium">{{ currency($room->price_hourly, $room->owner) }} / h</span>
                        </div>
                    @endif
                    @if($room->price_half_day)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ __('Half day') }}</span>
                            <span class="text-gray-900 font-medium">{{ currency($room->price_half_day, $room->owner) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Full day') }}</span>
                        <span class="text-gray-900 font-medium">{{ currency($room->price_full_day, $room->owner) }}</span>
                    </div>
                </div>

                @if($room->discounts->where('active', true)->count() > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('Available discounts') }}</h4>
                        <div class="space-y-2">
                            @foreach($room->discounts->where('active', true) as $discount)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">
                                        @can('manageDiscounts', $room)
                                            <a href="{{ route('room-discounts.edit', [$discount] + redirect_back_params()) }}">
                                                {{ $discount->name }}
                                            </a>
                                        @else
                                            {{ $discount->name }}
                                        @endcan
                                        @if($discount->limit_to_contact_type)
                                            <span class="text-gray-400 text-xs">({{ $discount->limit_to_contact_type->value === 'individual' ? __('Individual') : __('Org.') }})</span>
                                        @endif
                                    </span>
                                    <span class="text-green-600 font-medium">
                                        @if($discount->type->value === 'fixed')
                                            {{ currency(-$discount->value, $room->owner) }}
                                        @else
                                            -{{ $discount->value }}%
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Conditions -->
            @if($room->charter_mode->value !== 'none')
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Conditions') }}</h3>

                    @if($room->charter_mode->value === 'text')
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $room->charter_str }}</p>
                    @elseif($room->charter_mode->value === 'link')
                        <a
                            href="{{ $room->charter_str }}"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm text-gray-700 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            {{ __('View conditions') }}
                        </a>
                    @endif
                </div>
            @endif

            <!-- Owner info -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Owner') }}</h3>
                <div class="space-y-3">
                    @can('update', $room->owner)
                        <a href="{{ route('owners.edit', $room->owner) }}">
                            <p class="text-sm font-medium mb-2 text-gray-900">{{ $room->owner->contact->display_name() }}</p>
                        </a>
                    @else
                        <p class="text-sm font-medium mb-2 text-gray-900">{{ $room->owner->contact->display_name() }}</p>
                    @endcan

                    @if($room->owner->contact->email && !$room->owner->hide_email)
                        <a href="mailto:{{ $room->owner->contact->email }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <i class='fa-regular fa-envelope'></i>
                            {{ $room->owner->contact->email }}
                        </a>
                    @endif

                    @if($room->owner->contact->phone && !$room->owner->hide_phone)
                        <a href="tel:{{ $room->owner->contact->phone }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <x-icons.phone />
                            {{ $room->owner->contact->phone }}
                        </a>
                    @endif

                    @if($room->owner->website)
                        <a
                            href="{{ $room->owner->website }}"
                            target="_blank"
                            class="inline-flex items-center gap-2 mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm text-white font-medium transition"
                        >
                            <i class='fa-solid fa-link'></i>
                            {{ __('Visit website') }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Options -->
            @if($room->options->where('active', true)->count() > 0)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Options') }}</h3>
                    <div class="space-y-3">
                        @foreach($room->options->where('active', true) as $option)
                            @can('manageOptions', $room)
                                <a href="{{ route('room-options.edit', [$option] + redirect_back_params()) }}">
                            @endcan
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-900">{{ $option->name }}</span>
                                    <span class="text-sm text-gray-900 font-medium shrink-0 ml-2">{{ currency($option->price, $room->owner) }}</span>
                                </div>
                                @if($option->description)
                                    <p class="text-xs text-gray-500 mt-1">{{ $option->description }}</p>
                                @endif
                            </div>
                            @can('manageOptions', $room)
                                </a>
                            @endcan
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Booking rules -->
            @if($room->reservation_cutoff_days || $room->reservation_advance_limit || !$room->openedEveryday() || $room->day_start_time || $room->day_end_time)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Booking rules') }}</h3>
                    <dl class="space-y-2 text-sm">
                        @if($room->reservation_cutoff_days)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Minimum notice') }}</dt>
                                <dd class="text-gray-900">{{ $room->reservation_cutoff_days }} {{ __('days before') }}</dd>
                            </div>
                        @endif
                        @if($room->reservation_advance_limit)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Max. reservation') }}</dt>
                                <dd class="text-gray-900">{{ $room->reservation_advance_limit }} {{ __('days in advance') }}</dd>
                            </div>
                        @endif
                        @if(!$room->openedEveryday())
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Bookable days') }}</dt>
                                <dd class="text-gray-900 text-right">{{ $room->allowed_weekdays ? implode(', ', $room->allowedWeekdayNames()) : __('None.days') }}</dd>
                            </div>
                        @endif
                        @if($room->day_start_time || $room->day_end_time)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Bookable hours') }}</dt>
                                <dd class="text-gray-900">
                                    {{ $room->day_start_time ? substr($room->day_start_time, 0, 5) : '00:00' }}
                                    -
                                    {{ $room->day_end_time ? substr($room->day_end_time, 0, 5) : '24:00' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Image modal -->
<div id="image-modal" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4" onclick="closeImageModal()">
    <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white hover:text-gray-300">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
    <img id="modal-image" src="" alt="" class="max-w-full max-h-full object-contain" onclick="event.stopPropagation()">
</div>

@if($room->hasCoordinates())
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mapElement = document.getElementById('map');
            if (!mapElement) return;

            const lat = {{ $room->latitude }};
            const lng = {{ $room->longitude }};

            const map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);
            L.marker([lat, lng]).addTo(map);
        });
    </script>
@endif

<script>
    function openImageModal(imageUrl) {
        const modal = document.getElementById('image-modal');
        const modalImage = document.getElementById('modal-image');
        modalImage.src = imageUrl;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        const modal = document.getElementById('image-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    });
</script>
@endsection
