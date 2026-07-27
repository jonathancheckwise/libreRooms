@extends('layouts.app')

@section('title', __('Login'))

@section('content')
<div class="auth-container container-full-form">
    <div class="form-header">
        <h1 class="form-title">{{ __('Login') }}</h1>
    </div>

    @if($errors->any())
        <div class="error-messages">
            @foreach($errors->all() as $error)
                <p class="error">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if($identityProviders->isNotEmpty())
        <!-- OIDC Login Buttons -->
        <div class="styled-form">
            <div class="btn-group">
            @foreach($identityProviders as $provider)
                <a href="{{ route('auth.oidc.redirect', $provider) }}" class="btn btn-secondary items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                    </svg>
                    {{ __('Sign in with :provider', ['provider' => $provider->name]) }}
                </a>
            @endforeach
            </div>
        </div>

        <!-- Divider -->
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">{{ __('OR') }}</span>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="styled-form">
        @csrf
        @if(!empty($intendedUrl))
            <input type="hidden" name="intended_url" value="{{ $intendedUrl }}">
        @endif

        <div class="form-group">
            <div class="form-element">
                <label for="email" class="form-element-title">{{ __('Email') }}</label>
                <div class="form-field">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
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
                <div class="form-field">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                    >
                    <label for="remember">{{ __('Remember me') }}</label>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">{{ __('Sign in') }}</button>
        </div>
    </form>

    <p class="auth-link">
        <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
    </p>

    <p class="auth-link">
        {{ __("Don't have an account yet?") }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
    </p>
</div>
@endsection
