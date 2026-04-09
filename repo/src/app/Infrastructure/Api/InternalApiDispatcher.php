<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Dispatches internal requests through the Laravel router so that
 * Livewire components consume the same REST API endpoints as external
 * clients — maintaining a single authoritative write path.
 */
class InternalApiDispatcher
{
    public function get(string $uri, array $query = []): array
    {
        return $this->dispatch('GET', $uri, $query);
    }

    public function post(string $uri, array $data = []): array
    {
        return $this->dispatch('POST', $uri, $data);
    }

    public function patch(string $uri, array $data = []): array
    {
        return $this->dispatch('PATCH', $uri, $data);
    }

    public function delete(string $uri, array $data = []): array
    {
        return $this->dispatch('DELETE', $uri, $data);
    }

    private function dispatch(string $method, string $uri, array $data): array
    {
        $fullUri = '/api' . $uri;

        $request = Request::create($fullUri, $method, $data);

        // Carry forward session, auth, and fingerprint from parent request
        $parentRequest = app('request');

        if ($parentRequest->hasSession()) {
            $request->setLaravelSession($parentRequest->session());
        }

        $request->headers->set('X-Session-Id', session()->getId());

        // Forward device fingerprint attributes
        $request->attributes->set(
            'device_fingerprint_id',
            $parentRequest->attributes->get('device_fingerprint_id'),
        );
        $request->attributes->set(
            'device_fingerprint_hash',
            $parentRequest->attributes->get('device_fingerprint_hash'),
        );

        // Forward auth
        if (auth()->check()) {
            $user = auth()->user();
            $request->setUserResolver(fn () => $user);
        }

        $response = Route::dispatch($request);

        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent(), true) ?? [],
        ];
    }
}
