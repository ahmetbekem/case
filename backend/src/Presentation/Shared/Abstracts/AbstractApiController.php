<?php

declare(strict_types=1);

namespace Presentation\Shared\Abstracts;

use Application\Shared\Contracts\CommandBusInterface;
use Application\Shared\Contracts\QueryBusInterface;
use Presentation\Shared\Concerns\FormatsApiResponse;
use Presentation\Shared\Concerns\ResolvesRequestContext;

/**
 * Base controller for every API endpoint.
 *
 * - Holds the bus pair so concrete controllers stay weightless (per
 *   CODING_STANDARDS §15: dispatch-only, no business logic).
 * - Mixes in request-context helpers (getUserId, getCorrelationId)
 *   and response builders (success, noContent, error).
 * - Does NOT extend Illuminate\Routing\Controller — Laravel 11 makes
 *   that base optional; staying detached keeps coupling minimal.
 */
abstract class AbstractApiController
{
    use ResolvesRequestContext;
    use FormatsApiResponse;

    public function __construct(
        protected readonly CommandBusInterface $commandBus,
        protected readonly QueryBusInterface $queryBus,
    ) {
    }
}
