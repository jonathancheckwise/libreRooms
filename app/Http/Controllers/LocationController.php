<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lieux prédéfinis (La Pépite) — gabarits d'adresse pour pré-remplir les salles.
 * Accès réservé aux admins globaux (voir routes, middleware 'global_admin').
 */
class LocationController extends Controller
{
    private function rules(?Location $location = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:locations,name'.($location ? ','.$location->id : '')],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function index(): View
    {
        $locations = Location::orderBy('name')->paginate(20);

        return view('locations.index', ['locations' => $locations]);
    }

    public function create(): View
    {
        return view('locations.form', ['location' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($request->validate($this->rules()));

        return redirect()->route('locations.index')->with('success', __('Location created successfully.'));
    }

    public function edit(Location $location): View
    {
        return view('locations.form', ['location' => $location]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($request->validate($this->rules($location)));

        return redirect()->route('locations.index')->with('success', __('Location updated successfully.'));
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('locations.index')->with('success', __('Location deleted successfully.'));
    }
}
