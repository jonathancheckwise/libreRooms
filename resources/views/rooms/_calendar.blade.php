{{-- Partial calendar view that can be included anywhere --}}
{{-- Fetches events from AvailabilityController via AJAX --}}

@once
    <!-- FullCalendar v6 -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <!-- Tippy.js for tooltips -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>

    <style>
        .tippy-box {
            background-color: #333;
            color: #fff;
            border-radius: 4px;
            font-size: 14px;
            padding: 8px 12px;
            max-width: 300px;
        }
        .tippy-arrow {
            color: #333;
        }
        .tippy-content {
            white-space: pre-line;
        }
        .calendar-wrapper {
            position: relative;
            min-height: 400px;
        }
        .calendar-loader {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.95);
            z-index: 10;
            gap: 1rem;
        }
        .calendar-loader-spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .calendar-loader-text {
            color: #6b7280;
            font-size: 0.875rem;
        }
        .fc-toolbar-chunk {
            display: flex;
        }
        /* Style for non-business hours */
        .fc-non-business {
            background: repeating-linear-gradient(
                45deg,
                #f3f4f6,
                #f3f4f6 10px,
                #e5e7eb 10px,
                #e5e7eb 20px
            ) !important;
        }
    </style>
@endonce

<div class="calendar-wrapper calendar-wrapper-{{ $room->id }}">
    <div id='calendar-{{ $room->id }}'></div>
    <div id="calendar-loader-{{ $room->id }}" class="calendar-loader">
        <div class="calendar-loader-spinner"></div>
        <div class="calendar-loader-text">{{ __('Loading calendar...') }}</div>
    </div>
</div>

<script type="text/javascript">
    (function() {
        // Attendre que FullCalendar soit chargé
        async function initCalendar() {
            if (typeof FullCalendar === 'undefined') {
                setTimeout(initCalendar, 100);
                return;
            }

            const calendarEl = document.getElementById('calendar-{{ $room->id }}');
            if (!calendarEl) return;

            // Check if we're on a reservation form page (reservation-form.js will load the data)
            const isReservationForm = typeof window.RoomConfig !== 'undefined';

            let calendarEvents = [];
            let unavailabilities = [];
            let businessHours = false;

            if (isReservationForm) {
                // Wait for reservation-form.js to load the data (avoids duplicate fetch)
                while (!window.calendarEventsData) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
                const data = window.calendarEventsData;
                calendarEvents = data.events;
                unavailabilities = data.unavailabilities || [];
                businessHours = data.businessHours || false;
            } else {
                // Not on reservation form - fetch directly
                try {
                    const response = await fetch('{{ route('rooms.availability', $room) }}');
                    const data = await response.json();
                    calendarEvents = data.events;
                    unavailabilities = data.unavailabilities || [];
                    businessHours = data.businessHours || false;
                } catch (error) {
                    console.error('Error loading events:', error);
                }
            }
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: '{{ $initialView ?? 'dayGridMonth' }}',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridDay,timeGridWeek,dayGridMonth'
                },
                slotMinTime: '08:00:00',
                slotMaxTime: '22:00:00',
                allDaySlot: false,
                nowIndicator: true,
                expandRows: true,
                height: 'auto',
                buttonText: {
                    today:    "{!! __('today') !!}",
                    month:    '{{ __('month') }}',
                    week:     '{{ __('week') }}',
                    day:      '{{ __('day') }}',
                    list:     '{{ __('list') }}'
                },
                locale: '{{ app()->getLocale() }}',
                firstDay: 1, // Lundi
                timeZone: '{{ $room->getTimezone() }}',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },

                // Use fetched events + unavailabilities as background events
                events: [...calendarEvents, ...unavailabilities],

                eventClick: function(info) {
                    info.jsEvent.preventDefault(); // don't let the browser navigate

                    if (info.event.url) {
                        window.location.href= info.event.url;
                    }
                },
                // Business hours from room configuration
                businessHours: businessHours,

                // Afficher un tooltip avec la description
                eventDidMount: function(info) {
                    if (info.event.extendedProps.description && typeof tippy !== 'undefined') {
                        tippy(info.el, {
                            content: info.event.extendedProps.description,
                            placement: 'top',
                            arrow: true,
                            allowHTML: true,
                            interactive: false,
                        });
                    }
                }
            });

            calendar.render();

            // Hide loader
            const loader = document.getElementById('calendar-loader-{{ $room->id }}');
            if (loader) {
                loader.style.display = 'none';
            }
        }

        // Démarrer l'initialisation
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCalendar);
        } else {
            initCalendar();
        }
    })();
</script>
