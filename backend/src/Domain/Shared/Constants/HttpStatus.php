<?php

declare(strict_types=1);

namespace Domain\Shared\Constants;

final class HttpStatus
{
    public const int OK = 200;
    public const int CREATED = 201;
    public const int ACCEPTED = 202;
    public const int NO_CONTENT = 204;

    public const int BAD_REQUEST = 400;
    public const int UNAUTHORIZED = 401;
    public const int FORBIDDEN = 403;
    public const int NOT_FOUND = 404;
    public const int METHOD_NOT_ALLOWED = 405;
    public const int CONFLICT = 409;
    public const int GONE = 410;
    public const int LOCKED = 423;
    public const int UNPROCESSABLE_ENTITY = 422;
    public const int TOO_MANY_REQUESTS = 429;

    public const int INTERNAL_SERVER_ERROR = 500;
    public const int BAD_GATEWAY = 502;
    public const int SERVICE_UNAVAILABLE = 503;

    private function __construct()
    {
    }
}
