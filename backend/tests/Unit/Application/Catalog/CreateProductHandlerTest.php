<?php

declare(strict_types=1);

use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\DTOs\ProductDTO;
use Application\Catalog\Handlers\Commands\CreateProductHandler;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\DuplicateSkuException;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->repo = Mockery::mock(ProductRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLoggerInterface::class);
    $this->transaction = Mockery::mock(TransactionInterface::class);
    $this->transaction->shouldReceive('run')->andReturnUsing(fn ($cb) => $cb());

    $this->handler = new CreateProductHandler(
        $this->repo,
        $this->audit,
        $this->transaction,
        new NullLogger(),
    );
});

function makeCreateCmd(string $sku = 'NEW-001'): CreateProductCommand
{
    return new CreateProductCommand(
        userId: '11111111-2222-3333-4444-555555555555',
        correlationId: 'corr-1',
        userIp: '127.0.0.1',
        sku: $sku,
        name: 'New Product',
        description: 'Desc',
        price: 199.99,
        currency: 'TRY',
        stock: 50,
    );
}

it('creates product, saves, audits, returns DTO', function () {
    $this->repo->shouldReceive('existsBySku')->with('NEW-001')->andReturnFalse();
    $this->repo->shouldReceive('save')->once();
    $this->audit->shouldReceive('log')->once()->withArgs(function ($event) {
        expect($event->action)->toBe('catalog.product_create')
            ->and($event->module)->toBe('catalog')
            ->and($event->result)->toBe('success');
        return true;
    });

    $result = $this->handler->handle(makeCreateCmd());

    expect($result)->toBeInstanceOf(ProductDTO::class)
        ->and($result->sku)->toBe('NEW-001')
        ->and($result->price)->toBe(199.99)
        ->and($result->currency)->toBe('TRY')
        ->and($result->stock)->toBe(50)
        ->and($result->isActive)->toBeTrue();
});

it('rejects duplicate SKU before save / audit', function () {
    $this->repo->shouldReceive('existsBySku')->with('DUPLICATE')->andReturnTrue();
    $this->repo->shouldNotReceive('save');
    $this->audit->shouldNotReceive('log');

    $this->handler->handle(makeCreateCmd('DUPLICATE'));
})->throws(DuplicateSkuException::class);
