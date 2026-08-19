@extends('layouts.app')

@section('title', __('Available rooms'))

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('Rooms') }}</h1>

        @include('rooms._submenu', ['view' => $view])

        @cannot('viewAdmin', App\Models\Room::class)
            <p class="mt-2 text-sm text-gray-600">{{ __('List of all rooms available for reservation') }}</p>
        @endcannot
    </div>

    <!-- Filtres -->
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <form method="GET" action="{{ route('rooms.index') }}" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="display" value="{{ $display }}">

            <div class="flex-1 min-w-[200px]">
                <label for="owner_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Owner') }}</label>
                <select name="owner_id" id="owner_id" class="form-select">
                    <option value="">{{ __('All owners') }}</option>
                    @foreach($owners as $owner)
                        <option value="{{ $owner->id }}" {{ request('owner_id') == $owner->id ? 'selected' : '' }}>
                            {{ $owner->contact->display_name() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Filter') }}
                </button>
                @if(request()->has('owner_id'))
                    <a href="{{ route('rooms.index', ['view' => $view, 'display' => $display]) }}" class="btn btn-secondary">
                        {{ __('Reset') }}
                    </a>
                @endif
            </div>

            <!-- Display mode switcher -->
            <div class="flex items-center gap-2 ml-auto">
                <a href="{{ route('rooms.index', array_merge(request()->except('display'), ['display' => 'cards'])) }}"
                   class="p-2 rounded-lg transition {{ $display === 'cards' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                   title="{{ __('Card view') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                </a>
                <a href="{{ route('rooms.index', array_merge(request()->except('display'), ['display' => 'list'])) }}"
                   class="p-2 rounded-lg transition {{ $display === 'list' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                   title="{{ __('List view') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </a>
            </div>
        </form>
    </div>

    @if($display === 'cards')
        <!-- Vue cartes -->
        @if($rooms->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                {{ __('No room found') }}
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($rooms as $room)
                    <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col hover:shadow-lg transition-shadow">
                        <!-- Image -->
                        <div class="relative h-48 bg-gray-100">
                            <a href="{{ route('rooms.show', $room) }}">
                            @if($room->images->first())
                                <img
                                    src="{{ $room->images->first()->url }}"
                                    alt="{{ $room->name }}"
                                    class="w-full h-full object-cover"
                                >
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                            </a>

                            <!-- Badges -->
                            @if($view === 'mine')
                                <div class="absolute top-2 left-2 flex gap-1">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $room->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $room->active ? __('Active_room') : __('Inactive_room') }}
                                    </span>
                                </div>
                            @endif

                        </div>

                        <!-- Content -->
                        <div class="p-4 flex-1 flex flex-col">
                            <h3 class="font-semibold text-lg text-gray-900 mb-1 flex items-center gap-2">
                                <a href="{{ route('rooms.show', $room) }}">
                                {{ $room->name }}
                                </a>
                                @if(!$room->is_public)
                                    <span class="tooltip-container tooltip-right">
                                        <i class="fa-solid fa-user-lock text-sm"></i>
                                        <span class="tooltip-content">
                                            {{ __('Private') }}
                                        </span>
                                    </span>
                                @endif
                                @if($room->hasAddress())
                                    <span class="tooltip-container tooltip-right">
                                        <x-icons.location-dot class="w-4 h-4 text-gray-400"/>
                                        <span class="tooltip-content">
                                            @if($room->street){!! nl2br(e($room->street)) !!}<br>@endif
                                            {{ $room->postal_code }} {{ $room->city }}
                                        </span>
                                    </span>
                                @endif
                            </h3>

                            <p class="text-sm text-gray-500 mb-2">
                                @if($user?->can('update', $room->owner))
                                    <a href="{{ route('owners.edit', $room->owner) }}" class="hover:text-gray-700">
                                        {{ $room->owner->contact->display_name() }}
                                    </a>
                                @else
                                    {{ $room->owner->contact->display_name() }}
                                @endif
                            </p>

                            @if($room->description)
                                <p class="text-sm text-gray-600 mb-4 line-clamp-3">{{ Str::limit($room->description, 150) }}</p>
                            @endif

                            <div class="mt-auto flex items-center justify-between gap-2">
                                <a href="{{ route('rooms.show', $room) }}" class="btn btn-primary text-sm">
                                    {{ __('More info') }}
                                </a>
                                <div class="flex items-center justify-between gap-2 text-2xl">
                                    @if($room->active)
                                        <!-- There is a bypass for global_admins in can directives, we need to check that the room is active -->
                                        @can('reserve', $room)
                                            <a href="{{ route('reservations.create', [$room] + redirect_back_params()) }}" class="link-success" title="{{ __('Book this room') }}">
                                                <x-action-icon action="book" />
                                            </a>
                                        @endcan
                                        @guest
                                            @if($room->members_only && $room->bookable)
                                                <a href="{{ route('register') }}" class="link-primary" title="{{ __('Reserved for members — become a member') }}">🔒</a>
                                            @endif
                                        @endguest
                                    @endif
                                    @can('manageUsers', $room)
                                        <a href="{{ route('rooms.users.index', [$room] + redirect_back_params()) }}" class="link-primary" title="{{ __('Users') }}">
                                            <x-action-icon action="users" />
                                        </a>
                                    @endcan

                                    @can('update', $room)
                                        <a href="{{ route('rooms.edit', [$room] + redirect_back_params()) }}" class="link-primary" title="{{ __('Edit') }}">
                                            <x-action-icon action="edit" />
                                        </a>

                                        <form action="{{ route('rooms.destroy', [$room] + redirect_back_params()) }}" method="POST"
                                              onsubmit="return confirm('{{ __('Are you sure you want to delete this room? This action cannot be undone.') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="link-danger" title="{{ __('Delete') }}">
                                                <x-action-icon action="delete" />
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <!-- Vue liste (tableau) -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Name') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Owner') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hide-on-mobile">
                            {{ __('Description') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        </th>
                        @if($view === 'mine')
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                        @endif
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($rooms as $room)
                        <tr class="hover:bg-gray-50 cursor-pointer transition" onclick="toggleDetails({{ $room->id }})">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                <a href="{{ route('rooms.show', $room) }}" onclick="event.stopPropagation()" class="room-name-link">
                                    {{ $room->name }}
                                    <svg class="room-name-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                @if($user?->can('update', $room->owner))
                                    <a href="{{ route('owners.edit', $room->owner) }}" onclick="event.stopPropagation()">
                                        {{ $room->owner->contact->display_name() }}
                                    </a>
                                @else
                                    {{ $room->owner->contact->display_name() }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 hide-on-mobile">
                                {{ Str::limit($room->description, 100) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                @if(!$room->is_public)
                                    <span class="tooltip-container tooltip-right">
                                        <i class="fa-solid fa-user-lock text-sm"></i>
                                        <span class="tooltip-content">
                                            {{ __('Private') }}
                                        </span>
                                    </span>
                                @endif
                            </td>
                            @if($view === 'mine')
                                <td class="px-4 py-3">
                                    <div class="flex gap-1 flex-wrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $room->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $room->active ? __('Active_room') : __('Inactive_room') }}
                                        </span>
                                    </div>
                                </td>
                            @endif
                            <td class="px-4 py-3 text-sm font-medium">
                                <div class="action-group" onclick="event.stopPropagation()">
                                    <a href="{{ route('rooms.show', [$room] + redirect_back_params()) }}" class="link-primary" title="{{ __('View') }}">
                                        <x-action-icon action="view" />
                                    </a>
                                    @if($room->active)
                                        <!-- There is a bypass for global_admins in can directives, we need to check that the room is active -->
                                        @can('reserve', $room)
                                            <a href="{{ route('reservations.create', [$room] + redirect_back_params()) }}" class="link-success" title="{{ __('Book this room') }}">
                                                <x-action-icon action="book" />
                                            </a>
                                        @endcan
                                        @guest
                                            @if($room->members_only && $room->bookable)
                                                <a href="{{ route('register') }}" class="link-primary" title="{{ __('Reserved for members — become a member') }}">🔒</a>
                                            @endif
                                        @endguest
                                    @endif
                                    @can('manageUsers', $room)
                                        <a href="{{ route('rooms.users.index', [$room] + redirect_back_params()) }}" class="link-primary" title="{{ __('Users') }}">
                                            <x-action-icon action="users" />
                                        </a>
                                    @endcan

                                    @can('update', $room)
                                        <a href="{{ route('rooms.edit', [$room] + redirect_back_params()) }}" class="link-primary" title="{{ __('Edit') }}">
                                            <x-action-icon action="edit" />
                                        </a>

                                        <form action="{{ route('rooms.destroy', [$room] + redirect_back_params()) }}" method="POST"
                                              onsubmit="return confirm('{{ __('Are you sure you want to delete this room? This action cannot be undone.') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="link-danger" title="{{ __('Delete') }}">
                                                <x-action-icon action="delete" />
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>

                        <!-- Détails dépliables -->
                        @php
                            $colspan = 3; // Nom, Propriétaire, Description
                            if ($view === 'mine') $colspan++; // Statut
                            if (auth()->user()?->can('viewAdmin', App\Models\Room::class)) $colspan++; // Actions
                        @endphp
                        <tr id="details-{{ $room->id }}" class="details-row hidden">
                            <td colspan="{{ $colspan }}" class="px-4 py-3 bg-slate-50 border-t border-slate-200 w-0">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                                    <!-- Adresse -->
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('Address') }}</h4>
                                        @if($room->hasAddress())
                                            <p class="text-sm text-slate-700">{{ $room->formattedAddress() }}</p>
                                        @else
                                            <p class="text-sm text-slate-400">{{ __('Address not provided') }}</p>
                                        @endif
                                    </div>

                                    <!-- Charte -->
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('Conditions') }}</h4>
                                        @if($room->charter_mode->value === 'text')
                                            <p class="text-sm text-slate-700 line-clamp-4">{{ $room->charter_str }}</p>
                                        @elseif($room->charter_mode->value === 'link')
                                            <a href="{{ $room->charter_str }}" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-md text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                {{ __('View conditions') }}
                                            </a>
                                        @else
                                            <p class="text-sm text-slate-400">{{ __('No conditions') }}</p>
                                        @endif
                                    </div>

                                    <!-- Tarifs -->
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('Prices') }}</h4>
                                        @if($room->price_mode->value === 'fixed')
                                            <div class="bg-white rounded-lg border border-slate-200 p-3 space-y-2">
                                                @if($room->price_hourly)
                                                    <div class="flex justify-between text-sm">
                                                        <span class="text-slate-500">{{ __('Hourly booking') }}</span>
                                                        <span class="text-slate-900 font-medium">{{ currency($room->price_hourly, $room->owner) }} / h</span>
                                                    </div>
                                                @endif
                                                @if($room->price_half_day)
                                                    <div class="flex justify-between text-sm">
                                                        <span class="text-slate-500">{{ __('Half day') }}</span>
                                                        <span class="text-slate-900 font-medium">{{ currency($room->price_half_day, $room->owner) }}</span>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-slate-500">{{ __('Full day') }}</span>
                                                    <span class="text-slate-900 font-medium">{{ currency($room->price_full_day, $room->owner) }}</span>
                                                </div>
                                            </div>
                                        @elseif($room->price_mode->value === 'free')
                                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded">{{ __('Pay what you want') }}</span>
                                        @else
                                            <p class="text-sm text-slate-400">{{ __('Not specified') }}</p>
                                        @endif

                                        @if($room->discounts->where('active', true)->count() > 0)
                                            <div class="mt-3 space-y-1">
                                                @foreach($room->discounts->where('active', true) as $discount)
                                                    @can('manageDiscounts', $room)
                                                        <a href="{{ route('room-discounts.edit', $discount) }}">
                                                    @endcan
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <span class="text-green-600 font-medium">
                                                            @if($discount->type->value === 'fixed')
                                                                {{ currency(-$discount->value, $room->owner) }}
                                                            @else
                                                                -{{ $discount->value }}%
                                                            @endif
                                                        </span>
                                                        <span class="text-slate-600">{{ $discount->name }}</span>
                                                        @if($discount->limit_to_contact_type)
                                                            <span class="text-slate-400 text-xs">({{ $discount->limit_to_contact_type->value === 'individual' ? __('Private') : __('Org.') }})</span>
                                                        @endif
                                                    </div>
                                                    @can('manageDiscounts', $room)
                                                        </a>
                                                    @endcan
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Règles -->
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('Rules') }}</h4>
                                        <dl class="space-y-2 text-sm">
                                            @if($room->reservation_cutoff_days)
                                                <div class="flex">
                                                    <dt class="text-slate-500 mr-4">{{ __('Min. delay') }}</dt>
                                                    <dd class="text-slate-900">{{ __(':days d before', ['days' => $room->reservation_cutoff_days]) }}</dd>
                                                </div>
                                            @endif
                                            @if($room->reservation_advance_limit)
                                                <div class="flex">
                                                    <dt class="text-slate-500 mr-4">{{ __('Max. advance') }}</dt>
                                                    <dd class="text-slate-900">{{ __(':days d in advance', ['days' => $room->reservation_advance_limit]) }}</dd>
                                                </div>
                                            @endif
                                            @if(!$room->reservation_cutoff_days && !$room->reservation_advance_limit)
                                                <p class="text-slate-400">{{ __('No restrictions') }}</p>
                                            @endif
                                        </dl>
                                    </div>

                                    <!-- Options -->
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('Options') }}</h4>
                                        @if($room->options->where('active', true)->count() > 0)
                                            <div class="space-y-2">
                                                @foreach($room->options->where('active', true) as $option)
                                                    @can('manageOptions', $room)
                                                        <a href="{{ route('room-options.edit', $option) }}">
                                                    @endcan
                                                    <div class="bg-white rounded-lg border border-slate-200 p-2">
                                                        <div class="flex justify-between items-start">
                                                            <span class="text-sm text-slate-700">{{ $option->name }}</span>
                                                            <span class="text-sm text-slate-900 font-medium shrink-0 ml-2">{{ currency($option->price, $room->owner) }}</span>
                                                        </div>
                                                        @if($option->description)
                                                            <p class="text-xs text-slate-500 mt-1">{{ $option->description }}</p>
                                                        @endif
                                                    </div>
                                                    @can('manageOptions', $room)
                                                        </a>
                                                    @endcan
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-slate-400">{{ __('No options') }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @php
                            $emptyColspan = 3;
                            if ($view === 'mine') $emptyColspan++;
                            if ($user?->can('viewAdmin', App\Models\Room::class)) $emptyColspan++;
                        @endphp
                        <tr>
                            <td colspan="{{ $emptyColspan }}" class="px-4 py-3 text-center text-gray-500">
                                {{ __('No room found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Pagination -->
    <div class="mt-6">
        {{ $rooms->links() }}
    </div>
</div>

<script>
    function toggleDetails(roomId) {
        const detailsRow = document.getElementById(`details-${roomId}`);
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
</script>
@endsection
