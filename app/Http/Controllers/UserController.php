<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Company;
use App\Models\Owner;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    use RedirectsBack;

    /**
     * Show the login form
     */
    public function showLoginForm(Request $request)
    {
        // Get enabled identity providers for OIDC login
        $identityProviders = \App\Models\IdentityProvider::where('enabled', true)->get();

        // Capture the intended URL from Referer header (for public pages)
        // Only if not already set by auth middleware and coming from same host
        $intendedUrl = session('url.intended');
        if (! $intendedUrl) {
            $referer = $request->headers->get('referer');
            if ($referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
                // Don't redirect back to login/register pages
                $refererPath = parse_url($referer, PHP_URL_PATH);
                if (! in_array($refererPath, ['/login', '/register'])) {
                    $intendedUrl = $referer;
                    session(['url.intended' => $referer]);
                }
            }
        }

        return view('auth.login', [
            'identityProviders' => $identityProviders,
            'intendedUrl' => $intendedUrl,
        ]);
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Capture intended URL before session regeneration
        $intendedUrl = $request->input('intended_url') ?: session('url.intended');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Determine redirect URL
            $redirectUrl = $intendedUrl ?: route('rooms.index');

            // Use query parameter for flash message (survives session regeneration)
            return redirect($redirectUrl.(str_contains($redirectUrl, '?') ? '&' : '?').'login_success=1');
        }

        return back()->withErrors([
            'email' => __('These credentials do not match our records.'),
        ])->onlyInput('email');
    }

    /**
     * Show the registration form
     */
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // Configure mailer with system settings and send email verification
        app(SettingsService::class)->configureMailer();
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('success', __('Account created successfully! Please verify your email to activate your account.'));
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Use query parameter for flash message (survives session invalidation)
        return redirect(route('login').'?logout_success=1');
    }

    // ============================================
    // Admin User Management Methods
    // ============================================

    /**
     * Display a listing of users (global admin only)
     */
    public function index(Request $request): View
    {
        $query = User::with(['contacts', 'owners']);

        // Filter by owner
        if ($request->filled('owner_id')) {
            if ($request->input('owner_id') == 'admin') {
                $query->where(function ($q) {
                    $q->has('owners')
                        ->orWhere('is_global_admin', true);
                });
            } elseif ($request->input('owner_id') == 'not_admin') {
                $query->whereDoesntHave('owners')
                    ->where('is_global_admin', false);
            } elseif ($request->input('owner_id') == 'global_admin') {
                $query->where('is_global_admin', true);
            } else {
                $query->whereHas('owners', function ($q) use ($request) {
                    $q->where('owners.id', $request->input('owner_id'));
                });
            }
        }

        $users = $query->orderBy('name', 'asc')->paginate(15)->appends($request->except('page'));

        // Get all owners for filter dropdown
        $owners = Owner::with('contact')->orderBy('id')->get();

        return view('users.index', [
            'users' => $users,
            'owners' => $owners,
        ]);
    }

    /**
     * Show the form for creating a new user
     */
    public function create(): View
    {
        $owners = Owner::with('contact')->orderBy('id')->get();

        return view('users.form', [
            'user' => null,
            'owners' => $owners,
            'ownerRoles' => UserRole::cases(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created user in storage (admin creation)
     */
    public function store(Request $request): RedirectResponse
    {
        $roleValues = array_column(UserRole::cases(), 'value');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'is_global_admin' => ['boolean'],
            'owners' => ['array'],
            'owners.*.id' => ['exists:owners,id'],
            'owners.*.role' => ['in:'.implode(',', $roleValues)],
            'companies' => ['array'],
            'companies.*' => ['exists:companies,id'],
        ]);

        // Create user with email verified (admin created)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_global_admin' => $request->boolean('is_global_admin'),
            'email_verified_at' => now(),
        ]);

        // Sync owners with roles
        $ownerSync = [];
        if (! empty($validated['owners'])) {
            foreach ($validated['owners'] as $ownerData) {
                $ownerSync[$ownerData['id']] = ['role' => $ownerData['role']];
            }
        }
        $user->owners()->sync($ownerSync);

        // Rattachement aux entreprises (La Pépite)
        $user->companies()->sync($validated['companies'] ?? []);

        return $this->redirectBack('users.index')
            ->with('success', __('User created successfully.'));
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user): View
    {
        $user->load(['owners', 'companies']);
        $owners = Owner::with('contact')->orderBy('id')->get();

        return view('users.form', [
            'user' => $user,
            'owners' => $owners,
            'ownerRoles' => UserRole::cases(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified user in storage
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $roleValues = array_column(UserRole::cases(), 'value');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', Password::min(12)],
            'is_global_admin' => ['boolean'],
            'owners' => ['array'],
            'owners.*.id' => ['exists:owners,id'],
            'owners.*.role' => ['in:'.implode(',', $roleValues)],
            'companies' => ['array'],
            'companies.*' => ['exists:companies,id'],
        ]);

        // Update user
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Empêcher un utilisateur de retirer son propre statut de global_admin
        if ($user->id === auth()->id() && $user->is_global_admin && ! $request->boolean('is_global_admin')) {
            return redirect()->route('users.edit', $user)
                ->with('error', __('You cannot remove your own global administrator status.'));
        }

        $user->is_global_admin = $request->boolean('is_global_admin');

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sync owners with roles (ajoute, met à jour et supprime selon les changements)
        $ownerSync = [];
        if (! empty($validated['owners'])) {
            foreach ($validated['owners'] as $ownerData) {
                $ownerSync[$ownerData['id']] = ['role' => $ownerData['role']];
            }
        }
        $user->owners()->sync($ownerSync);

        // Rattachement aux entreprises (La Pépite)
        $user->companies()->sync($validated['companies'] ?? []);

        return $this->redirectBack('users.index')
            ->with('success', __('User updated successfully.'));
    }

    /**
     * Remove the specified user from storage
     * Used by admins only
     */
    public function destroy(User $user): RedirectResponse
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return $this->redirectBack('users.index')
                ->with('error', __('You cannot delete your own account.'));
        }

        $user->delete();

        return $this->redirectBack('users.index')
            ->with('success', __('User deleted successfully.'));
    }

    // ============================================
    // User Profile Management
    // ============================================

    /**
     * Show user's own profile
     */
    public function profile(): View|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->is_global_admin) {
            return view('users.profile', ['user' => $user]);
        } else {
            return redirect()->route('users.edit', $user);
        }
    }

    /**
     * Update user's own profile
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        // Check if email changed
        $emailChanged = $user->email !== $validated['email'];

        // Update user
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // If email changed, mark as unverified and send new verification email
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Send email verification if email changed
        if ($emailChanged) {
            app(SettingsService::class)->configureMailer();
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')
                ->with('success', __('Profile updated! A verification email has been sent to your new address.'));
        }

        return redirect()->route('profile')
            ->with('success', __('Profile updated successfully!'));
    }

    /**
     * Show the password change form (requires recent authentication)
     */
    public function showPasswordForm(): View
    {
        return view('users.password', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update user's password and invalidate other sessions
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = auth()->user();
        $user->password = Hash::make($request->input('password'));
        $user->save();

        // Invalidate all other sessions for this user
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();

        return redirect()->route('profile')
            ->with('success', __('Password changed successfully. Your other sessions have been logged out.'));
    }

    /**
     * Delete user's own account
     */
    public function deleteAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // Prevent global admin from deleting themselves (security)
        if ($user->is_global_admin) {
            return redirect()->route('profile')
                ->with('error', __('Global administrators cannot delete their own account. Contact another administrator.'));
        }

        // Logout and delete
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        return redirect('/')->with('success', __('Your account has been deleted successfully.'));
    }
}
