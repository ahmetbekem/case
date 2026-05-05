<?php

declare(strict_types=1);

namespace Presentation\Shared\Documentation;

use OpenApi\Attributes as OA;

// Tek dosyada toplanan ortak şemalar — modül controller'larında
// `ref: '#/components/schemas/<isim>'` ile yeniden kullanılır.
// Hata zarfı bootstrap/app.php'deki withExceptions render kuralı ile birebir aynı
// format; ValidationError Laravel default'una uygun (message + errors map).

#[OA\Schema(
    schema: 'PaginationMeta',
    description: 'Sayfalama meta verisi — listeleme cevaplarında `meta` altında döner.',
    properties: [
        new OA\Property(property: 'currentPage', type: 'integer', example: 1),
        new OA\Property(property: 'pageSize', type: 'integer', example: 20),
        new OA\Property(property: 'total', type: 'integer', example: 150),
        new OA\Property(property: 'totalPages', type: 'integer', example: 8),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    description: 'Laravel default 422 cevabı — validation kuralları başarısız.',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
            example: ['email' => ['Email is required.']],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UnauthorizedError',
    description: 'Auth gerekli ama eksik/geçersiz token.',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'UNAUTHORIZED'),
                new OA\Property(property: 'message', type: 'string', example: 'Token gerekli veya geçersiz.'),
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    description: 'Auth var ama bu işlem için yetki (ör. admin rolü) yok.',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'FORBIDDEN'),
                new OA\Property(property: 'message', type: 'string', example: 'Bu işlem için yetkiniz yok.'),
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'NotFoundError',
    description: 'Resource bulunamadı — IDOR pattern gereği yetkisiz erişim de bu cevabı alır.',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'NOT_FOUND'),
                new OA\Property(property: 'message', type: 'string', example: 'Kaynak bulunamadı.'),
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ConflictError',
    description: 'Business rule ihlali (duplicate SKU, yetersiz stok, sipariş zaten iptal vs.).',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'CONFLICT'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(
                    property: 'context',
                    type: 'object',
                    description: 'Opsiyonel debug bilgisi.',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                ),
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RateLimitError',
    description: 'Rate limit aşıldı — Retry-After response header\'ında saniye olarak döner.',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'RATE_LIMIT_EXCEEDED'),
                new OA\Property(property: 'message', type: 'string', example: 'Çok fazla istek. Lütfen bekleyin.'),
            ],
        ),
    ],
    type: 'object',
)]
final class CommonSchemas
{
    // Sadece annotation taşıyıcı — instance hiçbir zaman oluşturulmaz.
}
