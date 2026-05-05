<?php

declare(strict_types=1);

namespace Domain\Catalog\Entities;

use Domain\Catalog\Exceptions\InsufficientStockException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;

final class Product extends AbstractEntity
{
    public function __construct(
        private readonly ProductId $id,
        private string $sku,
        private string $name,
        private ?string $description,
        private Money $price,
        private int $stock,
        private bool $isActive,
        private readonly UserId $createdBy,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        if ($stock < 0) {
            throw new \InvalidArgumentException('Initial stock cannot be negative');
        }
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ProductId
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedBy(): UserId
    {
        return $this->createdBy;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }
        $this->name = $name;
        $this->markAsUpdated();
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description !== null ? trim($description) : null;
        $this->markAsUpdated();
    }

    public function changePrice(Money $price): void
    {
        $this->price = $price;
        $this->markAsUpdated();
    }

    /**
     * Decrease stock — throws if it would dip below zero. Used by Sales
     * during PlaceOrder (TASK 0.11).
     *
     * @throws InsufficientStockException
     */
    public function decreaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity to decrease must be positive');
        }
        if ($this->stock < $quantity) {
            throw InsufficientStockException::forProduct($this->id, $this->stock, $quantity);
        }
        $this->stock -= $quantity;
        $this->markAsUpdated();
    }

    public function increaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity to increase must be positive');
        }
        $this->stock += $quantity;
        $this->markAsUpdated();
    }

    /**
     * Direct stock set — admin restock or correction. Negative values
     * rejected; entity invariant `stock >= 0` matches the DB CHECK.
     */
    public function setStock(int $stock): void
    {
        if ($stock < 0) {
            throw new \InvalidArgumentException('Stock cannot be negative');
        }
        $this->stock = $stock;
        $this->markAsUpdated();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->markAsUpdated();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->markAsUpdated();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price->amount,
            'currency' => $this->price->currency->value,
            'stock' => $this->stock,
            'isActive' => $this->isActive,
            'createdBy' => $this->createdBy->value(),
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: ProductId::fromString((string) $data['id']),
            sku: (string) $data['sku'],
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            price: new Money((float) $data['price'], Currency::from((string) $data['currency'])),
            stock: (int) $data['stock'],
            isActive: (bool) ($data['isActive'] ?? true),
            createdBy: UserId::fromString((string) $data['createdBy']),
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt'])
                ? self::parseTimestamp($data['updatedAt'])
                : null,
        );
    }
}
