<?php

namespace App\Support;

/**
 * Jetons signés échangés avec le panneau d'administration du site
 * (admin.pepite-lausanne.ch). LibreRooms y fait office de fournisseur
 * d'identité : un seul compte, un seul mot de passe pour l'équipe.
 *
 * Format : base64url(json) . "." . base64url(hmac_sha256(json, secret)).
 * Le même format est implémenté côté site (api/_lib/sso.php) : toute
 * modification ici doit y être reportée.
 */
class PepiteSso
{
    public static function secret(): string
    {
        return (string) config('services.pepite_sso.secret', '');
    }

    public static function enabled(): bool
    {
        return strlen(self::secret()) >= 32;
    }

    public static function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = self::b64($json);

        return $body.'.'.self::b64(hash_hmac('sha256', $body, self::secret(), true));
    }

    /**
     * Vérifie signature, audience et expiration. Renvoie le contenu ou null.
     */
    public static function verify(?string $token, string $audience): ?array
    {
        if (! self::enabled() || ! $token || substr_count($token, '.') !== 1) {
            return null;
        }
        [$body, $sig] = explode('.', $token);
        $expected = self::b64(hash_hmac('sha256', $body, self::secret(), true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode(self::unb64($body), true);
        if (! is_array($payload) || ($payload['aud'] ?? null) !== $audience) {
            return null;
        }
        if (! isset($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * L'adresse de retour doit commencer par un des préfixes autorisés
     * (PEPITE_SSO_REDIRECTS) : sinon n'importe quel site pourrait recevoir
     * un jeton d'identité.
     */
    public static function redirectAllowed(string $url): bool
    {
        foreach ((array) config('services.pepite_sso.redirects', []) as $prefix) {
            $prefix = trim($prefix);
            if ($prefix !== '' && str_starts_with($url, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $txt): string
    {
        return (string) base64_decode(strtr($txt, '-_', '+/'));
    }
}
