<?php

declare(strict_types=1);

namespace Domain\Identity\Contracts;

use Domain\Identity\ValueObjects\HashedPassword;
use Domain\Identity\ValueObjects\PlainPassword;

interface PasswordHasherInterface
{
    public function hash(PlainPassword $plain): HashedPassword;

    public function verify(PlainPassword $plain, HashedPassword $hashed): bool;

    public function needsRehash(HashedPassword $hashed): bool;
}
