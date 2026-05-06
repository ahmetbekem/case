<?php

declare(strict_types=1);

namespace Domain\Sales\Services;

use Domain\Shared\ValueObjects\Money;

/**
 * CartMerger.plan() çıktısı — mutasyon DEĞİL, niyet kaydı.
 * Caller (handler) bu listeyi User Cart aggregate'i üzerinde uygular.
 */
final readonly class MergeOperation
{
    public const string TYPE_ADD_NEW = 'add_new';
    public const string TYPE_UPDATE_QUANTITY = 'update_quantity';

    public function __construct(
        public string $type,
        public string $productId,
        public string $productName,
        public string $productSku,
        public int $newQuantity,
        public Money $unitPrice,
    ) {
    }
}
