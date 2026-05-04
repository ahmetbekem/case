<?php

declare(strict_types=1);

namespace Presentation\Shared\Concerns;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

trait ResolvesRequestContext
{
    /**
     * The authenticated user's ID. Returns 'anonymous' if no user is bound
     * to the request (public endpoints, anon traffic). JwtAuthMiddleware
     * is responsible for setting `user_id` when a valid token is present.
     */
    protected function getUserId(Request $request): string
    {
        return (string) ($request->attributes->get('user_id') ?? 'anonymous');
    }

    /**
     * The current request's correlation ID. CorrelationIdMiddleware sets
     * this from the X-Correlation-Id header; if missing, we generate one
     * here and stash it back on the request for downstream consumers.
     */
    protected function getCorrelationId(Request $request): string
    {
        $existing = $request->attributes->get('correlation_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }
        $generated = Uuid::uuid4()->toString();
        $request->attributes->set('correlation_id', $generated);
        return $generated;
    }
}
