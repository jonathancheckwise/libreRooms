@extends('layouts.app')

@section('title', isset($room) ? __('Edit room') : __('New room'))

@section('page-script')
    @vite(['resources/js/rooms/room-form.js', 'resources/js/rooms/geocoding.js'])
    <script>
        window.translations = {
            max_hours_short: @json(__('Max hours for short reservation')),
            charter_content: @json(__('Conditions')),
            charter_link: @json(__('Conditions link')),
            secret_message: @json(__('Secret message')),
            default_settings: @json(__('Default settings')),
            delete_image: @json(__('Delete this image')),
            restore_image: @json(__('Restore this image')),
            geocode_fill_fields: @json(__('Please fill in at least the street and city.')),
            geocode_not_found: @json(__('Address not found. Please check or enter coordinates manually.')),
            geocode_error: @json(__('Search error. Please try again.')),
        };
    </script>
@endsection

@section('content')
<div class="max-w-4xl mx-auto py-6">
    <div class="form-header">
        <h1 class="form-title">
            {{ isset($room) ? __('Edit room') : __('New room') }}
        </h1>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ isset($room) ? route('rooms.update', [$room] + redirect_back_query()) : route('rooms.store', redirect_back_query()) }}" class="styled-form" enctype="multipart/form-data">
            @csrf
            @if(isset($room))
                @method('PUT')
            @endif

            <!-- Informations de base -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Basic information') }}</h3>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="owner_id" class="form-element-title">{{ __('Owner') }}</label>
                        <select name="owner_id" id="owner_id" required>
                            <option value="">{{ __('Select an owner') }}</option>
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id', $room?->owner_id) == $owner->id)>
                                    {{ $owner->contact->display_name() }}
                                </option>
                            @endforeach
                        </select>
                        @error('owner_id')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="name" class="form-element-title">{{ __('Room name') }}</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $room?->name) }}"
                            required
                        >
                        <small class="text-gray-600">{{ __('The slug will be automatically generated from the name') }}</small>
                        @error('name')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="description" class="form-element-title">{{ __('Description') }}</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                        >{{ old('description', $room?->description) }}</textarea>
                        @error('description')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input
                                type="hidden"
                                name="active"
                                value="0"
                            >
                            <input
                                type="checkbox"
                                name="active"
                                value="1"
                                @checked(old('active', $room?->active ?? true))
                            >
                            <span>{{ __('Active room (bookable)') }}</span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <!-- Adresse -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Address') }}</h3>

                @if($locations->isNotEmpty())
                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="pep-location-select" class="form-element-title">{{ __('Prefill from a saved location') }}</label>
                        <select id="pep-location-select">
                            <option value="">{{ __('— Choose a location —') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}"
                                    data-street="{{ $loc->street }}" data-postal="{{ $loc->postal_code }}"
                                    data-city="{{ $loc->city }}" data-country="{{ $loc->country }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-gray-600 block mt-1">{{ __('Fills the address fields below. You can still edit them.') }}</small>
                    </div>
                </fieldset>
                <script>
                (function () {
                    document.addEventListener('DOMContentLoaded', function () {
                        const sel = document.getElementById('pep-location-select');
                        if (!sel) return;
                        sel.addEventListener('change', function () {
                            const o = sel.options[sel.selectedIndex];
                            if (!o.value) return;
                            const set = (id, v) => { const el = document.getElementById(id); if (el) { el.value = v || ''; el.dispatchEvent(new Event('input', {bubbles:true})); } };
                            set('street', o.dataset.street); set('postal_code', o.dataset.postal);
                            set('city', o.dataset.city); set('country', o.dataset.country);
                        });
                    });
                })();
                </script>
                @endif

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="street" class="form-element-title">{{ __('Street') }} <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            id="street"
                            name="street"
                            value="{{ old('street', $room?->street) }}"
                            required
                        >
                        @error('street')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="postal_code" class="form-element-title">{{ __('Postal code') }} <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                id="postal_code"
                                name="postal_code"
                                value="{{ old('postal_code', $room?->postal_code) }}"
                                required
                            >
                            @error('postal_code')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="city" class="form-element-title">{{ __('City') }} <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                id="city"
                                name="city"
                                value="{{ old('city', $room?->city) }}"
                                required
                            >
                            @error('city')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="country" class="form-element-title">{{ __('Country') }} <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            id="country"
                            name="country"
                            value="{{ old('country', $room?->country ?? 'Suisse') }}"
                            required
                        >
                        @error('country')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>
            </div>

            <!-- Coordonnées GPS -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('GPS coordinates') }}</h3>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="latitude" class="form-element-title">{{ __('Latitude') }} <span class="text-red-500">*</span></label>
                            <input
                                type="number"
                                id="latitude"
                                name="latitude"
                                step="0.00000001"
                                min="-90"
                                max="90"
                                value="{{ old('latitude', $room?->latitude) }}"
                                required
                            >
                            @error('latitude')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="longitude" class="form-element-title">{{ __('Longitude') }} <span class="text-red-500">*</span></label>
                            <input
                                type="number"
                                id="longitude"
                                name="longitude"
                                step="0.00000001"
                                min="-180"
                                max="180"
                                value="{{ old('longitude', $room?->longitude) }}"
                                required
                            >
                            @error('longitude')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field flex items-end">
                            <button type="button" id="geocode-button" class="btn btn-secondary btn-inline">
                                <span id="geocode-loading" class="hidden">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                {{ __('Search') }}
                            </button>
                        </div>
                    </div>
                    <small class="text-gray-600">{{ __('Click "Search" to get coordinates from the address') }}</small>
                    <div id="geocode-error" class="text-red-600 text-sm mt-2 hidden"></div>
                </fieldset>
            </div>

            <!-- Images -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Images') }}</h3>
                <small class="text-gray-600 block mb-4">{{ __('Drag and drop images to change the display order. The first image will be used as the main image.') }}</small>

                <fieldset class="form-element fieldset-images">
                    <label class="form-element-title">{{ __('Images') }}</label>
                    <div id="images-sortable" class="grid grid-cols-3 gap-4 mt-2 min-h-[8rem]">
                        @if(isset($room) && $room->images->count() > 0)
                            @foreach($room->images as $index => $image)
                                <div class="image-item relative group cursor-move border-2 border-transparent hover:border-blue-400 rounded-lg transition-colors"
                                     data-type="existing"
                                     data-image-id="{{ $image->id }}"
                                     id="image-container-{{ $image->id }}"
                                     draggable="true">
                                    <img src="{{ $image->url }}" alt="{{ $image->original_name }}" class="w-full h-32 object-cover rounded-lg pointer-events-none">
                                    <div class="absolute top-2 left-2 bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold image-order-badge">
                                        {{ $index + 1 }}
                                    </div>
                                    <button
                                        type="button"
                                        class="image-remove-btn absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer ignore-styled-form"
                                        title="{{ __('Delete this image') }}"
                                    >
                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                    <input type="hidden" name="image_order[]" value="existing:{{ $image->id }}">
                                </div>
                            @endforeach
                        @endif
                    </div>
                    <div id="remove-images-container"></div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="images-input" class="form-element-title">{{ __('Add images') }}</label>
                        <input
                            type="file"
                            id="images-input"
                            multiple
                            accept="image/jpeg,image/jpg,image/png,image/webp"
                            class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-md file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100 cursor-pointer"
                        >
                        <small class="text-gray-600">{{ __('Maximum 3 images total. Accepted formats: JPEG, PNG, WebP. Max size: 5 MB per image.') }}</small>
                        @error('images')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                        @error('images.*')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    {{-- Hidden file input that will be populated with reordered files --}}
                    <input type="file" id="images-ordered" name="images[]" multiple class="hidden">
                </fieldset>
            </div>

            <!-- Accès à la salle -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Room access') }}</h3>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_public" value="0">
                            <input
                                type="checkbox"
                                name="is_public"
                                value="1"
                                id="is_public_checkbox"
                                @checked(old('is_public', $room?->is_public ?? true))
                            >
                            <span>{{ __('Open to all logged-in members') }}</span>
                        </label>
                        <small class="text-gray-600 block mt-1">
                            {{ __('If enabled, every logged-in member can see and book this room.') }}<br>
                            {{ __('If disabled, only members of the companies selected below (and people with direct access) can see and book it.') }}
                        </small>
                    </div>

                    <div class="form-field mt-4" id="companies-access-block">
                        <label class="block mb-1 font-medium">{{ __('Companies with access (used when not open to all)') }}</label>
                        @php
                            $roomCompanyIds = $room ? $room->companies->pluck('id')->all() : [];
                            $oldRoomCompanies = collect(old('companies', $roomCompanyIds))->map(fn ($v) => (int) $v)->all();
                        @endphp
                        @forelse($companies as $company)
                            <label class="flex items-center gap-2 mb-2">
                                <input type="checkbox" class="pep-company-checkbox" name="companies[]" value="{{ $company->id }}"
                                    @checked(in_array($company->id, $oldRoomCompanies))>
                                <span>{{ $company->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">
                                {{ __('No company yet.') }}
                                <a href="{{ route('companies.create') }}" class="link-primary" target="_blank">{{ __('Create one') }}</a>.
                            </p>
                        @endforelse
                    </div>
                </fieldset>
                <script>
                (function () {
                    // La Pépite : si la salle est « ouverte à tous », on désactive
                    // (et grise) le choix des entreprises — inutile dans ce cas.
                    function syncCompanies() {
                        const pub = document.getElementById('is_public_checkbox');
                        const block = document.getElementById('companies-access-block');
                        if (!pub || !block) return;
                        const disabled = pub.checked;
                        block.querySelectorAll('.pep-company-checkbox').forEach(cb => { cb.disabled = disabled; });
                        block.style.opacity = disabled ? '0.45' : '';
                        block.style.pointerEvents = disabled ? 'none' : '';
                    }
                    document.addEventListener('DOMContentLoaded', function () {
                        const pub = document.getElementById('is_public_checkbox');
                        if (pub) pub.addEventListener('change', syncCompanies);
                        syncCompanies();
                    });
                })();
                </script>
            </div>

            <!-- Équipements (La Pépite) -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Equipment') }}</h3>
                @php $roomEquipments = old('equipments', $room?->equipments ?? []); @endphp
                <div class="form-element" style="display:flex;flex-wrap:wrap;gap:.75rem 1.5rem">
                    @foreach(\App\Models\Room::EQUIPMENTS as $eq)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="equipments[]" value="{{ $eq }}"
                                @checked(in_array($eq, $roomEquipments))>
                            <span>{{ \App\Models\Room::equipmentLabel($eq) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Tarification -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Pricing') }}</h3>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="price_mode" class="form-element-title">{{ __('Pricing mode') }}</label>
                        <select name="price_mode" id="price_mode" required>
                            <option value="fixed" @selected(old('price_mode', $room?->price_mode?->value ?? 'fixed') == 'fixed')>{{ __('Fixed price') }}</option>
                            <option value="free" @selected(old('price_mode', $room?->price_mode?->value) == 'free')>{{ __('Free contribution') }}</option>
                        </select>
                        <small class="text-gray-600">{{ __('If free contribution is selected, the following settings are used to calculate a suggested price') }}</small>
                        @error('price_mode')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element" id="free-price-explanation-field" style="display: none;">
                    <div class="form-field">
                        <label for="free_price_explanation" class="form-element-title">{{ __('Explanation for free contribution') }}</label>
                        <textarea
                            id="free_price_explanation"
                            name="free_price_explanation"
                            rows="3"
                        >{{ old('free_price_explanation', $room?->free_price_explanation ?? '') }}</textarea>
                        <small class="text-gray-600">{{ __('Optional text explaining the free contribution policy') }}</small>
                        @error('free_price_explanation')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                {{-- Salle « sur demande » : pas de prix affiché, réservation par devis --}}
                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="on_request" value="0">
                            <input type="checkbox" name="on_request" value="1" id="on_request_checkbox"
                                @checked(old('on_request', $room?->on_request))>
                            <span class="font-medium">{{ __('On request only (no online price — booked via a special request / quote)') }}</span>
                        </label>
                        <small class="text-gray-600 block mt-1">{{ __('For rooms like La Big Room or La Place du Village. Hides the pricing below and the direct booking.') }}</small>
                    </div>
                </fieldset>

                {{-- Prix par salle (La Pépite). Deux colonnes selon le statut du réservant.
                     Les créneaux/durées sont globaux (réglages système). --}}
                <div id="pep-pricing-block">
                <p class="text-sm text-gray-600 mb-2">{{ __('Prices are specific to this room. Left column: non-profit organizations. Right column: for-profit organizations. Time windows are set globally in the system settings.') }}</p>

                <table class="pep-price-table" style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:6px"></th>
                            <th style="text-align:left;padding:6px">{{ __('Non-profit') }}</th>
                            <th style="text-align:left;padding:6px">{{ __('For-profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:6px">{{ __('Hourly price') }}</td>
                            <td style="padding:6px"><input type="number" name="price_np_hourly" step="0.01" min="0" value="{{ old('price_np_hourly', $room?->price_np_hourly ?? '') }}" style="width:120px"></td>
                            <td style="padding:6px"><input type="number" id="price_hourly" name="price_hourly" step="0.01" min="0" value="{{ old('price_hourly', $room?->price_hourly ?? '') }}" style="width:120px"></td>
                        </tr>
                        <tr>
                            <td style="padding:6px">{{ __('Half day price') }}</td>
                            <td style="padding:6px"><input type="number" name="price_np_half_day" step="0.01" min="0" value="{{ old('price_np_half_day', $room?->price_np_half_day ?? '') }}" style="width:120px"></td>
                            <td style="padding:6px"><input type="number" id="price_half_day" name="price_half_day" step="0.01" min="0" value="{{ old('price_half_day', $room?->price_half_day ?? '') }}" style="width:120px"></td>
                        </tr>
                        <tr>
                            <td style="padding:6px">{{ __('Full day price') }}</td>
                            <td style="padding:6px"><input type="number" name="price_np_full_day" step="0.01" min="0" value="{{ old('price_np_full_day', $room?->price_np_full_day ?? '') }}" style="width:120px"></td>
                            <td style="padding:6px"><input type="number" id="price_full_day" name="price_full_day" step="0.01" min="0" value="{{ old('price_full_day', $room?->price_full_day ?? '') }}" style="width:120px"></td>
                        </tr>
                    </tbody>
                </table>
                <small class="text-gray-600 block mt-1">{{ __('Hourly = price × hours (up to the global hourly max). Half-day = flat price for morning / afternoon / evening. Leave a cell empty to disable that mode. If the non-profit cell is empty, the for-profit price is used.') }}</small>
                @error('price_full_day')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
                </div>

                {{-- Bascule prix ⇄ sur demande --}}
                <script>
                    (function () {
                        const onReq = document.getElementById('on_request_checkbox');
                        const block = document.getElementById('pep-pricing-block');
                        const sync = () => {
                            const off = onReq.checked;
                            block.style.opacity = off ? '.4' : '1';
                            block.style.pointerEvents = off ? 'none' : 'auto';
                        };
                        onReq.addEventListener('change', sync);
                        sync();
                    })();
                </script>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="allow_late_end_hour" class="form-element-title">{{ __('Allowed late end hour') }}</label>
                        <input
                            type="number"
                            id="allow_late_end_hour"
                            name="allow_late_end_hour"
                            min="0"
                            value="{{ old('allow_late_end_hour', $room?->allow_late_end_hour ?? 0) }}"
                        >
                        <small class="text-gray-600">{{ __('E.g.: if a reservation ends the next day before 3am, the following day is not counted') }}</small>
                        @error('allow_late_end_hour')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="use_special_discount" value="0">
                            <input
                                type="checkbox"
                                name="use_special_discount"
                                value="1"
                                @checked(old('use_special_discount', $room?->use_special_discount))
                            >
                            <span>{{ __('Use special discounts') }}</span>
                        </label>
                    </div>
                    <small class="text-gray-600">{{ __('Allows admins to grant special discounts on a case-by-case basis') }}</small>
                </fieldset>

                <fieldset class="form-element" id="donation-fieldset">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="use_donation" value="0">
                            <input
                                type="checkbox"
                                name="use_donation"
                                value="1"
                                @checked(old('use_donation', $room?->use_donation))
                            >
                            <span>{{ __('Enable donations') }}</span>
                        </label>
                    </div>
                    <small class="text-gray-600">{{ __('Display an optional field in the booking form to add a donation') }}</small>
                </fieldset>
            </div>

            <!-- Règles de réservation -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Reservation rules') }}</h3>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="reservation_cutoff_days" class="form-element-title">{{ __('Minimum notice (days before)') }}</label>
                            <input
                                type="number"
                                id="reservation_cutoff_days"
                                name="reservation_cutoff_days"
                                min="0"
                                value="{{ old('reservation_cutoff_days', $room?->reservation_cutoff_days) }}"
                            >
                            @error('reservation_cutoff_days')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="reservation_advance_limit" class="form-element-title">{{ __('Maximum booking (days in advance)') }}</label>
                            <input
                                type="number"
                                id="reservation_advance_limit"
                                name="reservation_advance_limit"
                                min="0"
                                value="{{ old('reservation_advance_limit', $room?->reservation_advance_limit) }}"
                            >
                            @error('reservation_advance_limit')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <!-- Booking rules -->
                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="form-element-title">{{ __('Allowed booking days') }}</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                            @php
                                $weekdays = [
                                    1 => __('Monday'),
                                    2 => __('Tuesday'),
                                    3 => __('Wednesday'),
                                    4 => __('Thursday'),
                                    5 => __('Friday'),
                                    6 => __('Saturday'),
                                    7 => __('Sunday'),
                                ];
                                $allowedWeekdays = old('allowed_weekdays', $room?->allowed_weekdays ?? []);
                                // If null (all days allowed), check all boxes
                                $allDaysAllowed = is_null($room?->allowed_weekdays) && !old('allowed_weekdays');
                            @endphp
                            @foreach($weekdays as $day => $label)
                                <label class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="allowed_weekdays[]"
                                        value="{{ $day }}"
                                        @checked($allDaysAllowed || in_array($day, $allowedWeekdays))
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('allowed_weekdays')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <label class="form-element-title">{{ __('Reservable times - Please note that this will prevent multi-day events') }}</label>
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="day_start_time" class="text-sm text-gray-600 mb-4">{{ __('Day start time') }}</label>
                            <input
                                type="time"
                                id="day_start_time"
                                name="day_start_time"
                                value="{{ old('day_start_time', $room?->day_start_time ? substr($room->day_start_time, 0, 5) : '') }}"
                            >
                            <small class="text-gray-600">{{ __('Leave empty to disable') }}</small>
                            @error('day_start_time')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="day_end_time" class="text-sm text-gray-600 mb-4">{{ __('Day end time') }}</label>
                            <input
                                type="time"
                                id="day_end_time"
                                name="day_end_time"
                                value="{{ old('day_end_time', $room?->day_end_time ? substr($room->day_end_time, 0, 5) : '') }}"
                            >
                            <small class="text-gray-600">{{ __('Leave empty to disable') }}</small>
                            @error('day_end_time')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>
            </div>

            <!-- Charte -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Conditions') }}</h3>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="charter_mode" class="form-element-title">{{ __('Conditions display') }}</label>
                        <select name="charter_mode" id="charter_mode" required>
                            <option value="text" @selected(old('charter_mode', $room?->charter_mode?->value ?? 'text') == 'text')>{{ __('Text') }}</option>
                            <option value="link" @selected(old('charter_mode', $room?->charter_mode?->value) == 'link')>{{ __('Link') }}</option>
                            <option value="none" @selected(old('charter_mode', $room?->charter_mode?->value) == 'none')>{{ __('None') }}</option>
                        </select>
                        @error('charter_mode')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element" id="charter_str_field">
                    <div class="form-field">
                        <label for="charter_str" class="form-element-title">
                            <span id="charter_str_label">{{ __('Conditions') }}</span>
                        </label>
                        <textarea
                            id="charter_str"
                            name="charter_str"
                            rows="4"
                        >{{ old('charter_str', $room?->charter_str) }}</textarea>
                        <small class="text-gray-600">{{ __('Required unless "None" is selected') }}</small>
                        @error('charter_str')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>
            </div>

            <!-- Message secret -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Messages') }}</h3>
                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="custom_message" class="form-element-title">{{ __('Custom message (sent by email with the reservation confirmation)') }}</label>
                        <textarea
                            id="custom_message"
                            name="custom_message"
                            rows="3"
                        >{{ old('custom_message', $room?->custom_message) }}</textarea>
                        @error('custom_message')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element" id="secret_message_field">
                    <div class="form-field">
                        <label for="secret_message" class="form-element-title">{{ __('Secret message') }}</label>
                        <textarea
                            id="secret_message"
                            name="secret_message"
                            rows="3"
                        >{{ old('secret_message', $room?->secret_message) }}</textarea>
                        <small class="text-gray-600">{{ __('For example, to share the room code. The message can be changed at any time and will be shared via a link valid until the end of the reservation.') }}</small>
                        @error('secret_message')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="secret_message_days_before" class="form-element-title">{{ __('Secret message visible X days before events') }}</label>
                        <input
                            type="number"
                            id="secret_message_days_before"
                            name="secret_message_days_before"
                            min="1"
                            value="{{ old('secret_message_days_before', $room?->secret_message_days_before) }}"
                        >
                        <small class="text-gray-600">{{ __('If empty, the secret message is always visible for confirmed reservations.') }}</small>
                        @error('secret_message_days_before')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>
            </div>

            <!-- Configuration Calendrier (désactivée pour l'instant — La Pépite) -->
            <div class="form-group" style="opacity:.5;pointer-events:none" aria-disabled="true">
                <h3 class="form-group-title">{{ __('Calendar configuration') }}</h3>
                <p class="text-sm text-gray-600 mb-3">🔒 {{ __('Not configured yet — this section is disabled for now.') }}</p>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="external_slot_provider" class="form-element-title">{{ __('External slot provider') }}</label>
                        <select name="external_slot_provider" id="external_slot_provider">
                            <option value="" @selected(is_null(old('external_slot_provider', $room?->external_slot_provider)))>{{ __('None') }}</option>
                            <option value="caldav" id="caldav-option" @selected(old('external_slot_provider', $room?->external_slot_provider?->value) == 'caldav')>CalDAV</option>
                        </select>
                        @error('external_slot_provider')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element" id="dav_calendar_field">
                    <div class="form-field">
                        <label for="dav_calendar" class="form-element-title">{{ __('CalDAV calendar') }}</label>
                        <input
                            type="text"
                            id="dav_calendar"
                            name="dav_calendar"
                            value="{{ old('dav_calendar', $room?->dav_calendar) }}"
                        >
                        <small class="text-gray-600">{{ __('If the calendar does not exist, the system will try to create it.') }}</small>
                        @error('dav_calendar')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="embed_calendar_mode" class="form-element-title">{{ __('Calendar integration mode') }}</label>
                        <select name="embed_calendar_mode" id="embed_calendar_mode">
                            <option value="disabled" @selected(old('embed_calendar_mode', $room?->embed_calendar_mode?->value ?? 'disabled') == 'disabled')>{{ __('Disabled') }}</option>
                            <option value="enabled" @selected(old('embed_calendar_mode', $room?->embed_calendar_mode?->value) == 'enabled')>{{ __('Enabled (public form)') }}</option>
                            <option value="admin_only" @selected(old('embed_calendar_mode', $room?->embed_calendar_mode?->value) == 'admin_only')>{{ __('Admin only') }}</option>
                        </select>
                        <small class="text-gray-600">{{ __('Define whether a calendar view of the room should be visible directly in the reservation form.') }}</small>
                        @error('embed_calendar_mode')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="calendar_view_mode" class="form-element-title">{{ __('Calendar display mode') }}</label>
                        <select name="calendar_view_mode" id="calendar_view_mode">
                            @php
                                $selected = old('calendar_view_mode', $room?->calendar_view_mode?->value ?? App\Enums\CalendarViewModes::SLOT->value);
                            @endphp
                            @foreach(App\Enums\CalendarViewModes::cases() as $mode)
                                <option value="{{ $mode->value }}" @selected( $mode->value === $selected )>{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                        @error('calendar_view_mode')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>
            </div>

            <!-- Paramètres régionaux -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Regional settings (optional)') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('Leave empty to use the owner settings') }}</p>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="timezone" class="form-element-title">{{ __('Timezone') }}</label>
                        @include('partials._timezone_select', [
                            'name' => 'timezone',
                            'id' => 'timezone',
                            'value' => old('timezone') ?? $room?->timezone,
                            'defaultTimezone' => $ownerTimezones[old('owner_id') ?? $room?->owner_id] ?? $systemSettings?->getTimezone() ?? __('Not defined'),
                        ])
                        @error('timezone')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>
            </div>

            <div class="form-group">
                <h3 class="form-group-title">{{ __('Emails') }}</h3>
                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="disable_mailer" value="0">
                            <input
                                type="checkbox"
                                name="disable_mailer"
                                value="1"
                                @checked(old('disable_mailer', $room?->disable_mailer))
                            >
                            <span>{{ __('Disable sending emails for this room') }}</span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="btn-group justify-end mt-6">
                <a href="{{ redirect_back_url('rooms.index', ['view' => 'mine']) }}" class="btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    {{ isset($room) ? __('Update') : __('Create') }}
                </button>
                @if(isset($room))
                    <button type="button" onclick="confirmDeleteRoom()" class="btn btn-delete">
                        {{ __('Delete') }}
                    </button>
                @endif
            </div>
        </form>
        @if(isset($room))
            <form id="delete-room-form" action="{{ route('rooms.destroy', [$room] + redirect_back_query()) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
</div>
<script>
    // Update timezone default when owner changes
    window.ownerTimezones = @json($ownerTimezones);
    window.ownersCaldavValid = @json($ownersCaldavValid);

    function confirmDeleteRoom() {
        if (confirm('{{ __('Are you sure you want to delete this room?') }}')) {
            document.getElementById('delete-room-form').submit();
        }
    }
</script>
@endsection
