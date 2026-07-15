@extends('layouts.app')

@section('title', __('Users'))

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('User management') }}</h1>
        <nav class="page-submenu">
            <a href="{{ route('users.create', redirect_back_params()) }}" class="page-submenu-item page-submenu-action">
                + {{ __('New user') }}
            </a>
        </nav>
        <p class="mt-2 text-sm text-gray-600">{{ $users->total() }} {{ $users->total() > 1 ? __('users') : __('user') }} {{ __('total') }}</p>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" action="{{ route('users.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Filtre par propriétaire -->
                <div>
                    <label for="owner_id" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('Admin rights') }}
                    </label>
                    <select name="owner_id" id="owner_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Show all') }}</option>
                        <option value="admin" @selected(request('owner_id') == "admin")>{{ __('All admin accounts') }}</option>
                        <option value="not_admin" @selected(request('owner_id') == "not_admin")>{{ __('All accounts without admin rights') }}</option>
                        <option value="global_admin" @selected(request('owner_id') == "global_admin")>{{ __('Global administrators') }}</option>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}" @selected(request('owner_id') == $owner->id)>
                                {{ __('Admin for') }}: {{ $owner->contact->display_name() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Boutons d'action -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        {{ __('Filter') }}
                    </button>
                    @if(request()->hasAny(['owner_id', 'global_admin_only']))
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">
                            {{ __('Reset') }}
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Tableau des utilisateurs -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('User') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Email') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Roles') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="{{ route('users.edit', [$user] + redirect_back_params()) }}">
                                            {{ $user->name }}
                                        </a>
                                    </div>
                                    @if(!$user->email_verified_at)
                                        <div class="text-xs text-yellow-600">{{ __('Email not verified') }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900">
                                @if($user->is_global_admin)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        {{ __('Global admin') }}
                                    </span>
                                @endif

                                @if($user->owners->isNotEmpty())
                                    <div class="mt-1 space-y-1">
                                        @foreach($user->owners as $owner)
                                            @php
                                                $ownerRole = \App\Enums\UserRole::tryFrom($owner->pivot->role);
                                                $badgeColor = match($ownerRole) {
                                                    \App\Enums\UserRole::ADMIN => 'bg-red-100 text-red-800',
                                                    \App\Enums\UserRole::MODERATOR => 'bg-yellow-100 text-yellow-800',
                                                    \App\Enums\UserRole::VIEWER => 'bg-blue-100 text-blue-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <div class="text-xs">
                                                <a href="{{ route('owners.edit', $owner) }}" onclick="event.stopPropagation()"><span class="font-medium">{{ $owner->contact->display_name() }}</span></a>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeColor }}">
                                                    {{ $ownerRole?->label_short() ?? $owner->pivot->role }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!$user->is_global_admin && $user->owners->isEmpty())
                                    <span class="text-xs text-gray-500 italic">{{ __('No role') }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-medium">
                            <div class="action-group">
                                <a href="{{ route('users.edit', [$user] + redirect_back_params()) }}" class="link-primary" title="{{ __('Edit') }}">
                                    <x-action-icon action="edit" />
                                </a>
                                @if($user->id !== auth()->id())
                                    <button type="button" onclick="confirmDelete({{ $user->id }})" class="link-danger" title="{{ __('Delete') }}">
                                        <x-action-icon action="delete" />
                                    </button>
                                    <form id="delete-form-{{ $user->id }}" action="{{ route('users.destroy', [$user] + redirect_back_params()) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                            {{ __('No user found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @endif
</div>

<!-- Modal de confirmation de suppression -->
<script>
    function confirmDelete(userId) {
        if (confirm('{{ __('Are you sure you want to delete this user? This action cannot be undone.') }}')) {
            document.getElementById('delete-form-' + userId).submit();
        }
    }
</script>
@endsection
