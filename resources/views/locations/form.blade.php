@extends('layouts.app')

@section('title', $location ? __('Edit location') : __('New location'))

@section('content')
<div class="max-w-2xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ $location ? __('Edit location') : __('New location') }}</h1>
    </div>

    <div class="bg-white rounded-lg shadow p-6 styled-form">
        <form method="POST" action="{{ $location ? route('locations.update', $location) : route('locations.store') }}">
            @csrf
            @if($location) @method('PUT') @endif

            <div class="form-field">
                <label for="name">{{ __('Location name') }}</label>
                <input type="text" id="name" name="name" required value="{{ old('name', $location?->name) }}" placeholder="{{ __('e.g. Pépite Lausanne') }}">
                @error('name') <span style="color:#b91c1c" class="text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="form-field mt-3">
                <label for="street">{{ __('Street') }}</label>
                <input type="text" id="street" name="street" value="{{ old('street', $location?->street) }}">
                @error('street') <span style="color:#b91c1c" class="text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="form-element-row">
                <div class="form-field">
                    <label for="postal_code">{{ __('Postal code') }}</label>
                    <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $location?->postal_code) }}">
                </div>
                <div class="form-field">
                    <label for="city">{{ __('City') }}</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $location?->city) }}">
                </div>
            </div>

            <div class="form-field mt-3">
                <label for="country">{{ __('Country') }}</label>
                <input type="text" id="country" name="country" value="{{ old('country', $location?->country ?? 'Suisse') }}">
            </div>

            <div class="form-element-row">
                <div class="form-field">
                    <label for="latitude">{{ __('Latitude') }}</label>
                    <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude', $location?->latitude) }}" placeholder="46.5197">
                    @error('latitude') <span style="color:#b91c1c" class="text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label for="longitude">{{ __('Longitude') }}</label>
                    <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude', $location?->longitude) }}" placeholder="6.6323">
                    @error('longitude') <span style="color:#b91c1c" class="text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="btn-group" style="margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('locations.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
