@extends('emails.layout')

@section('content')
    <h1>{{ __('Confirmation of your reservation') }}</h1>

    <p>{{ __('Hello') }},</p>

    <p>
        {{ __('Your reservation of the room :room has been confirmed.', ['room' => $room->name]) }}
    </p>

    <div class="highlight-box">
        <strong>{{ $reservation->title }}</strong>
        @if($reservation->description)
            <br><span style="color: #6b7280;">{{ $reservation->description }}</span>
        @endif
    </div>

    <h2>{{ $reservation->events->count() > 1 ? __('Reserved dates') : __('Reserved date') }}</h2>
    <ul>
        @foreach ($reservation->events as $event)
            <li>
                {{ $event->dateString() }}
                <a href="{{ route('reservations.event-ics', ['hash' => $reservation->hash, 'uid' => $event->uid]) }}" style="font-size: 12px;">(ics)</a>
            </li>
        @endforeach
    </ul>

    @if ($room->custom_message)
        <h2>{{ __('Important information') }}</h2>
        <p>{{ $room->custom_message }}</p>
    @endif

    @if ($reservation->custom_message)
        <div class="highlight-box">
            {{ $reservation->custom_message }}
        </div>
    @endif

    @if ($room->secret_message)
        <h2>{{ __('Access codes') }}</h2>
        <p>
            {{ __('You will need access codes. As this information may change, we invite you to check it shortly before your event:') }}
        </p>
        <p>
            <a href="{{ route('reservations.codes', $reservation->hash) }}" class="btn btn-secondary">{{ __('View access codes') }}</a>
        </p>
    @endif

    <h2>{{ __('Invoice') }}</h2>
    <div class="highlight-box">
        <p style="margin: 0;">
            <strong>{{ __('Amount due:') }}</strong> {{ currency($invoice->amount, $room->owner) }}
            @if ($reservation->special_discount > 0)
                <br><span style="color: #059669;">{{ __('A discount of :amount has been applied.', ['amount' => currency($reservation->special_discount, $room->owner)]) }}</span>
            @endif
        </p>
        <p style="margin: 8px 0 0 0; font-size: 14px; color: #6b7280;">
            {{ __('Due date:') }} {{ $invoice->due_at->format('d.m.Y') }}
            <small>({{ $owner->invoice_due_mode->label($owner->invoice_due_days) }})</small>
        </p>
    </div>
    <p>
        <a href="{{ route('reservations.invoice.pdf', $reservation->hash) }}" class="btn">{{ __('Download the invoice') }}</a>
    </p>

    {{-- Art. 1.5 des CG : la confirmation rappelle, à titre informatif, la
         version acceptée lors de la demande. Le lien vise le fichier daté, pas
         le fichier courant qui sera remplacé à la prochaine révision. --}}
    @if ($reservation->terms_accepted_at && $reservation->terms_version)
        <p style="font-size: 14px; color: #6b7280;">
            {{ __('For the record: you accepted the general terms of booking and use of the spaces on :date, in their version of :version.', [
                'date' => $reservation->terms_accepted_at->format('d.m.Y') . ' ' . __('at') . ' ' . $reservation->terms_accepted_at->format('H:i'),
                'version' => $reservation->terms_version,
            ]) }}
            @if ($reservation->termsDocumentUrl())
                <a href="{{ $reservation->termsDocumentUrl() }}">{{ __('Read this version') }}</a>
            @endif
        </p>
    @endif

    <p>{{ __('For any questions, feel free to contact us by replying to this email.') }}</p>

    <p>{{ __('Best regards,') }}</p>
@endsection
