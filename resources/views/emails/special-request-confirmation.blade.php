@extends('emails.layout')

@section('content')
    <h1>{{ __('We have received your request') }}</h1>

    <p>{{ __('Hello') }} {{ $request->name }},</p>

    <p>{{ __('Thank you — your request has been received. The team will get back to you with a quote / proposal as soon as possible.') }}</p>

    <div class="highlight-box">
        <ul>
            @if($request->room)<li>{{ __('Room') }}: {{ $request->room->name }}</li>@endif
            @if($request->desired_dates)<li>{{ __('Desired dates') }}: {{ $request->desired_dates }}</li>@endif
            @if($request->people)<li>{{ __('Number of people') }}: {{ $request->people }}</li>@endif
            <li>{{ __('Catering') }}: {{ $request->catering ? __('Yes') : __('No') }}</li>
        </ul>
        @if($request->purpose)
            <p style="margin-top:.5rem;"><strong>{{ __('Purpose') }}:</strong> {{ $request->purpose }}</p>
        @endif
    </div>

    <p>{{ __('If you need to add anything, simply reply to this email.') }}</p>

    <p>{{ __('La Pépite') }}</p>
@endsection
