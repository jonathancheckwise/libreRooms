@extends('layouts.app')

@section('title', __('Planning') . ' — ' . $room->name)

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">

    <div style="margin-bottom:1rem">
        <a href="{{ route('planning.index') }}" style="color:#6b7280;text-decoration:none;font-size:.9rem">← {{ __('All rooms') }}</a>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
        <div>
            <h1 style="font-size:1.6rem;font-weight:700;margin:0">{{ $room->name }}</h1>
            <p style="color:#6b7280;margin:.25rem 0 0">{{ __("Today's schedule. Pick a free slot to book.") }}</p>
        </div>
        @if($room->on_request)
            <a href="{{ route('special-requests.create', ['room' => $room->id]) }}"
               style="background:#C8861F;color:#fff;text-decoration:none;border-radius:8px;padding:.6rem 1.2rem;font-weight:600">
                {{ __('Special request') }}
            </a>
        @else
            <a href="{{ route('reservations.create', $room) }}"
               style="background:#C8861F;color:#fff;text-decoration:none;border-radius:8px;padding:.6rem 1.2rem;font-weight:600">
                {{ __('Reserve this room') }}
            </a>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem">
        @include('rooms._calendar', ['room' => $room, 'initialView' => 'timeGridDay'])
    </div>
</div>
@endsection
