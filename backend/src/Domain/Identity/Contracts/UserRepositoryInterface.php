<?php

declare(strict_types=1);

namespace Domain\Identity\Contracts;

use Domain\Identity\Entities\User;
use Domain\Identity\ValueObjects\Email;
use Domain\Shared\Contracts\RepositoryInterface;
use Domain\Shared\ValueObjects\UserId;

/**
 * @extends RepositoryInterface<User, UserId>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function existsByEmail(Email $email): bool;

    public function save(User $user): void;

    public function softDelete(UserId $id): void;
}
