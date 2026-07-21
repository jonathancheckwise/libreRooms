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
