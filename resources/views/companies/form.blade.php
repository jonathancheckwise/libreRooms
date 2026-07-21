@extends('layouts.app')

@section('title', $company ? __('Edit company') : __('New company'))

@section('content')
<div class="max-w-2xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ $company ? __('Edit company') : __('New company') }}</h1>
    </div>

    <div class="bg-white rounded-lg shadow p-6 styled-form">
        <form method="POST" action="{{ $company ? route('companies.update', $company) : route('companies.store') }}">
            @csrf
            @if($company) @method('PUT') @endif

            <div class="form-field">
                <label for="name">{{ __('Company name') }}</label>
                <input type="text" id="name" name="name" required
                       value="{{ old('name', $company?->name) }}"
                       placeholder="{{ __('e.g. Coworkers') }}">
                @error('name') <span class="inf-message" style="color:#b91c1c">{{ $message }}</span> @enderror
            </div>

            <div class="btn-group" style="margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('companies.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
