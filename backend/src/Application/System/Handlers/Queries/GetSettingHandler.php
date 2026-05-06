<?php

declare(strict_types=1);

namespace Application\System\Handlers\Queries;

use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Application\System\DTOs\SettingDTO;
use Application\System\Queries\GetSettingQuery;
use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Exceptions\SettingNotFoundException;
use Psr\Log\LoggerInterface;

final class GetSettingHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetSettingQuery::class;
    }

    protected function doHandle(QueryInterface $query): SettingDTO
    {
        assert($query instanceof GetSettingQuery);

        $setting = $this->repository->findByKey($query->key);
        if ($setting === null) {
            throw SettingNotFoundException::withKey($query->key);
        }

        return SettingDTO::fromEntity($setting);
    }
}
