<div class="form-group" id="events-form-group">
    <h3 class="form-group-title">{{ __('Reservation dates and times') }} *</h3>
    @if($room->availabilityWindows->isEmpty())
        @if(! $room->openedEveryday())
            <div class="form-element flex justify-between">
                <dt class="text-gray-600">{{ __('Bookable days') }}</dt>
                <dd class="text-gray-900 text-right">{{ $room->allowed_weekdays ? implode(', ', $room->allowedWeekdayNames()) : __('None.days') }}</dd>
            </div>
        @endif
        @if($day_start_time || $day_end_time)
            <div class="form-element flex justify-between">
                <dt class="text-gray-600">{{ __('Bookable hours') }}</dt>
                <dd class="text-gray-900">
                    {{ $day_start_time ? substr($day_start_time, 0, 5) : '00:00' }}
                    -
                    {{ $day_end_time ? substr($day_end_time, 0, 5) : '00:00' }}
                </dd>
            </div>
        @endif
    @endif
    @error('events')
        <span class="text-red-600 text-sm block mb-2">{{ $message }}</span>
    @enderror
    <div id="events-container">
        @foreach ($events as $event)
            @include('reservations.partials.event-row', [
                'event' => $event,
                'availableOptions' => $availableOptions,
                'owner' => $owner,
            ])
        @endforeach
    </div>

    <button
        type="button"
        id="add-event"
        class="btn btn-confirm"
    >
        {{ __('Add a date') }}
    </button>
</div>

<template id="new-event-row">
    <div class="form-element event-row" data-event-id="__INDEX__">
        <div class="form-element-row event-row-date">
            {{-- Remove --}}
            <button
                type="button"
                class="event-remove"
                id="event-remove-__INDEX__"
                aria-label="{{ __('Remove this date') }}"
            >
                ✕
            </button>

            {{-- Start datetime --}}
            <div class="form-field">
                <input
                    type="datetime-local"
                    name="events[__INDEX__][start]"
                    value=""
                    class="event-start"
                    required
                >
            </div>

            {{-- End datetime --}}
            <div class="form-field">
                <input
                    type="datetime-local"
                    name="events[__INDEX__][end]"
                    value=""
                    class="event-end"
                    required
                >
            </div>

            {{-- Availability status --}}
            <span class="status-label" id="event-status-__INDEX__"></span>

            <input
                type="hidden"
                name="events[__INDEX__][uid]"
                value=""
            >
        </div>

        @if(!$availableOptions->isEmpty())
            <div class="form-element-row event-row-options">
                @foreach($availableOptions as $opt)
                    <div class="form-field option-field">
                        <input
                            type="checkbox"
                            id="option___INDEX___{{ $opt->id }}"
                            name="events[__INDEX__][options][]"
                            value="{{ $opt->id }}"
                        >
                        <label for="option___INDEX___{{ $opt->id }}" class="tooltip-target" data-tooltip="{{ $opt->description }}">
                            {{ $opt->name }} ({{ currency($opt->price, $owner) }}) ⓘ
                        </label>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="form-element-row event-row-info">
            {{-- Info text / price --}}
            <div class="form-field event-info">
            <span class="event-info-text"></span>
            </div>
        </div>
    </div>
</template>
