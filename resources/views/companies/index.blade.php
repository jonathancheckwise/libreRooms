@extends('layouts.app')

@section('title', __('Companies'))

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="page-header">
        <h1 class="page-header-title">{{ __('Companies') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('Organizations grouping users for room access (e.g. Coworkers, resident associations).') }}</p>
        <nav class="page-submenu">
            <a href="{{ route('companies.create') }}" class="page-submenu-item page-submenu-action">
                + {{ __('New company') }}
            </a>
        </nav>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Members') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Rooms') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($companies as $company)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $company->name }}</td>
                        <td class="px-4 py-3">{{ $company->users_count }}</td>
                        <td class="px-4 py-3">{{ $company->rooms_count }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('companies.edit', $company) }}" class="link-primary">{{ __('Edit') }}</a>
                            <form action="{{ route('companies.destroy', $company) }}" method="POST" class="inline"
                                  onsubmit="return confirm('{{ __('Delete this company?') }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-primary" style="color:#b91c1c">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No company yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
</div>
@endsection
