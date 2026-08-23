@extends('layouts.app')

@section('title', $reservation->title)

@php
    $room = $reservation->room;
    $owner = $room->owner;
    $tenant = $reservation->tenant;
    $invoice = $reservation->invoice;
    $canManage = $user->can('manageReservations', $reservation->room);
    $isPending = $reservation->status === \App\Enums\ReservationStatus::PENDING;
    $isConfirmed = $reservation->status === \App\Enums\ReservationStatus::CONFIRMED;
    $canEdit = $reservation->isEditable();
    $canCancel = $isPending || ($isConfirmed && $canManage);
    $canConfirm = $canEdit && $canManage;
@endphp

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ $reservation->title }}</h1>
        <div class="mt-2 flex items-center gap-3">
            <a href="{{ route('rooms.show', $room) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ $room->name }}</a>
            <span class="px-2 whitespace-nowrap inline-flex text-xs leading-5 font-semibold rounded-full {{ match($reservation->status) {
                \App\Enums\ReservationStatus::PENDING => 'bg-yellow-100 text-yellow-800',
                \App\Enums\ReservationStatus::CONFIRMED => 'bg-green-100 text-green-800',
                \App\Enums\ReservationStatus::FINISHED => 'bg-blue-100 text-blue-800',
                \App\Enums\ReservationStatus::CANCELLED => 'bg-red-100 text-red-800',
                } }}">
                {{ $reservation->status->label() }}
            </span>
        </div>
        <nav class="page-submenu">
            <a href="{{ route('reservations.index', array_filter(request()->only(['view', 'room_id', 'tenant_id', 'status']))) }}"
               class="page-submenu-item page-submenu-nav">
                {{ __('Back to reservations') }}
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Events -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Dates') }}</h2>
                <div class="space-y-3">
                    @foreach($reservation->events->sortBy('start') as $event)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-sm text-gray-900">
                                    <a href="{{ route('reservations.event-ics', [$reservation->hash, $event->uid]) }}"
                                       class="text-gray-400 hover:text-gray-600" title="{{ __('Download ICS event') }}">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    </a>
                                    <span class="font-medium">{{ $event->dateString() }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if($event->price_label)
                                        <span class="text-xs text-gray-500">{{ $event->price_label }}</span>
                                    @endif
                                    <span class="text-sm font-medium text-gray-900">{{ currency($event->price, $owner) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Description -->
            @if($reservation->description)
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Description') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        {!! nl2br(e($reservation->description)) !!}
                    </div>
                </div>
            @endif

            <!-- Custom fields -->
            @if($reservation->customFieldValues->count() > 0)
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Additional information') }}</h2>
                    <dl class="space-y-2">
                        @foreach($reservation->customFieldValues as $fieldValue)
                            <div class="flex gap-2 text-sm">
                                <dt class="font-medium text-gray-600 shrink-0">{{ $fieldValue->label }} :</dt>
                                <dd class="text-gray-900">{{ implode(', ', $fieldValue->labelValue()) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @php
                $totalPrice = $reservation->events->sum('price');
                $useFreePrice = $room->price_mode == App\Enums\PriceModes::FREE;
                $hasDiscounts = !empty($reservation->discounts) || $reservation->special_discount || ($reservation->donation && !$useFreePrice);
                $finalTotal = $reservation->finalPrice();
            @endphp

            <!-- Pricing -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Pricing') }}</h2>
                <dl class="space-y-2 text-sm">
                    @if($hasDiscounts)
                        <div class="flex justify-between">
                            <dt class="text-gray-600 font-semibold">{{ __('Initial total') }}</dt>
                            <dd class="text-gray-900 font-semibold">{{ currency($totalPrice, $owner) }}</dd>
                        </div>

                        @foreach($reservation->discounts ?? [] as $discount)
                            <div class="flex justify-between">
                                <dt class="text-green-600">{{ $discount[1] }}</dt>
                                <dd class="text-green-600 font-medium">{{ currency(-$discount[2], $owner) }}</dd>
                            </div>
                        @endforeach

                        @if($reservation->special_discount)
                            <div class="flex justify-between">
                                <dt class="text-green-600">{{ __('Special discount') }}</dt>
                                <dd class="text-green-600 font-medium">{{ currency(-$reservation->special_discount, $owner) }}</dd>
                            </div>
                        @endif

                        @if($reservation->donation && !$useFreePrice)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Additional donation') }}</dt>
                                <dd class="text-gray-900 font-medium">{{ currency($reservation->donation, $owner) }}</dd>
                            </div>
                        @endif
                    @endif

                    @if($useFreePrice)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Total recommended rate') }}</dt>
                            <dd class="text-gray-900 font-medium">{{ currency($reservation->recommendedPrice(), $owner) }}</dd>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-gray-200">
                            <dt class="text-gray-900 font-semibold">{{ __('Free rate') }}</dt>
                            <dd class="text-gray-900 font-bold">{{ currency($finalTotal, $owner) }}</dd>
                        </div>
                    @else
                        <div class="flex justify-between pt-2 border-t border-gray-200">
                            <dt class="text-gray-900 font-semibold">{{ __('Total (incl. VAT)') }}</dt>
                            <dd class="text-gray-900 font-bold">{{ currency($finalTotal, $owner) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Documents -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Documents') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reservations.prebook.pdf', $reservation->hash) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        {{ __('Pre-booking') }}
                    </a>
                    @if($invoice)
                        <a href="{{ route('reservations.invoice.pdf', $reservation->hash) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            {{ __('Invoice') }} {{ $invoice->number }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Admin actions -->
            @if($canCancel || $canEdit || $canConfirm)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-yellow-800 mb-4">{{ __('Administration') }}</h3>
                    <div class="space-y-2">
                        @if($canEdit)
                            <a href="{{ route('reservations.edit', [$reservation] + redirect_back_params()) }}" class="block w-full btn btn-secondary text-center">
                                {{ __('Edit') }}
                            </a>
                        @endif
                        @if($canConfirm)
                            <button type="button" onclick="openConfirmModal()" class="block w-full btn btn-primary text-center">
                                {{ __('Confirm request directly') }}
                            </button>
                        @endif
                        @if($canCancel)
                            <button type="button" onclick="openCancelModal()" class="block w-full btn btn-delete text-center">
                                {{ __('Cancel reservation') }}
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Tenant -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Tenant') }}</h3>
                <div class="space-y-3">
                    @if($user->canAccessContact($tenant))
                        <a href="{{ route('contacts.edit', [$tenant] + redirect_back_params()) }}">
                            <p class="inline-flex text-sm font-medium text-gray-900">{{ $tenant->display_name() }}</p>
                        </a>
                    @else
                        <p class="inline-flex text-sm font-medium text-gray-900">{{ $tenant->display_name() }}</p>
                    @endif

                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                        {{ $tenant->type->label() }}
                    </span>

                    @if($tenant->email)
                        <a href="mailto:{{ $tenant->email }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <i class="fa-regular fa-envelope"></i>
                            {{ $tenant->email }}
                        </a>
                    @endif

                    @if($tenant->phone)
                        <a href="tel:{{ $tenant->phone }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <x-icons.phone />
                            {{ $tenant->phone }}
                        </a>
                    @endif

                    @if($tenant->street || $tenant->city)
                        <p class="text-sm text-gray-600">
                            @if($tenant->street){{ $tenant->street }}<br>@endif
                            @if($tenant->zip || $tenant->city){{ $tenant->zip }} {{ $tenant->city }}@endif
                        </p>
                    @endif
                </div>
            </div>

            <!-- Invoice -->
            @if($invoice)
                @php $invoiceStatus = $invoice->computed_status; @endphp
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Invoice') }}</h3>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $invoiceStatus->color() }}">
                            {{ $invoiceStatus->label() }}
                        </span>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Number') }}</dt>
                            <dd class="text-gray-900 font-medium">
                                <a href="{{ route('reservations.invoice.pdf', $reservation->hash) }}" target="_blank" class="link-primary">
                                    {{ $invoice->number }}
                                </a>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Amount') }}</dt>
                            <dd class="text-gray-900 font-medium">{{ currency($invoice->amount, $owner) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Issued on') }}</dt>
                            <dd class="text-gray-900 font-medium">{{ $invoice->issued_at?->format('d.m.Y') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Due date') }}</dt>
                            <dd class="text-gray-900 font-medium">{{ $invoice->due_at?->format('d.m.Y') ?? '—' }}</dd>
                        </div>
                        @if($invoice->reminder_count > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">{{ __('Reminders') }}</dt>
                                <dd class="text-orange-600 font-medium">{{ $invoice->reminder_count }}</dd>
                            </div>
                        @endif
                        @if($invoice->paid_at)
                            <div class="flex justify-between pt-2 border-t border-gray-100">
                                <dt class="text-green-600">{{ __('Paid on') }}</dt>
                                <dd class="text-green-600 font-medium">{{ $invoice->paid_at->format('d.m.Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            <!-- Owner -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Owner') }}</h3>
                <div class="space-y-3">
                    @can('update', $owner)
                        <a href="{{ route('owners.edit', $owner) }}">
                            <p class="text-sm font-medium mb-2 text-gray-900">{{ $owner->contact->display_name() }}</p>
                        </a>
                    @else
                        <p class="text-sm font-medium mb-2 text-gray-900">{{ $owner->contact->display_name() }}</p>
                    @endcan

                    @if($owner->contact->email && !$owner->hide_email)
                        <a href="mailto:{{ $owner->contact->email }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <i class="fa-regular fa-envelope"></i>
                            {{ $owner->contact->email }}
                        </a>
                    @endif

                    @if($owner->contact->phone && !$owner->hide_phone)
                        <a href="tel:{{ $owner->contact->phone }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <x-icons.phone />
                            {{ $owner->contact->phone }}
                        </a>
                    @endif

                    @if($owner->website)
                        <a href="{{ $owner->website }}" target="_blank"
                           class="inline-flex items-center gap-2 mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm text-white font-medium transition">
                            <i class="fa-solid fa-link"></i>
                            {{ __('Visit website') }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Reservation metadata -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Reservation') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('Created on') }}</dt>
                        <dd class="text-gray-900 font-medium">{{ $reservation->created_at->format('d.m.Y H:i') }}</dd>
                    </div>
                    @if($reservation->confirmed_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Confirmed on') }}</dt>
                            <dd class="text-gray-900 font-medium">{{ $reservation->confirmed_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    @endif
                    @if($reservation->confirmedBy)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Confirmed by') }}</dt>
                            <dd class="text-gray-900 font-medium">
                                @if($user->canAccessUser($reservation->confirmedBy))
                                    <a href="{{ route('users.edit', $reservation->confirmedBy) }}" class="hover:underline">
                                        {{ $reservation->confirmedBy->name }}
                                    </a>
                                @else
                                    {{ $reservation->confirmedBy->name }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    {{-- Preuve d'acceptation des CG : date, version et provenance.
                         Réservée aux responsables (l'IP est une donnée personnelle). --}}
                    @if($reservation->terms_accepted_at && $canManage)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Terms accepted on') }}</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $reservation->terms_accepted_at->format('d.m.Y H:i') }}
                                @if($reservation->terms_version)
                                    <span class="text-gray-500">({{ __('version') }} {{ $reservation->terms_version }})</span>
                                @endif
                                @if($reservation->terms_ip)
                                    <span class="block text-xs text-gray-500">{{ __('from IP') }} {{ $reservation->terms_ip }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if($reservation->custom_message)
                        <div class="pt-2 border-t border-gray-100">
                            <dt class="text-gray-500 mb-1">{{ __('Custom message') }}</dt>
                            <dd class="text-gray-700 text-sm whitespace-pre-line">{{ $reservation->custom_message }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
<!-- Modal de chargement -->
<div id="loader-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 shadow-xl flex flex-col items-center gap-4">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
        <p class="text-gray-700 font-medium">{{ __('Processing...') }}</p>
    </div>
</div>

@if($canConfirm)
<!-- Modal de confirmation directe -->
<div id="confirm-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('Confirm request directly') }}</h3>
        <form id="confirm-form" method="POST" action="{{ route('reservations.confirm', [$reservation] + redirect_back_params()) }}">
            @csrf
            <div class="mb-4">
                <label for="confirm-custom-message" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Custom message (sent by email with the reservation confirmation)') }}
                </label>
                <textarea name="custom_message"
                          id="confirm-custom-message"
                          class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                          rows="3"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeConfirmModal()" class="btn btn-secondary">
                    {{ __('Back') }}
                </button>
                <button type="submit" class="btn btn-primary">
                    {{ __('Confirm') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canCancel)
<!-- Modal d'annulation -->
<div id="cancel-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('Cancel reservation') }}</h3>
        <form id="cancel-form" method="POST" action="{{ route('reservations.cancel', [$reservation] + redirect_back_params()) }}">
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
                <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">
                    {{ __('Back') }}
                </button>
                <button type="submit" class="btn btn-delete">
                    {{ __('Confirm cancellation') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
    function showLoader() {
        document.getElementById('loader-modal').classList.remove('hidden');
    }

    @if($canConfirm)
    function openConfirmModal() {
        document.getElementById('confirm-modal').classList.remove('hidden');
    }
    function closeConfirmModal() {
        document.getElementById('confirm-modal').classList.add('hidden');
    }
    document.getElementById('confirm-form').addEventListener('submit', function() {
        closeConfirmModal();
        showLoader();
    });
    document.getElementById('confirm-modal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirmModal();
    });
    @endif

    @if($canCancel)
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
    document.getElementById('cancel-send-email').addEventListener('change', updateCancelReasonState);
    document.getElementById('cancel-form').addEventListener('submit', function() {
        closeCancelModal();
        showLoader();
    });
    document.getElementById('cancel-modal').addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });
    @endif

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            @if($canConfirm)
            closeConfirmModal();
            @endif
            @if($canCancel)
            closeCancelModal();
            @endif
        }
    });
</script>
@endsection
