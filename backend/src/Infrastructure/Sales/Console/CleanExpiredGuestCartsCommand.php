<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Console;

use Domain\Sales\Contracts\CartRepositoryInterface;
use Illuminate\Console\Command;

/**
 * ADR-017 — Süresi geçmiş aktif guest cart'ları siler. Schedule
 * routes/console.php içinden günlük tetiklenir. cart_items FK ON
 * DELETE CASCADE olduğundan ilgili kalemler de gider.
 *
 * Yalnızca status=active + guest_session_id IS NOT NULL +
 * expires_at <= now() satırları silinir; converted/abandoned analytics
 * için korunur.
 *
 * Usage:
 *   php artisan sales:clean-guest-carts
 */
final class CleanExpiredGuestCartsCommand extends Command
{
    protected $signature = 'sales:clean-guest-carts';

    protected $description = 'Delete guest carts that have expired (30+ days old)';

    public function handle(CartRepositoryInterface $cartRepository): int
    {
        $deleted = $cartRepository->deleteExpiredGuestCarts();
        $this->info("Deleted {$deleted} expired guest cart(s).");
        return self::SUCCESS;
    }
}
