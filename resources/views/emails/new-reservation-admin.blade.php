@extends('emails.layout')

@section('content')
    <h1>{{ __('New reservation request') }}</h1>

    <p>
        {{ __('A reservation request has been made by :tenant for the room :room.', ['tenant' => $reservation->tenant->display_name(), 'room' => $room->name]) }}
    </p>

    <div class="highlight-box">
        <strong>{{ $reservation->title }}</strong>
        @if($reservation->description)
            <br><span style="color: #6b7280;">{{ $reservation->description }}</span>
        @endif
    </div>

    <h2>{{ __('Requested dates') }}</h2>
    <ul>
        @foreach ($reservation->events as $event)
            <li>
                {{ $event->dateString() }}
                <a href="{{ route('reservations.event-ics', ['hash' => $reservation->hash, 'uid' => $event->uid]) }}" style="font-size: 12px;">(ics)</a>
            </li>
        @endforeach
    </ul>

    <p>{{ __('Please review the request to confirm or reject it:') }}</p>

    <p>
        <a href="{{ route('reservations.show', $reservation) }}" class="btn">{{ __('View the request') }}</a>
    </p>
@endsection
