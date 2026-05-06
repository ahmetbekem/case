<?php

declare(strict_types=1);

namespace Infrastructure\System\Repositories;

use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Entities\Setting;
use Infrastructure\System\Mappers\SettingMapper;
use Infrastructure\System\Models\SettingModel;

final class EloquentSettingRepository implements SettingRepositoryInterface
{
    public function __construct(
        private readonly SettingMapper $mapper,
    ) {
    }

    public function findByKey(string $key): ?Setting
    {
        $model = SettingModel::query()->where('key', $key)->first();
        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function findAll(): array
    {
        return SettingModel::query()
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->map(fn (SettingModel $m) => $this->mapper->toEntity($m))
            ->all();
    }

    public function findPublic(): array
    {
        return SettingModel::query()
            ->where('is_public', true)
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->map(fn (SettingModel $m) => $this->mapper->toEntity($m))
            ->all();
    }

    public function save(Setting $setting): void
    {
        SettingModel::query()->updateOrInsert(
            ['id' => $setting->getId()],
            $this->mapper->toPersistence($setting),
        );
    }
}
