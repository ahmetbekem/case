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
use OpenApi\Attributes as OA;
use Presentation\Identity\Requests\LoginRequest;
use Presentation\Identity\Requests\RefreshTokenRequest;
use Presentation\Identity\Requests\RegisterUserRequest;
use Presentation\Sales\Middleware\GuestSessionMiddleware;
use Presentation\Shared\Abstracts\AbstractApiController;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Auth endpoints. By design these return tokens **in the JSON body**;
 * the Next.js BFF (TASK 0.15) is responsible for moving them into
 * httpOnly cookies before the response reaches the browser. The backend
 * itself is cookie-blind (ADR-004 + SECURITY.md §3.2).
 */
final class AuthController extends AbstractApiController
{
    #[OA\Post(
        path: '/auth/register',
        summary: 'Yeni kullanıcı kaydı',
        description: 'Yeni customer hesabı açar ve login\'le aynı zarfı (token + user) döner. '
            . 'Public endpoint. throttle:register profili ile rate-limit\'li.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Kullanıcı oluşturuldu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/TokenIssueResultDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                    ],
                ),
            ),
            new OA\Response(response: 409, description: 'Email zaten kayıtlı', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $guestSessionId = $this->extractGuestSessionId($request);

        $command = new RegisterUserCommand(
            userId: 'anonymous',
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            fullName: (string) $request->validated('fullName'),
            guestSessionId: $guestSessionId,
        );

        $result = $this->commandBus->dispatch($command);

        $response = $this->success(
            data: $result,
            message: 'Registration successful',
            status: HttpStatus::CREATED,
        );

        return $this->clearGuestCookieIfPresent($response, $guestSessionId);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Giriş yap',
        description: 'Email + şifre ile giriş; access + refresh token + user kaydı döner. '
            . 'BFF cevabı httpOnly cookie\'ye taşır. throttle:login profili.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Giriş başarılı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/TokenIssueResultDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Email veya şifre hatalı', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $guestSessionId = $this->extractGuestSessionId($request);

        $command = new LoginUserCommand(
            userId: 'anonymous',
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            userAgent: $request->userAgent(),
            guestSessionId: $guestSessionId,
        );

        $result = $this->commandBus->dispatch($command);

        return $this->clearGuestCookieIfPresent($this->success($result), $guestSessionId);
    }

    #[OA\Post(
        path: '/auth/refresh',
        summary: 'Access token yenile',
        description: 'Refresh token ile yeni access + refresh çifti üretir (rotation). '
            . 'Eski refresh token revoke edilir; replay 5s grace içinde tespit edilirse aile (family) revoke. '
            . 'throttle:refresh profili.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RefreshTokenRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token yenilendi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/AuthTokenPairDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Refresh token geçersiz / revoked / replay tespiti', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
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

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Çıkış yap',
        description: 'Access token JTI Redis blacklist\'e yazılır + refresh token revoke edilir. '
            . 'Body opsiyonel — refresh_token verilirse ailesi de revoke. 204 No Content döner.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string', description: 'Opsiyonel — verilirse aile revoke'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 204, description: 'Çıkış başarılı (no content)'),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ],
    )]
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

    /**
     * ADR-017 — Guest cart cookie value, valid UUID v4 değilse null
     * (bot/spam koruması). Login/register handler bu sinyali alıp
     * MergeGuestCartCommand'ı tetikler.
     */
    private function extractGuestSessionId(Request $request): ?string
    {
        $value = $request->cookie(GuestSessionMiddleware::COOKIE_NAME);
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            return null;
        }
        return $value;
    }

    /**
     * Login/register sonrası user mode'a geçildi — guest_cart_token
     * cookie'si artık taşıdığı bilgi merge ile user_id'ye aktarıldı.
     * Cookie'yi expire edip browser'dan sildir.
     */
    private function clearGuestCookieIfPresent(JsonResponse $response, ?string $guestSessionId): JsonResponse
    {
        if ($guestSessionId === null) {
            return $response;
        }
        $response->headers->setCookie(Cookie::create(
            name: GuestSessionMiddleware::COOKIE_NAME,
            value: '',
            expire: 1,
            path: '/',
            domain: null,
            secure: app()->environment() !== 'testing',
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        ));
        return $response;
    }
}
