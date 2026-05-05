<?php

declare(strict_types=1);

namespace Infrastructure\Currency\Providers;

use Domain\Currency\Contracts\ExchangeRateProviderInterface;
use Domain\Shared\Exceptions\InfrastructureException;
use Domain\Shared\ValueObjects\Currency;
use Illuminate\Support\Facades\Http;

/**
 * Frankfurter — ECB-derived rates, free, no API key, no rate limit
 * (ADR-009). API endpoint: GET https://api.frankfurter.app/latest
 *   ?from=TRY&to=USD,EUR
 * Response: {"amount":1,"base":"TRY","date":"2026-05-04","rates":{"USD":0.034,"EUR":0.029}}
 */
final class FrankfurterApiProvider implements ExchangeRateProviderInterface
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.frankfurter.app',
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function fetchLatest(Currency $base, array $targets): array
    {
        if ($targets === []) {
            return [];
        }

        $codes = implode(',', array_map(fn (Currency $c) => $c->value, $targets));

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($this->baseUrl . '/latest', [
                    'from' => $base->value,
                    'to' => $codes,
                ]);
        } catch (\Throwable $e) {
            throw InfrastructureException::withMessage(
                'Frankfurter API request failed: ' . $e->getMessage(),
                ['base' => $base->value, 'targets' => $codes],
                $e,
            );
        }

        if ($response->failed()) {
            throw InfrastructureException::withMessage(
                'Frankfurter API responded ' . $response->status(),
                [
                    'base' => $base->value,
                    'targets' => $codes,
                    'body' => $response->body(),
                ],
            );
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();
        $rates = $body['rates'] ?? [];

        if (!is_array($rates)) {
            throw InfrastructureException::withMessage(
                'Frankfurter response missing or malformed `rates`',
                ['body' => $body],
            );
        }

        return array_map(fn ($rate) => (float) $rate, $rates);
    }
}
