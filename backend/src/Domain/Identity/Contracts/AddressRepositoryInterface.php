<?php

declare(strict_types=1);

namespace Domain\Identity\Contracts;

use Domain\Identity\Entities\Address;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Shared\Contracts\RepositoryInterface;
use Domain\Shared\ValueObjects\UserId;

/**
 * @extends RepositoryInterface<Address, AddressId>
 *
 * ADR-018 — Address aggregate root persistence sözleşmesi. Tüm
 * find* metodları soft-delete'leri filtreler (deleted_at IS NULL).
 * Order snapshot için historical lookup gerekirse withTrashed
 * varyant ileride eklenebilir.
 */
interface AddressRepositoryInterface extends RepositoryInterface
{
    public function findById(AddressId $id): ?Address;

    /**
     * IDOR koruması — başka kullanıcının adresine erişim 404 döner.
     * Controller her detail/update/delete'te bu metodu kullanmalı.
     */
    public function findByIdAndUser(AddressId $id, UserId $userId): ?Address;

    /**
     * Listings için sıralama: önce default delivery, sonra default billing,
     * en son createdAt DESC (yeni eklenen üstte).
     *
     * @return array<int, Address>
     */
    public function findByUserId(UserId $userId): array;

    public function findDefaultDelivery(UserId $userId): ?Address;

    public function findDefaultBilling(UserId $userId): ?Address;

    public function countByUserId(UserId $userId): int;

    public function save(Address $address): void;

    /**
     * Soft delete + default flag'leri sıfırla — silinen adres
     * partial unique index'i dolduramasın.
     */
    public function softDelete(AddressId $id): void;

    /**
     * En son createdAt sahip aktif adresi döner. Default adres
     * silindikten sonra "otomatik bir başkasını default yap"
     * politikası için kullanılır.
     */
    public function findMostRecent(UserId $userId): ?Address;

    /**
     * Bulk update: kullanıcının tüm aktif default delivery flag'lerini
     * unset eder. Yeni default set edilirken eski default'u temizlemek
     * için CreateAddressHandler / SetDefaultDeliveryHandler kullanır
     * (DB partial unique index'i tek default garanti eder).
     */
    public function unsetAllDefaultDelivery(UserId $userId): void;

    public function unsetAllDefaultBilling(UserId $userId): void;
}
