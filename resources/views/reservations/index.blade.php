@extends('layouts.app')

@section('title', __('My reservations'))

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('Reservations') }}</h1>

        @include('reservations._submenu', ['view' => $view, 'canViewAdmin' => $canViewAdmin])

        @if(!$canViewAdmin)
            <p class="mt-2 text-sm text-gray-600">{{ __('List of all your reservations') }}</p>
        @endif
    </div>

    <!-- Filtres -->
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <form method="GET" action="{{ route('reservations.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="view" value="{{ $view }}">
            <div>
                <label for="room_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Room') }}</label>
                <select name="room_id" id="room_id" class="form-select">
                    <option value="">{{ __('All rooms') }}</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" {{ request('room_id') == $room->id ? 'selected' : '' }}>
                            {{ $room->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="tenant_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Tenant') }}</label>
                <select name="tenant_id" id="tenant_id" class="form-select">
                    <option value="">{{ __('All contacts') }}</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}" {{ request('tenant_id') == $contact->id ? 'selected' : '' }}>
                            {{ $contact->display_name() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                <select name="status" id="status" class="form-select">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach(\App\Enums\ReservationStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary flex-1">
                    {{ __('Filter') }}
                </button>
                @if(request()->hasAny(['room_id', 'tenant_id', 'status']))
                    <a href="{{ route('reservations.index', ['view' => $view]) }}" class="btn btn-secondary">
                        {{ __('Reset') }}
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tableau des réservations -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('No.') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Room') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Tenant') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Title') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hide-on-mobile">
                        {{ __('Price') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Status') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hide-on-mobile">
                        {{ __('Created on') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($reservations as $reservation)
                    @php
                        $canManage = $user->can('manageReservations', $reservation->room);
                        $isPending = $reservation->status === \App\Enums\ReservationStatus::PENDING;
                        $isConfirmed = $reservation->status === \App\Enums\ReservationStatus::CONFIRMED;
                        $isCancelled = $reservation->status === \App\Enums\ReservationStatus::CANCELLED;
                        $isFinished = $reservation->status === \App\Enums\ReservationStatus::FINISHED;
                        $canEdit = $reservation->isEditable();
                        $canCancel = $isPending || ($isConfirmed && $canManage);
                    @endphp
                    <tr class="hover:bg-gray-50 cursor-pointer transition" onclick="toggleDetails({{ $reservation->id }})">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            <a href="{{ route('reservations.show', [$reservation] + redirect_back_params()) }}"
                               class="link-primary" title="{{ __('View') }}" onclick="event.stopPropagation()">
                               #{{ $reservation->id }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <a href="{{ route('rooms.show', $reservation->room) }}" onclick="event.stopPropagation()">
                                {{ $reservation->room->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="contact-info">
                                @if ($user->canAccessContact($reservation->tenant))
                                    <a href="{{ route('contacts.edit', $reservation->tenant) }}" onclick="event.stopPropagation()">
                                        <span class="contact-info-name">{{ $reservation->tenant->display_name() }}</span>
                                    </a>
                                @else
                                    <span class="contact-info-name">{{ $reservation->tenant->display_name() }}</span>
                                @endif
                                <div class="contact-info-icons" onclick="event.stopPropagation()">
                                    @if($reservation->tenant->phone)
                                        <a href="tel:{{ $reservation->tenant->phone }}" title="{{ $reservation->tenant->phone }}">
                                            <x-icons.phone />
                                        </a>
                                    @endif
                                    <a href="mailto:{{ $reservation->tenant->email }}" title="{{ $reservation->tenant->email }}">
                                        <i class="fa-regular fa-envelope"></i>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $reservation->title }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 hide-on-mobile">
                            {{ currency($reservation->finalPrice(), $reservation->room->owner) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 whitespace-nowrap inline-flex text-xs leading-5 font-semibold rounded-full {{ match($reservation->status) {
                                \App\Enums\ReservationStatus::PENDING => 'bg-yellow-100 text-yellow-800',
                                \App\Enums\ReservationStatus::CONFIRMED => 'bg-green-100 text-green-800',
                                \App\Enums\ReservationStatus::FINISHED => 'bg-blue-100 text-blue-800',
                                \App\Enums\ReservationStatus::CANCELLED => 'bg-red-100 text-red-800',
                            } }}">
                                {{ $reservation->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 hide-on-mobile">
                            {{ $reservation->created_at->format('d.m.Y') }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium" onclick="event.stopPropagation()">
                            <div class="action-group">
                                <a href="{{ route('reservations.show', [$reservation] + redirect_back_params()) }}" class="link-primary" title="{{ __('View') }}"><x-action-icon action="view" /></a>
                                @if($canEdit)
                                    <a href="{{ route('reservations.edit', [$reservation] + redirect_back_params()) }}" class="link-primary" title="{{ __('Edit') }}"><x-action-icon action="edit" /></a>
                                @endif
                                <form method="POST" action="{{ route('reservations.duplicate', $reservation) }}" class="inline" onclick="event.stopPropagation()">
                                    @csrf
                                    <button type="submit" class="link-primary" title="{{ __('Duplicate') }}" style="background:none;border:none;cursor:pointer;font-size:1.1rem;line-height:1">⧉</button>
                                </form>

                                @if($canCancel)
                                    <button type="button"
                                            onclick="openCancelModal({{ $reservation->id }})"
                                            class="link-danger"
                                            title="{{ __('Cancel') }}">
                                        <x-action-icon action="cancel" />
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Détails dépliables -->
                    <tr id="details-{{ $reservation->id }}" class="details-row hidden">
                        <td colspan="8" class="px-4 py-3 bg-slate-50 border-t border-slate-200 w-0">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- Colonne 1: Description & Événements -->
                                <div class="space-y-4">
                                    @if($reservation->description)
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Description') }}</h4>
                                        <p class="text-sm text-slate-700">{{ $reservation->description ?: '—' }}</p>
                                    </div>
                                    @endif
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Events') }}</h4>
                                        <div class="space-y-1">
                                            @foreach($reservation->events as $event)
                                                <div class="text-sm text-slate-700 flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                    <span>{{ $event->dateString(false) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <!-- Colonne 2: Champs personnalisés & Documents -->
                                <div class="space-y-4">
                                    @if($reservation->customFieldValues->count() > 0)
                                        <div>
                                            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Additional information') }}</h4>
                                            <dl class="space-y-1">
                                                @foreach($reservation->customFieldValues as $fieldValue)
                                                    <div class="text-sm flex gap-2">
                                                        <dt class="font-medium text-slate-600 shrink-0">{{ $fieldValue->label }}:</dt>
                                                        <dd class="text-slate-700">
                                                            {{ implode(', ', $fieldValue->labelValue()) }}
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif

                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Documents') }}</h4>
                                        <div class="flex flex-wrap gap-2">
                                            @if(!$isCancelled)
                                                <a href="{{ route('reservations.prebook.pdf', $reservation->hash) }}"
                                                   target="_blank"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-md text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    {{ __('Pre-booking') }}
                                                </a>
                                            @endif
                                            @if($reservation->invoice)
                                                <a href="{{ route('reservations.invoice.pdf', $reservation->hash) }}"
                                                   target="_blank"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-md text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    {{ __('Invoice') }} {{ $reservation->invoice->number }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Colonne 3: Facture -->
                                @if($reservation->invoice)
                                    @php $invoiceStatus = $reservation->invoice->computed_status; @endphp
                                    <div class="bg-white rounded-lg border border-slate-200 p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ __('Invoice') }}</h4>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $invoiceStatus->color() }}">
                                                {{ $invoiceStatus->label() }}
                                            </span>
                                        </div>
                                        <dl class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <dt class="text-slate-500">{{ __('Issued on') }}</dt>
                                                <dd class="text-slate-900 font-medium">{{ $reservation->invoice->issued_at?->format('d.m.Y') ?? '—' }}</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-slate-500">{{ __('Due date') }}</dt>
                                                <dd class="text-slate-900 font-medium">{{ $reservation->invoice->due_at?->format('d.m.Y') ?? '—' }}</dd>
                                            </div>
                                            @if($reservation->invoice->reminder_count > 0)
                                                <div class="flex justify-between">
                                                    <dt class="text-slate-500">{{ __('Reminders') }}</dt>
                                                    <dd class="text-orange-600 font-medium">{{ $reservation->invoice->reminder_count }}</dd>
                                                </div>
                                            @endif
                                            @if($reservation->invoice->paid_at)
                                                <div class="flex justify-between pt-2 border-t border-slate-100">
                                                    <dt class="text-green-600">{{ __('Paid on') }}</dt>
                                                    <dd class="text-green-600 font-medium">{{ $reservation->invoice->paid_at->format('d.m.Y') }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-3 text-center text-gray-500">
                            {{ __('No reservations found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $reservations->links() }}
    </div>
</div>

<!-- Modal de chargement -->
<div id="loader-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 shadow-xl flex flex-col items-center gap-4">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
        <p class="text-gray-700 font-medium">{{ __('Processing...') }}</p>
    </div>
</div>

<!-- Modal d'annulation -->
<div id="cancel-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('Cancel reservation') }}</h3>
        <form id="cancel-form" method="POST">
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
    function toggleDetails(reservationId) {
        const detailsRow = document.getElementById(`details-${reservationId}`);
        if (detailsRow.classList.contains('hidden')) {
            // Fermer tous les autres détails
            document.querySelectorAll('[id^="details-"]').forEach(row => {
                row.classList.add('hidden');
            });
            // Ouvrir celui-ci
            detailsRow.classList.remove('hidden');
        } else {
            detailsRow.classList.add('hidden');
        }
    }

    const cancelRedirectQuery = @json(http_build_query(redirect_back_params()));

    function openCancelModal(reservationId) {
        const query = cancelRedirectQuery ? '?' + cancelRedirectQuery : '';
        document.getElementById('cancel-form').action = '/reservations/' + reservationId + '/cancel' + query;
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
        document.getElementById('loader-modal').classList.remove('hidden');
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
@endsection
