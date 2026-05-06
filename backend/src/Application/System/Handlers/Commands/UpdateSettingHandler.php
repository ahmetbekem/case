<?php

declare(strict_types=1);

namespace Application\System\Handlers\Commands;

use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Application\System\Commands\UpdateSettingCommand;
use Application\System\DTOs\SettingDTO;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Exceptions\SettingNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Yeni ayar yaratma şu an UI'dan yok — yalnızca seeder'la başlayan
 * sabit set güncellenir. find→null ise 404; varsa changeValue + save.
 *
 * Audit: actor + key + value snapshot (eski → yeni karşılaştırması istenirse
 * sonradan repository'den önceki value'yu fetch edilebilir).
 */
final class UpdateSettingHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return UpdateSettingCommand::class;
    }

    protected function doHandle(CommandInterface $command): SettingDTO
    {
        assert($command instanceof UpdateSettingCommand);

        $setting = $this->repository->findByKey($command->key);
        if ($setting === null) {
            throw SettingNotFoundException::withKey($command->key);
        }

        $oldValue = $setting->getValue();
        $setting->changeValue($command->value);
        $this->repository->save($setting);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'system.setting_update',
            resource: 'setting:' . $command->key,
            module: 'system',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'key' => $command->key,
                'oldValue' => $oldValue,
                'newValue' => $command->value,
            ],
        ));

        return SettingDTO::fromEntity($setting);
    }
}
