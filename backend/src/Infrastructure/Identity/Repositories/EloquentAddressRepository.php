<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Repositories;

use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Identity\Entities\Address;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;
use Infrastructure\Identity\Mappers\AddressMapper;
use Infrastructure\Identity\Models\AddressModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<Address, AddressId, AddressModel>
 *
 * ADR-018 — Soft delete'li model üzerinden listings + IDOR-safe lookup'lar.
 * AddressModel'in SoftDeletes trait'i query()'de otomatik
 * `deleted_at IS NULL` filter'ı uygular.
 */
final class EloquentAddressRepository extends AbstractEloquentRepository implements AddressRepositoryInterface
{
    public function __construct(AddressMapper $mapper)
    {
        parent::__construct($mapper);
    }

    protected function getModelClass(): string
    {
        return AddressModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return AddressId::fromString($id);
    }

    public function findById(AddressId $id): ?Address
    {
        $model = $this->query()->where('id', $id->value())->first();
        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function findByIdAndUser(AddressId $id, UserId $userId): ?Address
    {
        $model = $this->query()
            ->where('id', $id->value())
            ->where('user_id', $userId->value())
            ->first();
        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function findByUserId(UserId $userId): array
    {
        $models = $this->query()
            ->where('user_id', $userId->value())
            ->orderByDesc('is_default_delivery')
            ->orderByDesc('is_default_billing')
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn ($m) => $this->mapper->toEntity($m))->all();
    }

    public function findDefaultDelivery(UserId $userId): ?Address
    {
        $model = $this->query()
            ->where('user_id', $userId->value())
            ->where('is_default_delivery', true)
            ->first();
        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function findDefaultBilling(UserId $userId): ?Address
    {
        $model = $this->query()
            ->where('user_id', $userId->value())
            ->where('is_default_billing', true)
            ->first();
        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function countByUserId(UserId $userId): int
    {
        return $this->query()->where('user_id', $userId->value())->count();
    }

    public function findMostRecent(UserId $userId): ?Address
    {
        $model = $this->query()
            ->where('user_id', $userId->value())
            ->orderByDesc('created_at')
            ->first();
        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function save(Address $address): void
    {
        AddressModel::query()->updateOrInsert(
            ['id' => $address->getId()->value()],
            $this->mapper->toPersistence($address),
        );
    }

    public function softDelete(AddressId $id): void
    {
        // Default flag'leri sıfırla — silinen adres partial unique
        // index'i dolduramasın (yeni default gelebilsin). Soft delete'i
        // ayrı tutarak deleted_at + flags atomik update.
        AddressModel::query()
            ->where('id', $id->value())
            ->update([
                'deleted_at' => now(),
                'is_default_delivery' => false,
                'is_default_billing' => false,
                'updated_at' => now(),
            ]);
    }

    public function unsetAllDefaultDelivery(UserId $userId): void
    {
        AddressModel::query()
            ->where('user_id', $userId->value())
            ->where('is_default_delivery', true)
            ->update([
                'is_default_delivery' => false,
                'updated_at' => now(),
            ]);
    }

    public function unsetAllDefaultBilling(UserId $userId): void
    {
        AddressModel::query()
            ->where('user_id', $userId->value())
            ->where('is_default_billing', true)
            ->update([
                'is_default_billing' => false,
                'updated_at' => now(),
            ]);
    }
}
