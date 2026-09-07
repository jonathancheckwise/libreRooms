@extends('layouts.app')

@section('title', __('Door posters'))

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">

    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.5rem;font-weight:700;margin:0">{{ __('Door posters') }}</h1>
            <p style="color:#6b7280;margin:.25rem 0 0">{{ __('One QR code per room (links to its planning) plus a global one for the entrance. Print and post them.') }}</p>
        </div>
        <button type="button" onclick="window.print()"
                style="background:#C8861F;color:#fff;border:none;border-radius:8px;padding:.6rem 1.1rem;font-weight:600;cursor:pointer">
            {{ __('Print') }}
        </button>
    </div>

    <div class="posters-grid">
        {{-- Affiche globale (entrée) --}}
        <div class="poster" style="border-color:#C8861F">
            <div class="poster-kicker">{{ __('Entrance') }}</div>
            <div class="poster-title">{{ __('All rooms') }}</div>
            <a class="poster-qr" href="{{ route('planning.poster') }}" target="_blank" rel="noopener"
               data-url="{{ route('planning.index') }}" title="{{ __('Open printable PDF') }}"></a>
            <div class="poster-hint">{{ __('Scan to see live availability and book') }}</div>
            <a class="poster-pdf no-print" href="{{ route('planning.poster') }}" target="_blank" rel="noopener">⬇ {{ __('Printable PDF') }}</a>
        </div>

        @foreach($rooms as $room)
            <div class="poster">
                <div class="poster-kicker">{{ __('La Pépite') }}</div>
                <div class="poster-title">{{ $room->name }}</div>
                <a class="poster-qr" href="{{ route('rooms.poster', $room) }}" target="_blank" rel="noopener"
                   data-url="{{ route('rooms.planning', $room) }}" title="{{ __('Open printable PDF') }}"></a>
                <div class="poster-hint">{{ __('Scan to see availability and book this room') }}</div>
                <a class="poster-pdf no-print" href="{{ route('rooms.poster', $room) }}" target="_blank" rel="noopener">⬇ {{ __('Printable PDF') }}</a>
            </div>
        @endforeach
    </div>
</div>

<style>
    .posters-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
    .poster {
        background:#fff; border:2px solid #e5e7eb; border-radius:14px;
        padding:1.5rem 1rem; text-align:center; break-inside:avoid;
        display:flex; flex-direction:column; align-items:center; gap:.5rem;
    }
    .poster-kicker { font-size:.75rem; letter-spacing:.08em; text-transform:uppercase; color:#9ca3af; }
    .poster-title { font-size:1.4rem; font-weight:700; }
    .poster-qr { margin:.5rem 0; display:block; cursor:pointer; }
    .poster-qr svg { width:180px; height:180px; }
    .poster-hint { font-size:.85rem; color:#6b7280; max-width:220px; }
    .poster-pdf { font-size:.85rem; color:#2563eb; text-decoration:none; font-weight:600; margin-top:.25rem; }
    .poster-pdf:hover { text-decoration:underline; }
    @media print {
        .no-print, nav, header, footer { display:none !important; }
        .posters-grid { grid-template-columns:repeat(2,1fr); gap:1.5rem; }
        .poster { border-color:#111 !important; page-break-inside:avoid; }
    }
</style>

@once
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
@endonce
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.qrcode) return;
    document.querySelectorAll('.poster-qr').forEach(function (box) {
        try {
            var qr = qrcode(0, 'M');
            qr.addData(box.dataset.url);
            qr.make();
            box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 1 });
        } catch (e) { /* QR non bloquant */ }
    });
});
</script>
@endsection
