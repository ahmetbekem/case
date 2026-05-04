<?php

declare(strict_types=1);

namespace Presentation\Shared\Concerns;

use Domain\Shared\Constants\HttpStatus;
use Illuminate\Http\JsonResponse;

trait FormatsApiResponse
{
    /**
     * @param array<string, mixed> $meta
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $status = HttpStatus::OK,
        array $meta = [],
    ): JsonResponse {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    protected function noContent(): JsonResponse
    {
        return new JsonResponse(null, HttpStatus::NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function error(
        string $code,
        string $message,
        int $status,
        array $context = [],
    ): JsonResponse {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if ($context !== []) {
            $payload['error']['context'] = $context;
        }

        return new JsonResponse($payload, $status);
    }
}
