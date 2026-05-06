<?php

declare(strict_types=1);

namespace Application\System\Handlers\Queries;

use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Application\System\DTOs\SettingDTO;
use Application\System\Queries\GetAllSettingsQuery;
use Domain\System\Contracts\SettingRepositoryInterface;
use Psr\Log\LoggerInterface;

final class GetAllSettingsHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetAllSettingsQuery::class;
    }

    /** @return array<int, SettingDTO> */
    protected function doHandle(QueryInterface $query): array
    {
        assert($query instanceof GetAllSettingsQuery);

        $settings = $query->publicOnly
            ? $this->repository->findPublic()
            : $this->repository->findAll();

        return SettingDTO::collection($settings);
    }
}
