@extends('layouts.app')

@section('title', __('Register'))

@section('content')
<div class="auth-container container-full-form">
    <div class="form-header">
        <h1 class="form-title">{{ __('Register') }}</h1>
    </div>

    @if($errors->any())
        <div class="error-messages">
            @foreach($errors->all() as $error)
                <p class="error">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="styled-form">
        @csrf

        <div class="form-group">
            <div class="form-element">
                <label for="name" class="form-element-title">{{ __('Name') }}</label>
                <div class="form-field">
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-element">
                <label for="email" class="form-element-title">{{ __('Email') }}</label>
                <div class="form-field">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                    >
                </div>
            </div>

            <div class="form-element">
                <label for="password" class="form-element-title">{{ __('Password') }}</label>
                <div class="form-field">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >
                </div>
            </div>

            <div class="form-element">
                <label for="password_confirmation" class="form-element-title">{{ __('Confirm password') }}</label>
                <div class="form-field">
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                    >
                </div>
            </div>

            {{-- Déclarations La Pépite : déterminent le tarif appliqué --}}
            <div class="form-element">
                <label for="org_type" class="form-element-title">{{ __('Type of organization') }}</label>
                <div class="form-field">
                    <select id="org_type" name="org_type" required>
                        <option value="" disabled @selected(! old('org_type'))>{{ __('Please select…') }}</option>
                        <option value="non_profit" @selected(old('org_type') === 'non_profit')>{{ __('Non-profit organization') }}</option>
                        <option value="for_profit" @selected(old('org_type') === 'for_profit')>{{ __('For-profit organization') }}</option>
                    </select>
                </div>
            </div>

            <div class="form-element">
                <label for="is_pepite_member" class="form-element-title">{{ __('Are you a member of La Pépite?') }}</label>
                <div class="form-field">
                    <select id="is_pepite_member" name="is_pepite_member">
                        <option value="0" @selected(! old('is_pepite_member'))>{{ __('No') }}</option>
                        <option value="1" @selected(old('is_pepite_member'))>{{ __('Yes') }}</option>
                    </select>
                    <small class="text-gray-600">{{ __('Members get 1 free hour per month and −10% on bookings. Membership is verified by the team.') }}</small>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">{{ __('Sign up') }}</button>
        </div>
    </form>

    <p class="auth-link">
        {{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </p>
</div>
@endsection
