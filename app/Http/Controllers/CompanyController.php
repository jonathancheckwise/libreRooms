<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion des entreprises (La Pépite).
 * Accès réservé aux admins globaux (voir routes, middleware 'global_admin').
 */
class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::withCount(['users', 'rooms'])->orderBy('name')->paginate(20);

        return view('companies.index', ['companies' => $companies]);
    }

    /**
     * Réservations d'une entreprise (via les contacts de ses utilisateurs) +
     * total, sur une période. Suivi / facturation (bexio à venir).
     */
    public function reservations(Company $company, Request $request): View
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $userIds = $company->users()->pluck('users.id');
        $contactIds = \App\Models\Contact::whereHas('users', fn ($q) => $q->whereIn('users.id', $userIds))
            ->pluck('id');

        $query = \App\Models\Reservation::with(['room', 'events', 'tenant'])
            ->whereIn('tenant_id', $contactIds);
        if ($from) {
            $query->whereHas('events', fn ($e) => $e->whereDate('start', '>=', $from));
        }
        if ($to) {
            $query->whereHas('events', fn ($e) => $e->whereDate('start', '<=', $to));
        }

        $reservations = $query->get()
            ->sortByDesc(fn ($r) => optional($r->events->first())->start)
            ->values();

        $total = $reservations
            ->where('status', '!=', \App\Enums\ReservationStatus::CANCELLED)
            ->sum(fn ($r) => $r->finalPrice());

        return view('companies.reservations', [
            'company' => $company,
            'reservations' => $reservations,
            'total' => $total,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'currency' => app(\App\Models\SystemSettings::class)->currency ?? 'CHF',
        ]);
    }

    public function create(): View
    {
        return view('companies.form', ['company' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
        ]);

        Company::create(['name' => $validated['name']]);

        return redirect()->route('companies.index')
            ->with('success', __('Company created successfully.'));
    }

    public function edit(Company $company): View
    {
        return view('companies.form', ['company' => $company]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:companies,name,'.$company->id],
        ]);

        $company->update(['name' => $validated['name']]);

        return redirect()->route('companies.index')
            ->with('success', __('Company updated successfully.'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', __('Company deleted successfully.'));
    }
}
