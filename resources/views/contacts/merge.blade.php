@extends('layouts.app')

@section('title', __('Merge entity') . ' — ' . $contact->display_name())

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">

    <div style="margin-bottom:1rem">
        <a href="{{ route('contacts.index', ['view' => 'all']) }}" style="color:#6b7280;text-decoration:none;font-size:.9rem">← {{ __('All contacts') }}</a>
    </div>

    <h1 style="font-size:1.6rem;font-weight:700;margin:0 0 .25rem">{{ __('Merge entity') }}</h1>
    <p style="color:#6b7280;margin:0 0 1.5rem">
        {{ __('Merging is reversible and non-destructive: nothing is deleted, no reservation is moved. The absorbed entity becomes an alias and its invoicing is attached to the surviving one.') }}
    </p>

    @if (session('error'))
        <div style="background:#fee2e2;color:#991b1b;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem">{{ session('error') }}</div>
    @endif

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.25rem;margin-bottom:1.5rem">
        <p style="margin:0 0 .25rem;font-size:.85rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">{{ __('Entity to merge') }}</p>
        <p style="margin:0;font-weight:700;font-size:1.1rem">{{ $contact->display_name() }}</p>
        <p style="margin:.2rem 0 0;color:#6b7280">
            {{ $contact->email ?: '—' }} · {{ trim($contact->zip.' '.$contact->city) ?: '—' }} · {{ $contact->reservations()->count() }} {{ __('reservations') }}
        </p>
    </div>

    @if($contact->isMerged())
        <div style="background:#fef3c7;color:#92400e;padding:.75rem 1rem;border-radius:8px">
            {{ __('This entity is already merged into another one.') }}
            <form method="POST" action="{{ route('contacts.unmerge', $contact) }}" class="inline" style="margin-top:.5rem">
                @csrf
                <button type="submit" class="btn btn-secondary">{{ __('Un-merge') }}</button>
            </form>
        </div>
    @elseif($candidates->isEmpty())
        <p style="color:#6b7280">{{ __('No other entity to merge into.') }}</p>
    @else
        <form method="POST" action="{{ route('contacts.merge', $contact) }}" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.25rem">
            @csrf
            <label for="survivor_id" class="form-element-title" style="display:block;margin-bottom:.4rem;font-weight:600">
                {{ __('Keep (surviving entity) — the invoice address of this one is used') }}
            </label>
            <select name="survivor_id" id="survivor_id" class="form-select" required>
                <option value="">— {{ __('Choose the entity to keep') }} —</option>
                @foreach($candidates as $c)
                    <option value="{{ $c->id }}">
                        {{ $c->display_name() }}@if($c->email) · {{ $c->email }}@endif @if($c->city)· {{ $c->zip }} {{ $c->city }}@endif
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary" style="margin-top:1rem"
                    onclick="return confirm('{{ __('Merge this entity into the selected one? (reversible)') }}')">
                {{ __('Merge') }}
            </button>
        </form>
    @endif

    {{-- Entités déjà fusionnées DANS celle-ci : possibilité de défusionner --}}
    @if($aliases->isNotEmpty())
        <div style="margin-top:2rem">
            <h2 style="font-size:1.1rem;font-weight:700;margin:0 0 .75rem">{{ __('Entities merged into this one') }}</h2>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
                @foreach($aliases as $alias)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem;border-bottom:1px solid #f1f1f1">
                        <div>
                            <span style="font-weight:600">{{ $alias->display_name() }}</span>
                            <span style="color:#6b7280">· {{ $alias->email ?: '—' }} · {{ $alias->reservations()->count() }} {{ __('reservations') }}</span>
                        </div>
                        <form method="POST" action="{{ route('contacts.unmerge', $alias) }}" class="inline">
                            @csrf
                            <button type="submit" class="btn btn-secondary">{{ __('Un-merge') }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
