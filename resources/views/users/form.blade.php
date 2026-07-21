@extends('layouts.app')

@section('title', isset($user) ? __('Edit user') : __('New user'))

@section('content')
<div class="max-w-4xl mx-auto py-6">
    @if($user?->id === auth()->user()->id)
        <div class="form-header">
            <h1 class="form-title">{{ __('My profile') }}</h1>
            <p class="form-subtitle">{{ __('Manage your personal information') }}</p>
        </div>
    @else
        <div class="form-header">
            <h1 class="form-title">
                {{ isset($user) ? __('Edit user') : __('New user') }}
            </h1>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <form id="user-form" method="POST" action="{{ isset($user) ? route('users.update', [$user] + redirect_back_query()) : route('users.store', redirect_back_query()) }}" class="styled-form">
            @csrf
            @if(isset($user))
                @method('PUT')
            @endif

            {{-- La Pépite : résumé d'erreurs bien visible en haut du formulaire --}}
            @if($errors->any())
                <div role="alert" style="background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:14px 16px;margin-bottom:18px;color:#991b1b">
                    <strong>{{ __('Please fix the following before saving:') }}</strong>
                    <ul style="margin:8px 0 0 20px;list-style:disc">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('users.partials._basic_fields', [
                'user' => $user,
                'showPasswordFields' => true,
            ])

            <!-- Permissions -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Permissions') }}</h3>

                <fieldset class="form-element">
                    <div class="form-field">
                        <label class="flex items-center gap-2">
                            <input
                                type="hidden"
                                name="is_global_admin"
                                value="0"
                            >
                            <input
                                type="checkbox"
                                name="is_global_admin"
                                value="1"
                                @checked(old('is_global_admin', $user?->is_global_admin))
                                @disabled($user?->id === auth()->id())
                            >
                            <span class="font-medium">{{ __('Global administrator') }}</span>
                        </label>
                        @if($user?->id === auth()->id())
                            <small class="text-amber-600">{{ __('You cannot remove your own global administrator status') }}</small>
                        @else
                            <small class="text-gray-600">{{ __('The global administrator has access to all application features') }}</small>
                        @endif
                    </div>
                </fieldset>
            </div>

            <!-- Assignation aux propriétaires -->
            <div class="form-group">
                <h3 class="form-group-title">{{ __('Owner assignment') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('Select the owners and the role of the user for each') }}</p>

                <div id="owner-assignments" class="space-y-3">
                    @php
                        // Build owner assignments from old() (priority) or existing user data
                        $oldOwners = old('owners', []);
                        $userOwners = isset($user) ? $user->owners->keyBy('id') : collect();

                        // Convert old() array to collection keyed by id (cast to int for consistent comparison)
                        $oldOwnersCollection = collect($oldOwners)->keyBy(fn($item) => (int) $item['id']);
                    @endphp

                    @foreach($owners as $owner)
                        @php
                            // Check if this owner is assigned (from old() first, then user data)
                            $ownerId = (int) $owner->id;
                            if ($oldOwnersCollection->has($ownerId)) {
                                $isAssigned = true;
                                $role = $oldOwnersCollection->get($ownerId)['role'] ?? 'viewer';
                            } elseif ($userOwners->has($ownerId)) {
                                $isAssigned = true;
                                $role = $userOwners->get($ownerId)->pivot->role;
                            } else {
                                $isAssigned = false;
                                $role = 'viewer';
                            }
                        @endphp
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-md">
                            <label class="flex items-center gap-2 flex-1">
                                <input
                                    type="checkbox"
                                    name="owner_checkbox_{{ $owner->id }}"
                                    value="1"
                                    @checked($isAssigned)
                                    onchange="toggleOwnerRole(this, {{ $owner->id }})"
                                >
                                <span class="font-medium text-sm">{{ $owner->contact->display_name() }}</span>
                            </label>

                            <div class="flex gap-2 owner-role-{{ $owner->id }}" style="display: {{ $isAssigned ? 'flex' : 'none' }}">
                                @foreach($ownerRoles as $ownerRole)
                                    <label class="flex items-center gap-1">
                                        <input
                                            type="radio"
                                            name="owner_role_{{ $owner->id }}"
                                            value="{{ $ownerRole->value }}"
                                            @checked($role === $ownerRole->value)
                                        >
                                        <span class="text-sm">{{ $ownerRole->label_short() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Entreprises (La Pépite) : rattachement de l'utilisateur --}}
            <div class="form-section mt-6">
                <h2 class="text-lg font-semibold mb-2">{{ __('Companies') }}</h2>
                <p class="text-sm text-gray-600 mb-4">{{ __('Companies this user belongs to (grants access to rooms open to those companies).') }}</p>
                @php
                    $userCompanyIds = isset($user) ? $user->companies->pluck('id')->all() : [];
                    $oldCompanies = collect(old('companies', $userCompanyIds))->map(fn ($v) => (int) $v)->all();
                @endphp
                @forelse($companies as $company)
                    <label class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="companies[]" value="{{ $company->id }}"
                            @checked(in_array($company->id, $oldCompanies))>
                        <span>{{ $company->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500">
                        {{ __('No company yet.') }}
                        <a href="{{ route('companies.create') }}" class="link-primary">{{ __('Create one') }}</a>.
                    </p>
                @endforelse
            </div>

            <div class="btn-group justify-end mt-6">
                <a href="{{ redirect_back_url('users.index') }}" class="btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    {{ isset($user) ? __('Update') : __('Create') }}
                </button>
                @if(isset($user) && $user->id !== auth()->id())
                    <button type="button" onclick="confirmDeleteAccount()" class="btn btn-delete">
                        {{ __('Delete account') }}
                    </button>
                @endif
            </div>
        </form>
        @if(isset($user) && $user->id !== auth()->id())
            <form id="delete-account-form" action="{{ route('users.destroy', [$user] + redirect_back_query()) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif

    </div>
    @if($user?->id === auth()->id())
        @include('users.partials._admin_cannot_delete_msg')
    @endif
</div>

<script>
    function confirmDeleteAccount() {
        if (confirm('{{ __('Are you absolutely sure you want to delete this account? This action cannot be undone and all related data will be lost.') }}')) {
            document.getElementById('delete-account-form').submit();
        }
    }

    function toggleOwnerRole(checkbox, ownerId) {
        const roleDiv = document.querySelector('.owner-role-' + ownerId);
        if (checkbox.checked) {
            roleDiv.style.display = 'flex';
            // Ensure at least one radio is selected
            const radios = roleDiv.querySelectorAll('input[type="radio"]');
            const hasChecked = Array.from(radios).some(r => r.checked);
            if (!hasChecked && radios.length > 0) {
                radios[0].checked = true;
            }
        } else {
            roleDiv.style.display = 'none';
        }
    }

    // Transform checkbox/radio data to proper format before submit
    const userForm = document.getElementById('user-form');
    userForm.addEventListener('submit', function(e) {

        // Remove old hidden inputs if any
        document.querySelectorAll('input[name^="owners["]').forEach(el => el.remove());

        let ownerIndex = 0;
        // Process all owner checkboxes
        document.querySelectorAll('input[name^="owner_checkbox_"]').forEach(checkbox => {
            if (checkbox.checked) {
                const ownerId = checkbox.name.replace('owner_checkbox_', '');
                const checkedRadio = document.querySelector(`input[name="owner_role_${ownerId}"]:checked`);

                if (checkedRadio) {
                    // console.log('Adding owner ' + ownerId + ' with role ' + checkedRadio.value);

                    // Add hidden inputs for owners array
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = `owners[${ownerIndex}][id]`;
                    idInput.value = ownerId;
                    userForm.appendChild(idInput);

                    const roleInput = document.createElement('input');
                    roleInput.type = 'hidden';
                    roleInput.name = `owners[${ownerIndex}][role]`;
                    roleInput.value = checkedRadio.value;
                    userForm.appendChild(roleInput);

                    ownerIndex++;
                } else {
                    console.warn('No role selected for owner ' + ownerId + ', defaulting to viewer');
                    // Default to viewer if no radio is checked (shouldn't happen normally)
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = `owners[${ownerIndex}][id]`;
                    idInput.value = ownerId;
                    userForm.appendChild(idInput);

                    const roleInput = document.createElement('input');
                    roleInput.type = 'hidden';
                    roleInput.name = `owners[${ownerIndex}][role]`;
                    roleInput.value = 'viewer';
                    userForm.appendChild(roleInput);

                    ownerIndex++;
                }
            }
        });
    });
</script>
@endsection
