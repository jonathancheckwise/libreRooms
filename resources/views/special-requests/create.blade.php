@extends('layouts.app')

@section('title', __('Special request'))

@section('content')
<div class="max-w-2xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('Special request') }}</h1>
        <p class="text-sm text-gray-600 mt-1">
            {{ __('For rooms available on request (La Big Room, La Place du Village…), catering, or bookings outside the usual hours. The team will get back to you with a quote.') }}
        </p>
    </div>

    @if(session('success'))
        <div role="status" style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;margin-bottom:18px;color:#166534">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div role="alert" style="background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:14px 16px;margin-bottom:18px;color:#991b1b">
            <strong>{{ __('Please fix the following before saving:') }}</strong>
            <ul style="margin:8px 0 0 20px;list-style:disc">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6 styled-form">
        <form method="POST" action="{{ route('special-requests.store') }}">
            @csrf

            <div class="form-field">
                <label for="room_id">{{ __('Room (optional)') }}</label>
                <select id="room_id" name="room_id">
                    <option value="">{{ __('— No specific room / catering only —') }}</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('room_id', $selectedRoomId) == $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-element-row mt-3">
                <div class="form-field">
                    <label for="name">{{ __('Name') }}</label>
                    <input type="text" id="name" name="name" required value="{{ old('name', $user?->name) }}">
                </div>
                <div class="form-field">
                    <label for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" required value="{{ old('email', $user?->email) }}">
                </div>
            </div>

            <div class="form-element-row mt-3">
                <div class="form-field">
                    <label for="phone">{{ __('Phone') }}</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
                </div>
                <div class="form-field">
                    <label for="organization">{{ __('Organization') }}</label>
                    <input type="text" id="organization" name="organization" value="{{ old('organization') }}">
                </div>
            </div>

            <div class="form-field mt-3">
                <label for="desired_dates">{{ __('Desired dates / times') }}</label>
                <textarea id="desired_dates" name="desired_dates" rows="2" placeholder="{{ __('e.g. Saturday 12 September, 2–6 pm') }}">{{ old('desired_dates') }}</textarea>
            </div>

            <div class="form-field mt-3">
                <label for="people">{{ __('Number of people') }}</label>
                <input type="number" id="people" name="people" min="1" value="{{ old('people') }}">
            </div>

            <div class="form-field mt-3">
                <label for="purpose">{{ __('Purpose of the booking') }}</label>
                <textarea id="purpose" name="purpose" rows="2">{{ old('purpose') }}</textarea>
            </div>

            <div class="form-field mt-3">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="catering" value="0">
                    <input type="checkbox" name="catering" value="1" @checked(old('catering'))>
                    <span class="font-medium">{{ __('I would like a catering offer') }}</span>
                </label>
            </div>

            <div class="form-field mt-3">
                <label for="comment">{{ __('Comments') }}</label>
                <textarea id="comment" name="comment" rows="3">{{ old('comment') }}</textarea>
            </div>

            <div class="btn-group" style="margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">{{ __('Send the request') }}</button>
                <a href="{{ route('rooms.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
