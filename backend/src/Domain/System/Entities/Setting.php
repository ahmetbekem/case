<?php

declare(strict_types=1);

namespace Domain\System\Entities;

use Ramsey\Uuid\Uuid;

/**
 * System ayar entry — key-value mağaza satırı.
 *
 * AbstractEntity'den kasıtlı olarak extend edilmedi: o sınıf getId()
 * imzasını ValueObjectInterface dönüşüne kilitliyor; settings için
 * UUID string yeterli (key zaten benzersiz domain identifier'ı).
 *
 * Type field okuma ipucu: caller `getValue()` raw string alır, gerekirse
 * `getTypedValue()` ile json_decode/cast yapar.
 */
final class Setting
{
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly string $id,
        private readonly string $key,
        private string $value,
        private readonly string $type,
        private readonly string $category,
        private readonly ?string $description,
        private readonly bool $isPublic,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        string $key,
        string $value,
        string $type = 'string',
        string $category = 'general',
        ?string $description = null,
        bool $isPublic = false,
    ): self {
        return new self(
            id: Uuid::uuid4()->toString(),
            key: $key,
            value: $value,
            type: $type,
            category: $category,
            description: $description,
            isPublic: $isPublic,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /** Type-aware decode — caller'ın JSON decode etmesi gerekmesin diye */
    public function getTypedValue(): string|int|float|bool|array|null
    {
        return match ($this->type) {
            'number' => is_numeric($this->value)
                ? (str_contains($this->value, '.') ? (float) $this->value : (int) $this->value)
                : null,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeValue(string $newValue): void
    {
        if ($newValue === $this->value) {
            return; // no-op — gereksiz markAsUpdated, audit gürültüsü azaltır
        }
        $this->value = $newValue;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
