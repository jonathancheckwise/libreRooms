@extends('layouts.app')

@section('title', __('System settings'))

@section('page-script')
    @vite(['resources/js/system-settings/system-settings-form.js'])
    <script>
        window.systemSettingsId = {{ $settings?->id ?? 'null' }};
        window.translations = {
            testing: @json(__('Testing...')),
            verifying: @json(__('Verifying...')),
            network_error: @json(__('Network error')),
        };
    </script>
@endsection

@section('content')
<div class="max-w-4xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('System settings') }}</h1>
        @include('system-settings._submenu')
        <p class="mt-2 text-sm text-gray-600">{{ __('Global application configuration') }}</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('system-settings.update') }}" class="styled-form">
            @csrf
            @method('PUT')

            <!-- Configuration email -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Email configuration') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('SMTP configuration for sending emails (required)') }}</p>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="mail_host" class="form-element-title">{{ __('SMTP server') }}</label>
                            <input
                                type="text"
                                id="mail_host"
                                name="mail_host"
                                value="{{ old('mail_host', $settings?->mail_host) }}"
                                required
                            >
                            @error('mail_host')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="mail_port" class="form-element-title">{{ __('Port') }}</label>
                            <input
                                type="number"
                                id="mail_port"
                                name="mail_port"
                                value="{{ old('mail_port', $settings?->mail_port) }}"
                                required
                                min="1"
                                max="65535"
                            >
                            @error('mail_port')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="mail" class="form-element-title">{{ __('Email (SMTP user)') }}</label>
                            <input
                                type="text"
                                id="mail"
                                name="mail"
                                value="{{ old('mail', $settings?->mail) }}"
                                required
                            >
                            @error('mail')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="mail_pass" class="form-element-title">
                                {{ __('SMTP password') }}
                                @if($settings?->mail_pass)
                                    <span class="text-xs text-gray-500">{{ __('(leave blank to keep current)') }}</span>
                                @endif
                            </label>
                            <input
                                type="password"
                                id="mail_pass"
                                name="mail_pass"
                                value="{{ old('mail_pass') }}"
                                {{ $settings?->mail_pass ? '' : 'required' }}
                                @if($settings?->mail_pass)
                                    placeholder="***************"
                                @endif
                            >
                            @error('mail_pass')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <div id="mail-status" class="config-status hidden"></div>
            </div>

            <!-- Configuration CalDAV -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Default CalDAV configuration') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('CalDAV configuration available to owners (optional)') }}</p>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="dav_url" class="form-element-title">{{ __('CalDAV URL') }}</label>
                        <input
                            type="text"
                            id="dav_url"
                            name="dav_url"
                            value="{{ old('dav_url', $settings?->dav_url) }}"
                        >
                        @error('dav_url')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="dav_user" class="form-element-title">{{ __('CalDAV user') }}</label>
                            <input
                                type="text"
                                id="dav_user"
                                name="dav_user"
                                value="{{ old('dav_user', $settings?->dav_user) }}"
                            >
                            @error('dav_user')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="dav_pass" class="form-element-title">
                                {{ __('CalDAV password') }}
                                @if($settings?->dav_pass)
                                    <span class="text-xs text-gray-500">{{ __('(leave blank to keep current)') }}</span>
                                @endif
                            </label>
                            <input
                                type="password"
                                id="dav_pass"
                                name="dav_pass"
                                value="{{ old('dav_pass') }}"
                                @if($settings?->dav_pass)
                                    placeholder="***************"
                                @endif
                            >
                            @error('dav_pass')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <div id="caldav-status" class="config-status hidden"></div>
            </div>

            <!-- Configuration WebDAV -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Default WebDAV configuration') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('WebDAV configuration available to owners (optional)') }}</p>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="webdav_endpoint" class="form-element-title">{{ __('WebDAV endpoint') }}</label>
                        <input
                            type="text"
                            id="webdav_endpoint"
                            name="webdav_endpoint"
                            value="{{ old('webdav_endpoint', $settings?->webdav_endpoint) }}"
                        >
                        @error('webdav_endpoint')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="webdav_user" class="form-element-title">{{ __('WebDAV user') }}</label>
                            <input
                                type="text"
                                id="webdav_user"
                                name="webdav_user"
                                value="{{ old('webdav_user', $settings?->webdav_user) }}"
                            >
                            @error('webdav_user')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="webdav_pass" class="form-element-title">
                                {{ __('WebDAV password') }}
                                @if($settings?->webdav_pass)
                                    <span class="text-xs text-gray-500">{{ __('(leave blank to keep current)') }}</span>
                                @endif
                            </label>
                            <input
                                type="password"
                                id="webdav_pass"
                                name="webdav_pass"
                                value="{{ old('webdav_pass') }}"
                                @if($settings?->webdav_pass)
                                    placeholder="***************"
                                @endif
                            >
                            @error('webdav_pass')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="webdav_save_path" class="form-element-title">{{ __('Save path') }}</label>
                        <input
                            type="text"
                            id="webdav_save_path"
                            name="webdav_save_path"
                            value="{{ old('webdav_save_path', $settings?->webdav_save_path) }}"
                        >
                        @error('webdav_save_path')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <div id="webdav-status" class="config-status hidden"></div>
            </div>

            <!-- Paramètres régionaux -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Regional settings') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('Default settings for owners (required)') }}</p>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="timezone" class="form-element-title">{{ __('Timezone') }}</label>
                        @include('partials._timezone_select', [
                            'name' => 'timezone',
                            'id' => 'timezone',
                            'value' => old('timezone', $settings?->timezone),
                            'showDefaultOption' => false,
                            'required' => true,
                        ])
                        @error('timezone')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="currency" class="form-element-title">{{ __('Currency') }}</label>
                            @include('partials._currency_select', [
                                'name' => 'currency',
                                'id' => 'currency',
                                'value' => old('currency', $settings?->currency),
                                'showDefaultOption' => false,
                                'required' => true,
                            ])
                            @error('currency')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label for="locale" class="form-element-title">{{ __('Locale') }}</label>
                            @include('partials._locale_select', [
                                'name' => 'locale',
                                'id' => 'locale',
                                'value' => old('locale', $settings?->locale),
                                'showDefaultOption' => false,
                                'required' => true,
                            ])
                            @error('locale')
                                <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </fieldset>
            </div>

            {{-- Plages horaires globales (La Pépite) --}}
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Booking time windows (all rooms)') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('These windows define what an hourly / half-day / full-day booking means. Prices remain set per room.') }}</p>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label for="hourly_max_hours" class="form-element-title">{{ __('Hourly booking: max hours') }}</label>
                        <input type="number" id="hourly_max_hours" name="hourly_max_hours" min="1" max="24"
                            value="{{ old('hourly_max_hours', $settings?->hourly_max_hours ?? 3) }}" required>
                        <small class="text-gray-600 block mt-1">{{ __('Beyond this duration, the visitor must choose a half-day or full-day booking.') }}</small>
                        @error('hourly_max_hours') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <legend class="text-sm font-medium mb-2">{{ __('Morning half-day') }}</legend>
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="half_day_morning_start" class="form-element-title">{{ __('Start') }}</label>
                            <input type="time" id="half_day_morning_start" name="half_day_morning_start"
                                value="{{ old('half_day_morning_start', $settings ? substr($settings->half_day_morning_start, 0, 5) : '06:00') }}" required>
                            @error('half_day_morning_start') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label for="half_day_morning_end" class="form-element-title">{{ __('End') }}</label>
                            <input type="time" id="half_day_morning_end" name="half_day_morning_end"
                                value="{{ old('half_day_morning_end', $settings ? substr($settings->half_day_morning_end, 0, 5) : '12:00') }}" required>
                            @error('half_day_morning_end') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <legend class="text-sm font-medium mb-2">{{ __('Afternoon half-day') }}</legend>
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="half_day_afternoon_start" class="form-element-title">{{ __('Start') }}</label>
                            <input type="time" id="half_day_afternoon_start" name="half_day_afternoon_start"
                                value="{{ old('half_day_afternoon_start', $settings ? substr($settings->half_day_afternoon_start, 0, 5) : '12:00') }}" required>
                            @error('half_day_afternoon_start') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label for="half_day_afternoon_end" class="form-element-title">{{ __('End') }}</label>
                            <input type="time" id="half_day_afternoon_end" name="half_day_afternoon_end"
                                value="{{ old('half_day_afternoon_end', $settings ? substr($settings->half_day_afternoon_end, 0, 5) : '17:00') }}" required>
                            @error('half_day_afternoon_end') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-element">
                    <legend class="text-sm font-medium mb-2">{{ __('Full day') }}</legend>
                    <div class="form-element-row">
                        <div class="form-field">
                            <label for="full_day_start" class="form-element-title">{{ __('Start') }}</label>
                            <input type="time" id="full_day_start" name="full_day_start"
                                value="{{ old('full_day_start', $settings ? substr($settings->full_day_start, 0, 5) : '07:00') }}" required>
                            @error('full_day_start') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label for="full_day_end" class="form-element-title">{{ __('End') }}</label>
                            <input type="time" id="full_day_end" name="full_day_end"
                                value="{{ old('full_day_end', $settings ? substr($settings->full_day_end, 0, 5) : '17:00') }}" required>
                            @error('full_day_end') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="btn-group justify-end mt-6">
                <button type="submit" class="btn btn-primary">
                    {{ __('Save') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
