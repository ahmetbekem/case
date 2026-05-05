<?php

declare(strict_types=1);

use Domain\Logging\ValueObjects\AuditEvent;
use Infrastructure\Logging\Audit\AuditFallbackWriter;
use Infrastructure\Logging\Audit\AuditLogger;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->publisher = Mockery::mock(RabbitMqPublisher::class);
    $this->fallback = Mockery::mock(AuditFallbackWriter::class);
    $this->fallback->shouldReceive('getPath')->andReturn('/tmp/audit-fallback.log');
    $this->logger = new AuditLogger($this->publisher, $this->fallback, new NullLogger());
});

function makeAuditEvent(): AuditEvent
{
    return new AuditEvent(
        actor: 'userId:abc-123',
        actorIp: '127.0.0.1',
        action: 'identity.login',
        resource: 'user:abc-123',
        module: 'identity',
        result: 'success',
        correlationId: 'corr-test-1',
    );
}

it('publishes the envelope with publisher confirm and does not fall back on success', function () {
    $this->publisher->shouldReceive('publishWithConfirm')
        ->once()
        ->withArgs(function (string $exchange, string $routingKey, array $payload, int $timeoutMs) {
            expect($exchange)->toBe('logs.exchange')
                ->and($routingKey)->toBe('log.audit.identity.identity_login')
                ->and($timeoutMs)->toBe(2000)
                ->and($payload)->toHaveKeys(['id', 'category', 'publishedAt', 'source', 'version', 'payload'])
                ->and($payload['category'])->toBe('audit')
                ->and($payload['payload']['action'])->toBe('identity.login');
            return true;
        })
        ->andReturn(true);

    $this->fallback->shouldNotReceive('write');

    $this->logger->log(makeAuditEvent());
});

it('writes to fallback when publisher confirm times out (returns false)', function () {
    $this->publisher->shouldReceive('publishWithConfirm')->once()->andReturn(false);
    $this->fallback->shouldReceive('write')->once()->withArgs(function (array $envelope) {
        expect($envelope['category'])->toBe('audit')
            ->and($envelope['payload']['action'])->toBe('identity.login');
        return true;
    });

    $this->logger->log(makeAuditEvent());
});

it('writes to fallback when the AMQP layer throws', function () {
    $this->publisher->shouldReceive('publishWithConfirm')
        ->once()
        ->andThrow(new \RuntimeException('Connection refused'));
    $this->fallback->shouldReceive('write')->once();

    // Must not bubble — handler callers cannot tolerate audit publish failures
    $this->logger->log(makeAuditEvent());
});

it('flattens dots in the action so the AMQP topic does not get split', function () {
    $event = new AuditEvent(
        actor: 'system',
        actorIp: 'unknown',
        action: 'security.refresh_token_replay',  // already underscore — stays
        resource: 'family:foo',
        module: 'identity',
        result: 'failure',
        correlationId: 'cid-1',
    );

    $this->publisher->shouldReceive('publishWithConfirm')
        ->once()
        ->withArgs(function (string $_, string $routingKey) {
            expect($routingKey)->toBe('log.audit.identity.security_refresh_token_replay');
            return true;
        })
        ->andReturn(true);

    $this->logger->log($event);
});
