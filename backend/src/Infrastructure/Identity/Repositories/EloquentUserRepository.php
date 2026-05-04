<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Repositories;

use Domain\Identity\Contracts\UserRepositoryInterface;
use Domain\Identity\Entities\User;
use Domain\Identity\ValueObjects\Email;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;
use Infrastructure\Identity\Mappers\UserMapper;
use Infrastructure\Identity\Models\UserModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<User, UserId, UserModel>
 */
final class EloquentUserRepository extends AbstractEloquentRepository implements UserRepositoryInterface
{
    public function __construct(UserMapper $mapper)
    {
        parent::__construct($mapper);
    }

    protected function getModelClass(): string
    {
        return UserModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return UserId::fromString($id);
    }

    public function findById(UserId $id): ?User
    {
        $model = $this->query()->where('id', $id->value())->first();
        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $model = $this->query()->where('email', $email->value)->first();
        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->query()->where('email', $email->value)->exists();
    }

    public function save(User $user): void
    {
        $data = $this->mapper->toPersistence($user);
        UserModel::query()->updateOrInsert(
            ['id' => $user->getId()->value()],
            $data,
        );
    }

    public function softDelete(UserId $id): void
    {
        $this->query()->where('id', $id->value())->update([
            'deleted_at' => now(),
        ]);
    }
}
