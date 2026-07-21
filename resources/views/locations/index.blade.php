@extends('layouts.app')

@section('title', __('Locations'))

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('Locations') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('Predefined addresses (with GPS) to quickly fill in a room.') }}</p>
        <nav class="page-submenu">
            <a href="{{ route('locations.create') }}" class="page-submenu-item page-submenu-action">
                + {{ __('New location') }}
            </a>
        </nav>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Address') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($locations as $location)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $location->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ trim(collect([$location->street, $location->postal_code, $location->city, $location->country])->filter()->implode(', ')) ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('locations.edit', $location) }}" class="link-primary">{{ __('Edit') }}</a>
                            <form action="{{ route('locations.destroy', $location) }}" method="POST" class="inline"
                                  onsubmit="return confirm('{{ __('Delete this location?') }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-primary" style="color:#b91c1c">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">{{ __('No location yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $locations->links() }}</div>
</div>
@endsection
