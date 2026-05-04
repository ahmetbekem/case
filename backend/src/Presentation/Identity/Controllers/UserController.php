<?php

declare(strict_types=1);

namespace Presentation\Identity\Controllers;

use Application\Identity\Queries\GetCurrentUserQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Presentation\Shared\Abstracts\AbstractApiController;

final class UserController extends AbstractApiController
{
    public function me(Request $request): JsonResponse
    {
        $query = new GetCurrentUserQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
        );

        $result = $this->queryBus->ask($query);

        return $this->success($result);
    }
}
