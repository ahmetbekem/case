<?php

declare(strict_types=1);

namespace Domain\System\Contracts;

use Domain\System\Entities\Setting;

interface SettingRepositoryInterface
{
    public function findByKey(string $key): ?Setting;

    /** @return array<int, Setting> */
    public function findAll(): array;

    /** @return array<int, Setting> */
    public function findPublic(): array;

    public function save(Setting $setting): void;
}
