<?php

declare(strict_types=1);

namespace Presentation\Identity\Controllers;

use Application\Identity\Commands\LoginUserCommand;
use Application\Identity\Commands\LogoutUserCommand;
use Application\Identity\Commands\RefreshTokenCommand;
use Application\Identity\Commands\RegisterUserCommand;
use Domain\Shared\Constants\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Presentation\Identity\Requests\LoginRequest;
use Presentation\Identity\Requests\RefreshTokenRequest;
use Presentation\Identity\Requests\RegisterUserRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

/**
 * Auth endpoints. By design these return tokens **in the JSON body**;
 * the Next.js BFF (TASK 0.15) is responsible for moving them into
 * httpOnly cookies before the response reaches the browser. The backend
 * itself is cookie-blind (ADR-004 + SECURITY.md §3.2).
 */
final class AuthController extends AbstractApiController
{
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $command = new RegisterUserCommand(
            userId: 'anonymous',
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            fullName: (string) $request->validated('fullName'),
        );

        $result = $this->commandBus->dispatch($command);

        return $this->success(
            data: $result,
            message: 'Registration successful',
            status: HttpStatus::CREATED,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $command = new LoginUserCommand(
            userId: 'anonymous',
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            userAgent: $request->userAgent(),
        );

        $result = $this->commandBus->dispatch($command);

        return $this->success($result);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $command = new RefreshTokenCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            refreshToken: (string) $request->validated('refresh_token'),
            userAgent: $request->userAgent(),
        );

        $result = $this->commandBus->dispatch($command);

        return $this->success($result);
    }

    public function logout(Request $request): JsonResponse
    {
        $jti = $request->attributes->get('jwt_jti');
        $exp = $request->attributes->get('jwt_exp');
        $remaining = is_int($exp) ? max(0, $exp - time()) : null;

        $command = new LogoutUserCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            refreshToken: (string) $request->input('refresh_token', ''),
            accessTokenJti: is_string($jti) ? $jti : null,
            accessTokenRemainingTtl: $remaining,
        );

        $this->commandBus->dispatch($command);

        return $this->noContent();
    }
}
