<?php

namespace App\Services\Users;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class UserCreationAuditContext
{
    protected ?array $context = null;

    public function current(): array
    {
        return $this->normalize($this->context ?? []);
    }

    public function runWith(array $context, callable $callback): mixed
    {
        $previous = $this->context;
        $this->context = $this->normalize($context);

        try {
            return $callback();
        } finally {
            $this->context = $previous;
        }
    }

    protected function normalize(array $context): array
    {
        $request = app()->bound('request') ? request() : null;
        $hasHttpRequest = $this->hasHttpRequest($request);
        $actor = Auth::user();

        $metadata = $context['metadata'] ?? [];
        if (! is_array($metadata)) {
            $metadata = [];
        }

        return [
            'source' => $context['source'] ?? $this->inferSource($request, $hasHttpRequest),
            'actor_id' => $context['actor_id'] ?? $actor?->id,
            'actor_name' => $context['actor_name'] ?? $actor?->name,
            'ip_address' => $context['ip_address'] ?? ($hasHttpRequest ? $request?->ip() : null),
            'user_agent' => $context['user_agent'] ?? ($hasHttpRequest ? $request?->userAgent() : null),
            'execution_context' => $context['execution_context'] ?? ($hasHttpRequest ? 'http' : 'console'),
            'metadata' => $metadata,
        ];
    }

    protected function inferSource($request, bool $hasHttpRequest): string
    {
        if (! $hasHttpRequest) {
            return 'console';
        }

        $routeName = $request?->route()?->getName();
        if (is_string($routeName) && $routeName !== '') {
            return $routeName;
        }

        $method = Request::method();
        $path = $request?->path() ?: '/';

        return strtolower($method).' '.$path;
    }

    protected function hasHttpRequest($request): bool
    {
        if (! $request) {
            return false;
        }

        if ($request->route()) {
            return true;
        }

        return $request->path() !== '/';
    }
}
