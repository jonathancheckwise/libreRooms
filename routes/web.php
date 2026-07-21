<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OidcController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\OwnerUserController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomUserController;
use App\Http\Controllers\UserController;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Eager load Room relations for all routes
Route::bind('room', function (string $value) {
    return Room::where('slug', $value)
        ->with([
            'options',        // Used in ReservationService for price calculation
            'customFields',   // Used in ReservationService for CustomFieldValues
            'discounts',      // Used in ReservationService for sum_discounts calculation
            'owner',          // Used for permission checks
            'images',         // Used in room show page
        ])
        ->firstOrFail();
});

// Eager load Reservation relations for all routes
Route::bind('reservation', function (string $value) {
    return Reservation::with([
        'room.options',        // Used in ReservationService for price calculation
        'room.customFields',   // Used in ReservationService for CustomFieldValues
        'room.discounts',      // Used in ReservationService for sum_discounts calculation
        'room.owner',          // Used for permission checks
        'tenant',              // The Contact associated with this reservation
        'events.options',      // ReservationEvents with their options
        'customFieldValues',   // Custom field values for this reservation
        'confirmedBy',         // User who confirmed (if applicable)
    ])
        ->findOrFail($value);
});

Route::get('/', function () {
    // La Pépite : un visiteur non connecté est envoyé vers la connexion,
    // pas vers la liste des salles (rien n'est public pour l'instant).
    return auth()->check()
        ? redirect(route('rooms.index'))
        : redirect(route('login'));
});

// Initial setup routes (protected by controller - requires DB_CONFIGURED=false OR global_admin)
Route::controller(\App\Http\Controllers\SetupController::class)
    ->group(function () {
        Route::get('/setup', 'showEnvironmentForm')->name('setup.environment');
        Route::post('/setup', 'saveEnvironment')->name('setup.environment.store');
        Route::get('/setup/admin', 'showAdminForm')->name('setup.admin');
        Route::post('/setup/admin', 'createAdmin')->name('setup.admin.store');
    });

// Webcron route
Route::get('/webcron/schedule-run', function (Request $request) {
    abort_unless(
        hash_equals((string) config('app.webcron_token'), (string) $request->query('token')),
        403
    );
    Artisan::call('schedule:run');
    return response(Artisan::output(), 200)->header('Content-Type', 'text/plain');
});

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [UserController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [UserController::class, 'login']);
    // La Pépite : inscription libre désactivée. Les comptes sont créés par l'équipe.
    Route::get('/register', fn () => redirect()->route('login'))->name('register');
    Route::post('/register', fn () => abort(403));
    Route::get('/auth/{provider:slug}', [OidcController::class, 'redirect'])->name('auth.oidc.redirect');

    // Password reset routes
    Route::get('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\PasswordResetController::class, 'reset'])->name('password.update');
});

// OIDC callback - outside guest middleware to support both login and reauthentication
Route::get('/auth/{provider:slug}/callback', [OidcController::class, 'callback'])->name('auth.oidc.callback');

Route::post('/logout', [UserController::class, 'logout'])->middleware('auth')->name('logout');

// Email verification routes
Route::middleware(['auth', 'unverified'])->group(function () {
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('rooms.index')->with('success', 'Email vérifié avec succès !');
    })->middleware(['signed'])->name('verification.verify');

    Route::post('/email/resend', function (Request $request) {
        app(\App\Services\Settings\SettingsService::class)->configureMailer();
        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Email de vérification renvoyé !');
    })->middleware(['throttle:6,1'])->name('verification.resend');
});

// User profile
Route::middleware('auth')->group(function () {
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::delete('/profile', [UserController::class, 'deleteAccount'])->name('profile.delete');
});

// Re-authentication
Route::middleware('auth')->group(function () {
    Route::get('/reauthenticate', [\App\Http\Controllers\ReauthController::class, 'show'])->name('reauthenticate');
    Route::post('/reauthenticate/password', [\App\Http\Controllers\ReauthController::class, 'verifyPassword'])->name('reauthenticate.password')->middleware('throttle:5,1');
    Route::get('/reauthenticate/oidc/{provider:slug}', [\App\Http\Controllers\ReauthController::class, 'oidcRedirect'])->name('reauthenticate.oidc.redirect');
});

// Password change (requires recent authentication)
Route::middleware(['auth', 'recently_authenticated'])->group(function () {
    Route::get('/profile/password', [UserController::class, 'showPasswordForm'])->name('profile.password');
    Route::put('/profile/password', [UserController::class, 'updatePassword'])->name('profile.password.update');
});

Route::controller(RoomController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/rooms/create', 'create')->name('rooms.create');
    Route::post('/rooms', 'store')->name('rooms.store');
    Route::get('/rooms/{room:slug}/edit', 'edit')->name('rooms.edit');
    Route::put('/rooms/{room:slug}', 'update')->name('rooms.update');
    Route::delete('/rooms/{room:slug}', 'destroy')->name('rooms.destroy');
});
Route::controller(RoomController::class)->group(function () {
    Route::get('/rooms', 'index')->name('rooms.index');
    Route::get('/rooms/{room:slug}', 'show')->name('rooms.show');
    Route::get('/rooms/{room:slug}/available', 'available')->name('rooms.available');
});

Route::controller(RoomUserController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/rooms/{room:slug}/users', 'index')->name('rooms.users.index');
    Route::post('/rooms/{room:slug}/users', 'store')->name('rooms.users.store');
    Route::delete('/rooms/{room:slug}/users/{user}', 'destroy')->name('rooms.users.destroy');
});

Route::controller(ReservationController::class)->group(function () {
    Route::get('/rooms/{room:slug}/book', 'create')->name('reservations.create');
    Route::post('/rooms/{room:slug}/reservation', 'store')->name('reservations.store');
});
Route::controller(ReservationController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/reservations', 'index')->name('reservations.index');
    Route::get('/reservations/{reservation}', 'show')->name('reservations.show');
    Route::get('/reservations/{reservation}/edit', 'edit')->name('reservations.edit');
    Route::put('/reservations/{reservation}', 'update')->name('reservations.update');
    Route::post('/reservations/{reservation}/confirm', 'directConfirm')->name('reservations.confirm');
    Route::post('/reservations/{reservation}/cancel', 'cancel')->name('reservations.cancel');
});

Route::controller(OwnerController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/owners', 'index')->name('owners.index');
    Route::get('/owners/create', 'create')->name('owners.create');
    Route::post('/owners', 'store')->name('owners.store');
    Route::get('/owners/{owner:slug}/edit', 'edit')->name('owners.edit');
    Route::put('/owners/{owner:slug}', 'update')->name('owners.update');
    Route::delete('/owners/{owner:slug}', 'destroy')->name('owners.destroy');
});

Route::controller(OwnerUserController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/owners/{owner:slug}/users', 'index')->name('owners.users.index');
    Route::post('/owners/{owner:slug}/users', 'store')->name('owners.users.store');
    Route::delete('/owners/{owner:slug}/users/{user}', 'destroy')->name('owners.users.destroy');
});

Route::controller(ContactController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/contacts', 'index')->name('contacts.index');
    Route::get('/contacts/create', 'create')->name('contacts.create');
    Route::post('/contacts', 'store')->name('contacts.store');
    Route::get('/contacts/{contact}/edit', 'edit')->name('contacts.edit');
    Route::put('/contacts/{contact}', 'update')->name('contacts.update');
    Route::post('/contacts/{contact}/share', 'share')->name('contacts.share');
    Route::delete('/contacts/{contact}', 'destroy')->name('contacts.destroy');
});

// User management (global admin only)
Route::controller(UserController::class)->middleware(['auth', 'verified', 'global_admin'])->group(function () {
    Route::get('/users', 'index')->name('users.index');
    Route::get('/users/create', 'create')->name('users.create');
    Route::post('/users', 'store')->name('users.store');
});
Route::controller(UserController::class)->middleware(['auth', 'recently_authenticated', 'verified', 'global_admin'])->group(function () {
    Route::get('/users/{user}/edit', 'edit')->name('users.edit');
    Route::put('/users/{user}', 'update')->name('users.update');
    Route::delete('/users/{user}', 'destroy')->name('users.destroy');
});

// Entreprises / organisations (La Pépite) — admin global uniquement
Route::controller(\App\Http\Controllers\CompanyController::class)->middleware(['auth', 'verified', 'global_admin'])->group(function () {
    Route::get('/companies', 'index')->name('companies.index');
    Route::get('/companies/create', 'create')->name('companies.create');
    Route::post('/companies', 'store')->name('companies.store');
    Route::get('/companies/{company}/edit', 'edit')->name('companies.edit');
    Route::put('/companies/{company}', 'update')->name('companies.update');
    Route::delete('/companies/{company}', 'destroy')->name('companies.destroy');
});

// System settings (global admin only)
Route::controller(\App\Http\Controllers\SystemSettingsController::class)->middleware(['auth', 'verified', 'global_admin'])->group(function () {
    Route::get('/system-settings', 'edit')->name('system-settings.edit');
    Route::put('/system-settings', 'update')->name('system-settings.update');
});

// Configuration testing routes
Route::controller(\App\Http\Controllers\ConfigTestController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::post('/config/test-mail', 'testMail')->name('config.test-mail');
    Route::post('/config/test-caldav', 'testCaldav')->name('config.test-caldav');
    Route::post('/config/test-webdav', 'testWebdav')->name('config.test-webdav');
});

// Identity providers (global admin only)
Route::controller(\App\Http\Controllers\IdentityProviderController::class)->middleware(['auth', 'verified', 'global_admin'])->group(function () {
    Route::get('/identity-providers', 'index')->name('identity-providers.index');
    Route::get('/identity-providers/create', 'create')->name('identity-providers.create');
    Route::post('/identity-providers', 'store')->name('identity-providers.store');
    Route::get('/identity-providers/{identityProvider}/edit', 'edit')->name('identity-providers.edit');
    Route::put('/identity-providers/{identityProvider}', 'update')->name('identity-providers.update');
    Route::delete('/identity-providers/{identityProvider}', 'destroy')->name('identity-providers.destroy');
});

Route::controller(\App\Http\Controllers\RoomDiscountController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/room-discounts', 'index')->name('room-discounts.index');
    Route::get('/room-discounts/create', 'create')->name('room-discounts.create');
    Route::post('/room-discounts', 'store')->name('room-discounts.store');
    Route::get('/room-discounts/{roomDiscount}/edit', 'edit')->name('room-discounts.edit');
    Route::put('/room-discounts/{roomDiscount}', 'update')->name('room-discounts.update');
    Route::delete('/room-discounts/{roomDiscount}', 'destroy')->name('room-discounts.destroy');
});

Route::controller(\App\Http\Controllers\RoomOptionController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/room-options', 'index')->name('room-options.index');
    Route::get('/room-options/create', 'create')->name('room-options.create');
    Route::post('/room-options', 'store')->name('room-options.store');
    Route::get('/room-options/{roomOption}/edit', 'edit')->name('room-options.edit');
    Route::put('/room-options/{roomOption}', 'update')->name('room-options.update');
    Route::delete('/room-options/{roomOption}', 'destroy')->name('room-options.destroy');
});

Route::controller(\App\Http\Controllers\CustomFieldController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/custom-fields', 'index')->name('custom-fields.index');
    Route::get('/custom-fields/create', 'create')->name('custom-fields.create');
    Route::post('/custom-fields', 'store')->name('custom-fields.store');
    Route::get('/custom-fields/{customField}/edit', 'edit')->name('custom-fields.edit');
    Route::put('/custom-fields/{customField}', 'update')->name('custom-fields.update');
    Route::delete('/custom-fields/{customField}', 'destroy')->name('custom-fields.destroy');
});

Route::controller(\App\Http\Controllers\RoomUnavailabilityController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/room-unavailabilities', 'index')->name('room-unavailabilities.index');
    Route::get('/room-unavailabilities/create', 'create')->name('room-unavailabilities.create');
    Route::post('/room-unavailabilities', 'store')->name('room-unavailabilities.store');
    Route::get('/room-unavailabilities/{roomUnavailability}/edit', 'edit')->name('room-unavailabilities.edit');
    Route::put('/room-unavailabilities/{roomUnavailability}', 'update')->name('room-unavailabilities.update');
    Route::delete('/room-unavailabilities/{roomUnavailability}', 'destroy')->name('room-unavailabilities.destroy');
});

// Invoice management
Route::controller(\App\Http\Controllers\InvoiceController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/invoices', 'index')->name('invoices.index');
    Route::post('/invoices/{invoice}/remind', 'remind')->name('invoices.remind');
    Route::post('/invoices/{invoice}/pay', 'markAsPaid')->name('invoices.pay');
    Route::post('/invoices/{invoice}/cancel', 'cancel')->name('invoices.cancel');
    Route::post('/invoices/{invoice}/recreate', 'recreate')->name('invoices.recreate');
});

Route::get(
    '/rooms/{room:slug}/availability',
    [AvailabilityController::class, 'show']
)->name('rooms.availability')
    ->middleware(\App\Http\Middleware\ReadOnlySession::class);

// Public reservation access via hash (for tenants)
Route::get('/r/{hash}/prebook.pdf', [\App\Http\Controllers\PdfController::class, 'prebook'])->name('reservations.prebook.pdf');
Route::get('/r/{hash}/invoice.pdf', [\App\Http\Controllers\PdfController::class, 'invoice'])->name('reservations.invoice.pdf');
Route::get('/r/{hash}/reminder.pdf', [\App\Http\Controllers\PdfController::class, 'reminder'])->name('reservations.reminder.pdf');

Route::get('/r/{hash}/codes', [\App\Http\Controllers\SecretCodeController::class, 'show'])
    ->name('reservations.codes');

Route::get('/r/{hash}/event/{uid}/ics', function (string $hash, string $uid) {
    $reservation = Reservation::where('hash', $hash)
        ->with('events', 'room')
        ->firstOrFail();
    $event = $reservation->events->firstWhere('uid', $uid);
    if (! $event) {
        abort(404);
    }

    $icalData = \App\Services\Ical\IcalService::getIcalData($event->eventDTO());

    return response($icalData)
        ->header('Content-Type', 'text/calendar; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="event.ics"');
})->name('reservations.event-ics');
