<?php

declare(strict_types=1);

use Application\Shared\Contracts\TransactionInterface;
use Application\System\Commands\UpdateSettingCommand;
use Application\System\DTOs\SettingDTO;
use Application\System\Handlers\Commands\UpdateSettingHandler;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Entities\Setting;
use Domain\System\Exceptions\SettingNotFoundException;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->repo = Mockery::mock(SettingRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLoggerInterface::class);
    $this->tx = Mockery::mock(TransactionInterface::class);
    $this->tx->shouldReceive('run')->andReturnUsing(fn ($cb) => $cb());

    $this->handler = new UpdateSettingHandler($this->repo, $this->audit, $this->tx, new NullLogger());
});

it('updates an existing setting + emits audit', function () {
    $existing = Setting::create('base_currency', 'TRY', 'string', 'currency', null, true);
    $this->repo->shouldReceive('findByKey')->with('base_currency')->andReturn($existing);
    $this->repo->shouldReceive('save')->once()->with($existing);
    $this->audit->shouldReceive('log')->once();

    $cmd = new UpdateSettingCommand(
        userId: '00000000-0000-0000-0000-000000000001',
        correlationId: 'cor-1',
        userIp: '127.0.0.1',
        key: 'base_currency',
        value: 'USD',
    );

    $dto = $this->handler->handle($cmd);

    expect($dto)->toBeInstanceOf(SettingDTO::class)
        ->and($dto->value)->toBe('USD')
        ->and($existing->getValue())->toBe('USD');
});

it('throws 404 when key unknown', function () {
    $this->repo->shouldReceive('findByKey')->with('nope')->andReturn(null);

    $cmd = new UpdateSettingCommand(
        userId: '00000000-0000-0000-0000-000000000001',
        correlationId: 'cor-1',
        userIp: '127.0.0.1',
        key: 'nope',
        value: 'x',
    );

    expect(fn () => $this->handler->handle($cmd))->toThrow(SettingNotFoundException::class);
});
