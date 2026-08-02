@extends('emails.layout')

@section('content')
    <h1>{{ __('New special request') }}</h1>

    <p>{{ __('A special request has been submitted and needs a quote / manual handling.') }}</p>

    <div class="highlight-box">
        <strong>{{ $request->name }}</strong>
        @if($request->organization)<br><span style="color:#6b7280;">{{ $request->organization }}</span>@endif
        <br>{{ $request->email }}@if($request->phone) · {{ $request->phone }}@endif
    </div>

    <ul>
        @if($request->room)<li>{{ __('Room') }}: {{ $request->room->name }}</li>@endif
        @if($request->desired_dates)<li>{{ __('Desired dates') }}: {{ $request->desired_dates }}</li>@endif
        @if($request->people)<li>{{ __('Number of people') }}: {{ $request->people }}</li>@endif
        <li>{{ __('Catering') }}: {{ $request->catering ? __('Yes') : __('No') }}</li>
    </ul>

    @if($request->purpose)
        <h2>{{ __('Purpose') }}</h2>
        <p>{{ $request->purpose }}</p>
    @endif

    @if($request->comment)
        <h2>{{ __('Comments') }}</h2>
        <p>{{ $request->comment }}</p>
    @endif
@endsection
