<?php

namespace App\Services\Bexio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client HTTP minimal pour l'API bexio 2.0 / 3.0.
 * Auth = Bearer token (Personal Access Token ou access token OAuth), lu depuis
 * la config (services.bexio.token), donc depuis .env — jamais en dur.
 *
 * bexio = source de vérité comptable : ce client CRÉE/LIT dans bexio, il ne
 * stocke rien lui-même (le mapping d'IDs est gardé côté modèles La Pépite).
 */
class BexioClient
{
    public function configured(): bool
    {
        return filled(config('services.bexio.token'));
    }

    /** Requête préconfigurée (base URL + Bearer + JSON). */
    protected function http(): PendingRequest
    {
        $token = config('services.bexio.token');
        if (blank($token)) {
            throw new RuntimeException('bexio : aucun token configuré (BEXIO_TOKEN manquant dans .env).');
        }

        return Http::baseUrl(rtrim((string) config('services.bexio.base_url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    /** @param  array<string,mixed>  $query */
    public function get(string $path, array $query = []): array
    {
        return $this->decode($this->http()->get($this->path($path), $query), 'GET '.$path);
    }

    /** @param  array<string,mixed>  $payload */
    public function post(string $path, array $payload): array
    {
        return $this->decode($this->http()->post($this->path($path), $payload), 'POST '.$path);
    }

    /** @param  array<string,mixed>  $payload */
    public function put(string $path, array $payload): array
    {
        return $this->decode($this->http()->put($this->path($path), $payload), 'PUT '.$path);
    }

    /** Normalise le chemin (ex. "2.0/contact"). */
    protected function path(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    /** Décode la réponse, lève une erreur lisible si bexio répond en erreur. */
    protected function decode(Response $response, string $context): array
    {
        if ($response->failed()) {
            throw new RuntimeException(
                'bexio '.$context.' → HTTP '.$response->status().' : '.$response->body()
            );
        }

        return $response->json() ?? [];
    }
}
