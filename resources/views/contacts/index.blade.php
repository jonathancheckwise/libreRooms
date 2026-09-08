@extends('layouts.app')

@section('title', __('My contacts'))

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('Contacts') }}</h1>

        @include('contacts._submenu', ['view' => $view, 'user' => $user, 'canViewAll' => $canViewAll])

        @if($view === 'all')
            <p class="mt-2 text-sm text-gray-600">{{ __('List of all accessible contacts') }}</p>
        @else
            <p class="mt-2 text-sm text-gray-600">{{ __('List of all your contacts') }}</p>
        @endif
    </div>

    <!-- Tableau des contacts -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Type') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Entity') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Full name') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Contact') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Shared with') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($contacts as $contact)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            @if($contact->type->value === 'individual')
                                <i class="fa-regular fa-user"></i>
                            @else
                                <i class="fa-regular fa-building"></i>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $contact->entity_name ?: '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $contact->first_name ?: '-' }}<br>
                            {{ $contact->last_name ?: '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="grid grid-cols-2 items-center gap-2">
                                @if($contact->email)
                                    <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:text-blue-800" title="{{ __('Email') }}: {{ $contact->email }}">
                                        <i class="fa-regular fa-envelope"></i>
                                    </a>
                                @endif
                                @if($contact->invoice_email)
                                    <a href="mailto:{{ $contact->invoice_email }}" class="text-green-600 hover:text-green-800" title="{{ __('Billing email') }}: {{ $contact->invoice_email }}">
                                        <i class="fa-regular fa-file-lines"></i>
                                    </a>
                                @endif
                                @if($contact->phone)
                                    <a href="tel:{{ $contact->phone }}" class="text-blue-600 hover:text-blue-800" title="{{ __('Phone') }}: {{ $contact->phone }}">
                                        <x-icons.phone/>
                                    </a>
                                @endif
                                @if($contact->street || $contact->zip || $contact->city)
                                    <span class="tooltip-container">
                                        <x-icons.location-dot/>
                                        <span class="tooltip-content">
                                            @if($contact->street){!! nl2br(e($contact->street)) !!}<br>@endif
                                            {{ $contact->zip }} {{ $contact->city }}
                                        </span>
                                    </span>
                                @endif
                                @if(!$contact->email && !$contact->invoice_email && !$contact->phone && !$contact->street && !$contact->zip && !$contact->city)
                                    <span class="text-gray-400">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @php
                                $otherUsers = $view === 'all'
                                    ? $contact->users
                                    : $contact->users->where('id', '!=', $user->id);
                                $userOwnsContact = $contact->users->contains('id', $user->id);
                            @endphp
                            @if($otherUsers->count() > 0)
                                <div class="flex flex-wrap gap-1">
                                    @foreach($otherUsers as $sharedUser)
                                        @if($user->canAccessUser($sharedUser))
                                            <a href="{{ route('users.edit', $sharedUser) }}" onclick="event.stopPropagation()">
                                                <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
                                                    {{ $sharedUser->name }}
                                                </span>
                                            </a>
                                        @else
                                            <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
                                                {{ $sharedUser->name }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                @if($view === 'all')
                                    <span class="text-gray-400">{{ __('No user') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('Only you') }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-medium">
                            @php
                                $canManage = $userOwnsContact || $user->is_global_admin;
                            @endphp
                            @php
                                $linkedView = $view === 'all' ? 'admin' : 'mine';
                            @endphp
                            <div class="action-group">
                                <a href="{{ route('contacts.reservations', $contact) }}" class="link-primary" title="{{ __('Reservations') }}">
                                    <x-action-icon action="reservations" />
                                </a>
                                <a href="{{ route('invoices.index', ['view' => $linkedView, 'tenant_id' => $contact->id]) }}" class="link-primary" title="{{ __('Invoices') }}">
                                    <x-action-icon action="invoices" />
                                </a>
                                @if($canManage)
                                    <a href="#" class="link-primary" title="{{ __('Share') }}" onclick="event.preventDefault(); showShareModal({{ $contact->id }}, '{{ addslashes($contact->display_name()) }}')">
                                        <x-action-icon action="share" />
                                    </a>
                                    <a href="{{ route('contacts.edit', [$contact] + redirect_back_params()) }}" class="link-primary" title="{{ __('Edit') }}">
                                        <x-action-icon action="edit" />
                                    </a>
                                    <form method="POST" action="{{ route('contacts.destroy', [$contact] + redirect_back_params()) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        @if($userOwnsContact && $contact->users->where('id', '!=', $user->id)->count() > 0)
                                            <button type="submit" class="link-danger" title="{{ __('Remove') }}" onclick="return confirm('{{ __('Are you sure you want to remove this contact from your list? Other users also have access to it, it will not be permanently deleted.') }}')">
                                                <x-action-icon action="remove" />
                                            </button>
                                        @else
                                            <button type="submit" class="link-danger" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Are you sure you want to permanently delete this contact? This action cannot be undone.') }}')">
                                                <x-action-icon action="delete" />
                                            </button>
                                        @endif
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-3 text-center text-gray-500">
                            {{ __('No contact found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $contacts->links() }}
    </div>
</div>

<!-- Modal de partage -->
<div id="shareModal" class="fixed inset-0 bg-gray-600/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Share contact') }}</h3>
        <p class="text-sm text-gray-600 mb-4">
            {{ __('Share') }} "<span id="shareContactName"></span>" {{ __('with another user') }}
        </p>
        <form method="POST" action="#" id="shareForm">
            @csrf
            <div class="mb-4">
                <label for="share_email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('User email') }}
                </label>
                <input type="email" name="email" id="share_email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="email@exemple.com">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="hideShareModal()" class="btn btn-secondary">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary">
                    {{ __('Share') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const redirectQuery = @json(http_build_query(redirect_back_params()));

    function showShareModal(contactId, contactName) {
        document.getElementById('shareContactName').textContent = contactName;
        const query = redirectQuery ? '?' + redirectQuery : '';
        document.getElementById('shareForm').action = `/contacts/${contactId}/share` + query;
        document.getElementById('shareModal').classList.remove('hidden');
        document.getElementById('shareModal').classList.add('flex');
    }

    function hideShareModal() {
        document.getElementById('shareModal').classList.add('hidden');
        document.getElementById('shareModal').classList.remove('flex');
        document.getElementById('share_email').value = '';
    }

    // Close modal on click outside
    document.getElementById('shareModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideShareModal();
        }
    });
</script>
@endsection
