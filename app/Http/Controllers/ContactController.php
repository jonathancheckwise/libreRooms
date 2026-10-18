<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Contact;
use App\Models\Room;
use App\Models\User;
use App\Validation\ContactRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    use RedirectsBack;

    /**
     * Display a listing of the resource.
     *
     * Two views available:
     * - "mine": Contacts belonging to the user (default)
     * - "all": Own contacts + contacts with reservations on moderated rooms (moderators+)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $canViewAll = $user->can('viewAdmin', Room::class);
        // Les responsables arrivent sur l'annuaire PARTAGÉ (toutes les entités).
        $view = $canViewAll ? $request->input('view', 'all') : 'mine';

        if ($view === 'all') {
            // Tous les responsables voient TOUTES les entités (racines, alias masqués).
            // Évite les doublons créés par le cloisonnement des contacts entre admins.
            $contacts = Contact::with('users')
                ->withCount('aliases')
                ->roots()
                ->orderByRaw("CASE WHEN type = 'organization' THEN entity_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END ASC")
                ->paginate(15)
                ->appends($request->except('page'));
        } else {
            // Regular view: user's contacts only (hors alias fusionnés)
            $contacts = $user->contacts()
                ->roots()
                ->with('users')
                ->orderByRaw("CASE WHEN type = 'organization' THEN entity_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END ASC")
                ->paginate(15)
                ->appends($request->except('page'));
        }

        return view('contacts.index', [
            'contacts' => $contacts,
            'user' => $user,
            'view' => $view,
            'canViewAll' => $canViewAll,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('contacts.form', ['contact' => null]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // Prepare data (nullify fields based on conditions)
        ContactRules::prepare($request);

        // Validate
        $validated = $request->validate(ContactRules::rules($request));

        // Remove has_invoice_email from validated data (it's not a field in the contacts table)
        unset($validated['has_invoice_email']);
        unset($validated['contact_id']);
        $validated['type'] = $validated['contact_type'];
        unset($validated['contact_type']);

        // Create contact
        $contact = Contact::create($validated);

        // Attach to current user
        $user->contacts()->attach($contact->id);

        return $this->redirectBack('contacts.index')
            ->with('success', __('Contact created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Historique des réservations d'un contact (résa → tenant direct) + total,
     * sur une période. Même vue que par utilisateur / entreprise ; utile pour
     * les externes one-shot qui n'ont pas de compte.
     */
    public function reservations(Contact $contact, Request $request): View
    {
        if (! auth()->user()->canAccessContact($contact)) {
            abort(403, __('You do not have access to this contact.'));
        }

        $from = $request->date('from');
        $to = $request->date('to');

        $query = $contact->reservations()->with(['room', 'events']);
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

        return view('contacts.reservations', [
            'contact' => $contact,
            'reservations' => $reservations,
            'total' => $total,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'currency' => app(\App\Models\SystemSettings::class)->currency ?? 'CHF',
        ]);
    }

    public function edit(Contact $contact): View
    {
        $user = auth()->user();

        // Global admins can edit any contact, otherwise check ownership
        if (! $user->canAccessContact($contact)) {
            abort(403, __('You do not have access to this contact.'));
        }

        return view('contacts.form', [
            'contact' => $contact,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $user = auth()->user();

        // Global admins can update any contact, otherwise check ownership
        if (! $user->canAccessContact($contact)) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('You do not have access to this contact.'));
        }

        // Prepare data
        ContactRules::prepare($request);

        // Validate
        $validated = $request->validate(ContactRules::rules($request));

        // Remove fields not in contacts table
        unset($validated['has_invoice_email']);
        unset($validated['contact_id']);

        // Update contact
        $contact->update($validated);

        return $this->redirectBack('contacts.index')
            ->with('success', __('Contact updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $user = auth()->user();
        $userOwnsContact = $user->contacts()->where('contacts.id', $contact->id)->exists();

        // Global admins can delete any contact, otherwise check ownership
        if (! $user->is_global_admin && ! $userOwnsContact) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('You do not have access to this contact.'));
        }

        // If user owns the contact and other users have access, just detach
        if ($userOwnsContact) {
            $otherUsers = $contact->users()->where('users.id', '!=', $user->id)->exists();

            if ($otherUsers) {
                // Detach only this user from the contact
                $user->contacts()->detach($contact->id);

                return redirect()->back()
                    ->with('success', __('Contact removed from your list.'));
            }
        }

        if ($contact->owners()->exists()) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('This contact cannot be deleted because it is used for an owner.'));
        }

        // Before deleting, check for active reservations
        $activeReservations = $contact->reservations()
            ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::CONFIRMED])
            ->exists();

        if ($activeReservations) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('This contact has active reservations (pending or confirmed). Please cancel them before deleting the contact.'));
        }

        // Check for unpaid/uncancelled invoices
        $unpaidInvoices = $contact->reservations()
            ->whereHas('invoice', function ($query) {
                $query->whereNull('paid_at')
                    ->whereNull('cancelled_at');
            })
            ->exists();

        if ($unpaidInvoices) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('This contact has unpaid invoices. Please mark them as paid or cancel them before deleting the contact.'));
        }

        // Delete the contact entirely
        $contact->delete();

        return redirect()->back()
            ->with('success', __('Contact deleted permanently.'));
    }

    /**
     * Share a contact with another user.
     */
    public function share(Request $request, Contact $contact): RedirectResponse
    {
        $currentUser = auth()->user();

        // Global admins can share any contact, otherwise check ownership
        if (! $currentUser->canAccessContact($contact)) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('You do not have access to this contact.'));
        }

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Find the user by email
        $userToShareWith = User::where('email', $validated['email'])->first();

        if (! $userToShareWith) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('No user found with this email.'));
        }

        if ($userToShareWith->id === $currentUser->id && ! $currentUser->is_global_admin) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('You cannot share a contact with yourself.'));
        }

        // Check if already shared
        if ($userToShareWith->contacts()->where('contacts.id', $contact->id)->exists()) {
            return $this->redirectBack('contacts.index')
                ->with('error', __('This contact is already shared with this user.'));
        }

        // Share the contact
        $userToShareWith->contacts()->attach($contact->id);

        return redirect()->back()
            ->with('success', __('Contact shared with :name.', ['name' => $userToShareWith->name]));
    }

    /* ===================== Fusion / défusion d'entités (dédup) ================
     * Réservé aux responsables. Non destructif : la fusion pose merged_into_id,
     * la défusion le retire. Rien n'est supprimé, aucune réservation déplacée.
     */

    /** Page de fusion d'une entité (choisir le survivant + défusionner ses alias). */
    public function mergeForm(Contact $contact): View
    {
        abort_unless(auth()->user()->can('viewAdmin', Room::class), 403);

        // Entités « racines » candidates au survivant (toutes sauf celle-ci).
        $candidates = Contact::roots()
            ->where('id', '!=', $contact->id)
            ->orderByRaw("CASE WHEN type = 'organization' THEN entity_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END ASC")
            ->get();

        return view('contacts.merge', [
            'contact' => $contact,
            'candidates' => $candidates,
            'aliases' => $contact->aliases()->get(),
        ]);
    }

    /** Fusionne cette entité DANS un survivant (elle devient un alias). */
    public function merge(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless(auth()->user()->can('viewAdmin', Room::class), 403);

        $data = $request->validate([
            'survivor_id' => ['required', 'integer', 'different:'.$contact->id, 'exists:contacts,id'],
        ]);

        $survivor = Contact::findOrFail($data['survivor_id']);
        if ($survivor->isMerged()) {
            return back()->with('error', __('The target entity is itself merged. Pick a root entity.'));
        }
        if ($contact->aliases()->exists()) {
            return back()->with('error', __('This entity already has merged entities. Un-merge them first.'));
        }

        $contact->merged_into_id = $survivor->id;
        $contact->save();

        return redirect()->route('contacts.index', ['view' => 'all'])
            ->with('success', __(':a merged into :b.', ['a' => $contact->display_name(), 'b' => $survivor->display_name()]));
    }

    /** Défusionne une entité : elle redevient indépendante (réversible). */
    public function unmerge(Contact $contact): RedirectResponse
    {
        abort_unless(auth()->user()->can('viewAdmin', Room::class), 403);

        if (! $contact->isMerged()) {
            return back()->with('error', __('This entity is not merged.'));
        }

        $contact->merged_into_id = null;
        $contact->save();

        return back()->with('success', __(':name un-merged.', ['name' => $contact->display_name()]));
    }
}
