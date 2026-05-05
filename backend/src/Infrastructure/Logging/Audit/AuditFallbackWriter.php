<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Audit;

/**
 * NDJSON fallback log for audit envelopes that AuditLogger could not
 * publish to RabbitMQ (broker down, confirm timeout, network blip).
 *
 * Recovery happens out-of-band via `php artisan logging:replay-audit-fallback`
 * which reads this file and re-publishes each envelope.
 */
// Non-final for the same reason as RabbitMqPublisher — Mockery test mock.
class AuditFallbackWriter
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Append a single envelope as one NDJSON line. LOCK_EX prevents two
     * concurrent PHP-FPM workers from interleaving partial writes.
     *
     * @param array<string, mixed> $envelope
     */
    public function write(array $envelope): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Yield each previously-written envelope, decoded. Skips malformed
     * lines silently so a single corrupt entry doesn't block recovery.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function readEntries(): \Generator
    {
        if (!is_file($this->path)) {
            return;
        }
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            return;
        }
        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    yield $decoded;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function exists(): bool
    {
        return is_file($this->path) && filesize($this->path) > 0;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
