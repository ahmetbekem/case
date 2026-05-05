<?php

declare(strict_types=1);

namespace Presentation\Currency\Controllers;

use Application\Currency\Queries\GetExchangeRateQuery;
use Application\Currency\Queries\ListSupportedCurrenciesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Presentation\Shared\Abstracts\AbstractApiController;

final class CurrencyController extends AbstractApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = new ListSupportedCurrenciesQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
        );

        return $this->success($this->queryBus->ask($query));
    }

    public function show(Request $request, string $base, string $target): JsonResponse
    {
        $query = new GetExchangeRateQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            base: $base,
            target: $target,
        );

        return $this->success($this->queryBus->ask($query));
    }
}
