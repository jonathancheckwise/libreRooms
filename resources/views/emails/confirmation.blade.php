@extends('emails.layout')

@section('content')
    <h1>{{ __('Thank you for your reservation at La Pépite!') }}</h1>

    <p>{{ __('Your reservation of the room :room has been confirmed.', ['room' => $room->name]) }}</p>

    <p>{{ __('La Pépite is a non-profit association project: hosting gatherings in our premises keeps the place alive and supports our social activities.') }}</p>

    {{-- Une demande validée telle quelle et une demande retouchée avant validation
         ne se lisent pas pareil : on le dit d'entrée, avec le détail. --}}
    @php($modifications = $reservation->changesBeforeConfirmation())
    @if($modifications->isNotEmpty())
        <div class="highlight-box" style="border-left: 4px solid #d97706;">
            <strong>{{ __('Your request was modified before being confirmed.') }}</strong>
            <ul style="margin: 8px 0 0 0;">
                @foreach($modifications as $change)
                    <li style="margin-bottom: 6px;">
                        <strong>{{ $change->fieldLabel() }}</strong><br>
                        {{ __('before:') }} <span style="text-decoration: line-through; color: #6b7280;">{{ $change->old_value !== null && $change->old_value !== '' ? $change->old_value : __('(empty)') }}</span><br>
                        {{ __('after:') }} {{ $change->new_value !== null && $change->new_value !== '' ? $change->new_value : __('(empty)') }}
                    </li>
                @endforeach
            </ul>
            <p style="margin: 8px 0 0 0; font-size: 14px;">
                {{ __('If this does not match what you expected, reply to this email.') }}
            </p>
        </div>
    @endif

    <div class="highlight-box">
        <strong>{{ $reservation->title }}</strong>
        @if($reservation->event_type)
            <br><span style="color: #6b7280; font-size: 13px;">{{ $reservation->event_type->label() }}</span>
        @endif
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


    {{-- La Pépite : facturation via bexio (pas de facture ici). On rappelle
         seulement le montant FINAL (celui qui sera facturé), sans le prix initial. --}}
    @unless($room->price_mode->value === 'free' && $reservation->finalPrice() == 0)
    <h2>{{ __('Amount') }}</h2>
    <div class="highlight-box">
        <p style="margin: 0;">
            <strong>{{ __('Final amount:') }}</strong> {{ currency($reservation->finalPrice(), $room->owner) }}
        </p>
    </div>
    @endunless

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
