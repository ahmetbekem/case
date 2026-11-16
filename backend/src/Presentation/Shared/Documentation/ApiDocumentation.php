<?php

declare(strict_types=1);

namespace Presentation\Shared\Documentation;

use OpenApi\Attributes as OA;

/**
 * OpenAPI 3.0 root info — boş sınıf, sadece üst-düzey #[OA\*] attribute'larını
 * tutmak için var. l5-swagger generate src/Presentation altını taradığında
 * bu dosyadan Info, Server, SecurityScheme ve Tag tanımlarını toplar.
 *
 * `bearerAuth` development try-it-out için (Authorization: Bearer <jwt>),
 * `cookieAuth` ise gerçek production akışını yansıtıyor (httpOnly cookie).
 * Default security cookieAuth — endpoint'ler isterlerse override eder.
 */
#[OA\Info(
    version: '1.0.0',
    description: 'E-Ticaret Demo Projesi — basit e-ticaret API. Tüm public istekler '
        . 'Next.js BFF üzerinden geçer; direkt backend erişimi X-Proxy-Secret '
        . 'header doğrulaması ile reddedilir (ProxyOriginMiddleware).',
    title: 'E-Ticaret Demo API',
    contact: new OA\Contact(name: 'Ahmet Bekem', email: 'ahmet@example.com'),
)]
#[OA\Server(
    url: 'http://localhost:8000/api/v1',
    description: 'Local development (docker compose)',
)]
#[OA\Server(
    url: 'https://api.example.com/api/v1',
    description: 'Production',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT bearer token — `POST /auth/login` cevabındaki accessToken.',
)]
#[OA\SecurityScheme(
    securityScheme: 'cookieAuth',
    type: 'apiKey',
    in: 'cookie',
    name: 'access_token',
    description: 'httpOnly cookie — login sonrası tarayıcı otomatik gönderir; '
        . 'BFF proxy üzerinden tek yönlü kullanılır.',
)]
#[OA\Tag(name: 'Authentication', description: 'Login, register, token operations')]
#[OA\Tag(name: 'Users (Admin)', description: 'Admin-only kullanıcı yönetimi')]
#[OA\Tag(name: 'Catalog', description: 'Public ürün okuma')]
#[OA\Tag(name: 'Catalog (Admin)', description: 'Admin-only ürün yönetimi')]
#[OA\Tag(name: 'Cart', description: 'Sepet işlemleri (kullanıcı kendi sepeti)')]
#[OA\Tag(name: 'Orders', description: 'Sipariş oluşturma ve takip')]
#[OA\Tag(name: 'Orders (Admin)', description: 'Admin sipariş yönetimi + dashboard')]
#[OA\Tag(name: 'Currency', description: 'Public döviz kurları')]
#[OA\Tag(name: 'Currency (Admin)', description: 'Admin sync + tarihsel kur verisi')]
#[OA\Tag(name: 'System', description: 'Healthcheck, public settings ve alt-yapı endpoint\'leri')]
#[OA\Tag(name: 'System (Admin)', description: 'Admin-only sistem ayarları yönetimi (base_currency vb.)')]
final class ApiDocumentation
{
    /**
     * Laravel default health endpoint'inin (/up) OpenAPI tanımı —
     * controller'sız route olduğu için annotation'ı buraya yazıyoruz.
     */
    #[OA\Get(
        path: '/up',
        summary: 'Healthcheck',
        description: 'Laravel built-in /up endpoint. Auth gerekmez. '
            . 'Compose healthcheck ve dış monitoring tarafından kullanılır.',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'Servis ayakta'),
            new OA\Response(response: 503, description: 'Servis sağlıksız'),
        ],
    )]
    public function health(): void
    {
        // Sadece annotation taşıyıcı — instance hiçbir zaman oluşturulmaz.
    }
}
