<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\PepiteSso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Pont avec le panneau d'administration du site (admin.pepite-lausanne.ch).
 *
 *  authorizeAdmin : le panneau envoie l'admin ici ; s'il n'est pas connecté,
 *              LibreRooms affiche SON formulaire de connexion, puis renvoie
 *              au panneau un jeton signé (qui, nom, e-mail).
 *  enter     : chemin inverse — le bouton « Réservations » du panneau
 *              arrive ici avec un jeton signé et ouvre la session sans
 *              redemander le mot de passe.
 *  login     : le formulaire de connexion affiché SUR le panneau envoie
 *              (de serveur à serveur) e-mail + mot de passe à vérifier ici :
 *              l'équipe ne quitte pas admin.pepite-lausanne.ch.
 *  notify    : le panneau fait envoyer un e-mail (nouvelle demande de
 *              modification) par le compte d'envoi déjà configuré ici.
 */
class PepiteSsoController extends Controller
{
    public function authorizeAdmin(Request $request)
    {
        abort_unless(PepiteSso::enabled(), 404);

        $redirect = (string) $request->query('redirect_uri', '');
        $state = (string) $request->query('state', '');
        abort_unless($redirect !== '' && PepiteSso::redirectAllowed($redirect), 400, 'Adresse de retour non autorisée.');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) === 1, 400, 'Paramètre state invalide.');

        $user = $request->user();
        $sep = str_contains($redirect, '?') ? '&' : '?';

        if (! $user->is_global_admin) {
            return redirect()->away($redirect.$sep.http_build_query(['error' => 'forbidden', 'state' => $state]));
        }

        $token = PepiteSso::sign([
            'aud' => 'admin',
            'uid' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'state' => $state,
            'exp' => time() + 120,
        ]);

        return redirect()->away($redirect.$sep.http_build_query(['token' => $token, 'state' => $state]));
    }

    public function enter(Request $request)
    {
        abort_unless(PepiteSso::enabled(), 404);

        $payload = PepiteSso::verify($request->query('token'), 'reservations');
        // Un jeton ne sert qu'une fois (nonce mémorisé jusqu'à son expiration).
        $fresh = $payload && isset($payload['nonce'])
            && Cache::add('pepite_sso_nonce_'.$payload['nonce'], 1, 300);

        $user = $fresh ? User::find($payload['uid'] ?? 0) : null;
        if (! $user || ! $user->is_global_admin || strcasecmp($user->email, (string) ($payload['email'] ?? '')) !== 0) {
            return redirect()->route('login');
        }

        if (! Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('planning.index');
    }

    public function notify(Request $request)
    {
        abort_unless(PepiteSso::enabled(), 404);

        $payload = PepiteSso::verify((string) $request->input('token'), 'notify');
        if (! $payload || ! isset($payload['nonce']) || ! Cache::add('pepite_sso_nonce_'.$payload['nonce'], 1, 300)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }

        $to = (string) ($payload['to'] ?? '');
        $subject = Str::limit((string) ($payload['subject'] ?? ''), 180, '…');
        $text = (string) ($payload['text'] ?? '');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $text === '') {
            return response()->json(['ok' => false, 'error' => 'bad payload'], 422);
        }

        try {
            app(SettingsService::class)->configureMailer();
            Mail::raw($text, function ($m) use ($to, $subject, $payload) {
                $m->to($to)->subject($subject);
                if (! empty($payload['reply_to']) && filter_var($payload['reply_to'], FILTER_VALIDATE_EMAIL)) {
                    $m->replyTo($payload['reply_to'], $payload['reply_name'] ?? null);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('pepite-sso notify: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => 'send failed'], 502);
        }

        return response()->json(['ok' => true]);
    }

    public function login(Request $request)
    {
        abort_unless(PepiteSso::enabled(), 404);

        $p = PepiteSso::verify((string) $request->input('token'), 'login');
        if (! $p || ! isset($p['nonce']) || ! Cache::add('pepite_sso_nonce_'.$p['nonce'], 1, 300)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }

        // Toutes les tentatives arrivent de l'IP du site : on limite par
        // adresse e-mail, pas par IP.
        $email = mb_strtolower(trim((string) ($p['email'] ?? '')));
        $key = 'pepite-sso-login:'.sha1($email);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'error' => 'throttled', 'retry' => RateLimiter::availableIn($key)]);
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (! $user || ! $user->password || ! Hash::check((string) ($p['password'] ?? ''), $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json(['ok' => false, 'error' => 'credentials']);
        }
        RateLimiter::clear($key);

        if (! $user->is_global_admin) {
            return response()->json(['ok' => false, 'error' => 'forbidden']);
        }

        return response()->json(['ok' => true, 'uid' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
