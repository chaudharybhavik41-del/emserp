<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaPushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription' => ['required', 'array'],
            'subscription.endpoint' => ['required', 'string', 'max:5000'],
            'subscription.keys' => ['nullable', 'array'],
            'subscription.keys.p256dh' => ['nullable', 'string', 'max:5000'],
            'subscription.keys.auth' => ['nullable', 'string', 'max:5000'],
            'subscription.contentEncoding' => ['nullable', 'string', 'max:100'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $subscription = $validated['subscription'];
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        $endpointHash = hash('sha256', $endpoint);
        $keys = (array) ($subscription['keys'] ?? []);

        $record = $user->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'endpoint' => $endpoint,
                'public_key' => $keys['p256dh'] ?? null,
                'auth_token' => $keys['auth'] ?? null,
                'content_encoding' => $subscription['contentEncoding'] ?? null,
                'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
                'disabled_at' => null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'id' => $record->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['nullable', 'string', 'max:5000'],
        ]);

        $query = $request->user()->pushSubscriptions();
        if (!empty($validated['endpoint'])) {
            $query->where('endpoint_hash', hash('sha256', (string) $validated['endpoint']));
        }

        $deleted = $query->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }
}
