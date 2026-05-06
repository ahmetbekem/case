<?php

declare(strict_types=1);

namespace Application\System\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class UpdateSettingCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $key,
        public string $value,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'UpdateSetting';
    }
}
