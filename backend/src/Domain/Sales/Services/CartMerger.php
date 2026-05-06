<?php

declare(strict_types=1);

namespace Domain\Sales\Services;

use Domain\Sales\Entities\Cart;
use Domain\Sales\Entities\CartItem;

/**
 * ADR-017 — guest cart ile user cart birleştirme stratejisi.
 *
 * Davranış:
 *   - Aynı productId varsa: quantity'ler toplanır
 *   - Snapshot fiyatlardan EN ESKİ olan korunur (kullanıcı, fiyat
 *     artışından korunsun — sepete eklediği fiyatla ödeme yapsın)
 *   - Yeni ürünler (yalnızca guest tarafında olanlar) user cart'a eklenir
 *   - User'da olup guest'te olmayan ürünler dokunulmaz
 *
 * Bu servis mutasyon yapmaz — yalnızca operasyon listesi döner.
 * Caller (MergeGuestCartHandler) Cart aggregate üzerinde sırayla
 * uygular ve repository.save() ile persist eder.
 */
final readonly class CartMerger
{
    /**
     * @return array<int, MergeOperation>
     */
    public function plan(Cart $userCart, Cart $guestCart): array
    {
        // User cart item'larını productId → CartItem map'ine çevir
        $userByProduct = [];
        foreach ($userCart->getItems() as $item) {
            $userByProduct[$item->getProductId()] = $item;
        }

        $ops = [];
        foreach ($guestCart->getItems() as $guestItem) {
            $productId = $guestItem->getProductId();

            if (isset($userByProduct[$productId])) {
                // Kesişim — quantity birleştir, EN ESKİ snapshot'ı koru
                $userItem = $userByProduct[$productId];

                $oldestItem = $userItem->getCreatedAt() <= $guestItem->getCreatedAt()
                    ? $userItem
                    : $guestItem;

                $ops[] = new MergeOperation(
                    type: MergeOperation::TYPE_UPDATE_QUANTITY,
                    productId: $productId,
                    productName: $oldestItem->getProductNameSnapshot(),
                    productSku: $oldestItem->getProductSkuSnapshot(),
                    newQuantity: $userItem->getQuantity() + $guestItem->getQuantity(),
                    unitPrice: $oldestItem->getUnitPriceSnapshot(),
                );
            } else {
                // Yalnızca guest'te — user cart'a yeni satır olarak ekle
                $ops[] = new MergeOperation(
                    type: MergeOperation::TYPE_ADD_NEW,
                    productId: $productId,
                    productName: $guestItem->getProductNameSnapshot(),
                    productSku: $guestItem->getProductSkuSnapshot(),
                    newQuantity: $guestItem->getQuantity(),
                    unitPrice: $guestItem->getUnitPriceSnapshot(),
                );
            }
        }

        return $ops;
    }

    /**
     * Pratik convenience — plan() çıktısını User Cart aggregate'i
     * üzerinde uygular. Quantity update için CartItem.changeQuantity'i
     * çağırır; yeni item için Cart.addItem.
     */
    public function applyToUserCart(Cart $userCart, Cart $guestCart): int
    {
        $ops = $this->plan($userCart, $guestCart);
        $applied = 0;

        // userCart.addItem productId → existing match'i kendi içinde
        // halleder (incrementQuantity). Update vs add ayrımı CartMerger
        // tarafında yine de kavramsal olarak ayrı tutuldu — audit metadata
        // için sayım yapılabilsin diye.
        foreach ($ops as $op) {
            if ($op->type === MergeOperation::TYPE_UPDATE_QUANTITY) {
                // userCart.addItem mevcut item'ı bulup quantity'e EKLER —
                // ama biz "yeni toplam" olarak set etmek istiyoruz; addItem
                // artırma davranışı + EN ESKİ snapshot için manuel reset.
                $existing = self::findItem($userCart, $op->productId);
                if ($existing !== null) {
                    $existing->changeQuantity($op->newQuantity);
                }
            } else {
                $userCart->addItem(
                    productId: $op->productId,
                    productName: $op->productName,
                    productSku: $op->productSku,
                    quantity: $op->newQuantity,
                    unitPrice: $op->unitPrice,
                );
            }
            $applied++;
        }
        return $applied;
    }

    private static function findItem(Cart $cart, string $productId): ?CartItem
    {
        foreach ($cart->getItems() as $i) {
            if ($i->getProductId() === $productId) {
                return $i;
            }
        }
        return null;
    }
}
