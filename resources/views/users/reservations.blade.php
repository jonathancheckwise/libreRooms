@extends('layouts.app')

@section('title', __('Reservations') . ' — ' . $user->name)

@php
    $statusColor = [
        'pending' => '#b45309',
        'confirmed' => '#059669',
        'cancelled' => '#dc2626',
        'finished' => '#6b7280',
    ];
@endphp

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">

    <div style="margin-bottom:1rem">
        <a href="{{ route('users.index') }}" style="color:#6b7280;text-decoration:none;font-size:.9rem">← {{ __('Users') }}</a>
    </div>

    <h1 style="font-size:1.6rem;font-weight:700;margin:0 0 .25rem">{{ __('Reservations') }} — {{ $user->name }}</h1>
    <p style="color:#6b7280;margin:0 0 1.25rem">{{ $user->email }}</p>

    {{-- Filtre période --}}
    <form method="GET" action="{{ route('users.reservations', $user) }}"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin-bottom:1.25rem">
        <div>
            <label for="from" style="display:block;font-size:.8rem;color:#6b7280">{{ __('From') }}</label>
            <input type="date" id="from" name="from" value="{{ $from }}" class="form-input">
        </div>
        <div>
            <label for="to" style="display:block;font-size:.8rem;color:#6b7280">{{ __('To') }}</label>
            <input type="date" id="to" name="to" value="{{ $to }}" class="form-input">
        </div>
        <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
        @if($from || $to)
            <a href="{{ route('users.reservations', $user) }}" class="btn btn-secondary">{{ __('Reset') }}</a>
        @endif
    </form>

    {{-- Total --}}
    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:.85rem 1.1rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <span style="color:#9a3412">
            {{ trans_choice(':count reservation|:count reservations', $reservations->count(), ['count' => $reservations->count()]) }}
            @if($from || $to) · {{ $from ?: '…' }} → {{ $to ?: '…' }} @endif
        </span>
        <strong style="font-size:1.2rem">{{ __('Total') }} : {{ number_format((float) $total, 2, '.', ' ') }} {{ $currency }}</strong>
    </div>

    {{-- Tableau --}}
    <div style="overflow-x:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px">
        <table style="width:100%;border-collapse:collapse;font-size:.9rem">
            <thead>
                <tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb">
                    <th style="padding:.7rem 1rem">{{ __('Date') }}</th>
                    <th style="padding:.7rem 1rem">{{ __('Room') }}</th>
                    <th style="padding:.7rem 1rem">{{ __('Subject') }}</th>
                    <th style="padding:.7rem 1rem">{{ __('Status') }}</th>
                    <th style="padding:.7rem 1rem;text-align:right">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reservations as $r)
                    @php $ev = $r->events->first(); $sc = $statusColor[$r->status->value] ?? '#6b7280'; @endphp
                    <tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:.7rem 1rem;white-space:nowrap">
                            {{ $ev ? \Illuminate\Support\Carbon::parse($ev->start)->format('d.m.Y H:i') : '—' }}
                        </td>
                        <td style="padding:.7rem 1rem">{{ $r->room?->name ?? '—' }}</td>
                        <td style="padding:.7rem 1rem">{{ $r->title ?: '—' }}</td>
                        <td style="padding:.7rem 1rem">
                            <span style="color:{{ $sc }};font-weight:600">{{ $r->status->label() }}</span>
                        </td>
                        <td style="padding:.7rem 1rem;text-align:right;white-space:nowrap">
                            {{ number_format((float) $r->finalPrice(), 2, '.', ' ') }} {{ $currency }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:1.5rem 1rem;color:#6b7280;text-align:center">{{ __('No reservation for this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p style="color:#9ca3af;font-size:.82rem;margin-top:1rem">
        💡 {{ __('bexio / Stripe billing reconciliation coming soon.') }}
    </p>
</div>
@endsection
