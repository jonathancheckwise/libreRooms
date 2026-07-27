@extends('layouts.app')

@section('title', __('My profile'))

@section('content')
    <div class="max-w-4xl mx-auto py-6">
        <div class="form-header">
            <h1 class="form-title">{{ __('My profile') }}</h1>
            <p class="form-subtitle">{{ __('Manage your personal information') }}</p>
        </div>

        @if(!$user->email_verified_at)
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            {{ __('Your email is not yet verified. Please check your inbox and click the verification link.') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('profile.update') }}" class="styled-form">
                @csrf
                @method('PUT')

                @include('users.partials._basic_fields', [
                    'user' => $user,
                    'showPasswordFields' => false,
                ])

                {{-- Statut Pépite : détermine le tarif appliqué à mes réservations --}}
                <div class="form-group">
                    <h3 class="form-group-title">{{ __('La Pépite status') }}</h3>
                    <fieldset class="form-element">
                        <div class="form-field">
                            <label for="org_type">{{ __('Type of organization') }}</label>
                            <select id="org_type" name="org_type">
                                <option value="" @selected(! old('org_type', $user->org_type))>{{ __('Not specified') }}</option>
                                <option value="non_profit" @selected(old('org_type', $user->org_type) === 'non_profit')>{{ __('Non-profit organization') }}</option>
                                <option value="for_profit" @selected(old('org_type', $user->org_type) === 'for_profit')>{{ __('For-profit organization') }}</option>
                            </select>
                        </div>
                        <div class="form-field mt-3">
                            <label class="flex items-center gap-2">
                                <input type="hidden" name="is_pepite_member" value="0">
                                <input type="checkbox" name="is_pepite_member" value="1" @checked(old('is_pepite_member', $user->is_pepite_member))>
                                <span class="font-medium">{{ __('I am a member of La Pépite') }}</span>
                            </label>
                            <small class="text-gray-600">{{ __('Membership is verified by the team. It grants 1 free hour per month and −10% on bookings.') }}</small>
                        </div>
                    </fieldset>
                </div>

                <div class="form-group">
                    <h3 class="form-group-title">{{ __('Security') }}</h3>
                    <fieldset class="form-element">
                        <div class="form-field">
                            <a href="{{ route('profile.password') }}" class="link-primary">
                                {{ auth()->user()->password ? __('Change password') : __('Set a password') }}
                            </a>
                            @if(!auth()->user()->password)
                                <small class="text-gray-600">{{ __('Setting a password will allow you to log in with your email') }}</small>
                            @endif
                        </div>
                    </fieldset>
                </div>

                <div class="btn-group justify-end mt-6">
                    <a href="{{ route('rooms.index') }}" class="btn btn-secondary">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Update') }}
                    </button>
                    <button type="button" onclick="confirmDeleteAccount()"
                            class="btn bg-red-600 hover:bg-red-700 text-white">
                        {{ __('Delete my account') }}
                    </button>
                </div>
            </form>

            <form id="delete-account-form" action="{{ route('profile.delete') }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <script>
        function confirmDeleteAccount() {
            if (confirm('{{ __('Are you absolutely sure you want to delete your account? This action cannot be undone and all your data will be lost.') }}')) {
                document.getElementById('delete-account-form').submit();
            }
        }
    </script>
@endsection
