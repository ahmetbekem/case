# 📐 E-Ticaret Demo Projesi - Coding Standards & Patterns

> **CRITICAL:** Bu doküman backend (Laravel) için tüm kod template'lerini ve pattern'leri tanımlar. **Tüm kod kesinlikle bu kalıplara uymak zorundadır.**

**Version:** 1.0
**Stack:** PHP 8.2+ (Strict Types), Laravel 11
**Patterns:** Onion Architecture + CQRS + DDD (Lite)
**Test Framework:** Pest 3.x

---

## 📋 TABLE OF CONTENTS

1. [Universal Rules](#1-universal-rules)
2. [Value Objects](#2-value-objects)
3. [Entities](#3-entities)
4. [Domain Services](#4-domain-services)
5. [Domain Exceptions](#5-domain-exceptions)
6. [Commands](#6-commands)
7. [Queries](#7-queries)
8. [Command Handlers](#8-command-handlers)
9. [Query Handlers](#9-query-handlers)
10. [DTOs](#10-dtos)
11. [Repository Interfaces](#11-repository-interfaces)
12. [Eloquent Models](#12-eloquent-models)
13. [Mappers](#13-mappers)
14. [Repository Implementations](#14-repository-implementations)
15. [Controllers](#15-controllers)
16. [Form Requests](#16-form-requests)
17. [Service Provider Registration](#17-service-provider-registration)
18. [Routes](#18-routes)
19. [Testing Standards](#19-testing-standards)
20. [Logging Integration](#20-logging-integration)
21. [Forbidden Patterns](#21-forbidden-patterns)

---

## 1. UNIVERSAL RULES

### 1.1 PHP File Header

Her PHP dosyası şu satırlarla başlar:

```php
<?php

declare(strict_types=1);

namespace ...;
```

### 1.2 Class Modifiers

| Class Type | Modifier |
|-----------|----------|
| Entity | `final` |
| Value Object | `final readonly` |
| Command | `final readonly` |
| Query | `final readonly` |
| DTO | `final readonly` |
| Handler | `final` |
| Repository Implementation | `final` |
| Mapper | `final` |
| Controller | `final` |
| FormRequest | `final` |
| Exception | `final` |

### 1.3 Property Visibility

- ✅ Entity properties: `private`
- ✅ Command/Query/DTO properties: `public readonly`
- ✅ Handler dependencies: `private readonly`

### 1.4 Type Declarations

- ✅ Tüm parametreler type-hinted
- ✅ Tüm return type'lar deklare edilmiş
- ✅ `mixed` kullanımı **istisnai durumlar** için (CommandInterface return type vs.)
- ❌ `array` return type'ında PHPDoc ile eleman tipini belirt: `@return array<int, ProductDTO>`

---

## 2. VALUE OBJECTS

### 2.1 UUID-Based Value Object

**Kullanım:** Tüm entity ID'leri için.

```php
<?php

declare(strict_types=1);

namespace Domain\Catalog\ValueObjects;

use Domain\Shared\Abstracts\AbstractUuidValueObject;

/**
 * Product Unique Identifier.
 */
final readonly class ProductId extends AbstractUuidValueObject
{
    // Base class everything handles. No additional code.
}
```

**Usage:**
```php
$id = ProductId::generate();              // Yeni UUID v4
$id = ProductId::fromString($uuidString); // Validation ile parse
$id->value();                              // string döner
$id->equals($otherId);                     // bool
```

**Base class (Domain/Shared):**
```php
abstract readonly class AbstractUuidValueObject implements ValueObjectInterface
{
    final protected function __construct(private readonly string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidUuidException("Invalid UUID: {$value}");
        }
    }

    public static function generate(): static
    {
        return new static(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }
}
```

### 2.2 Complex Value Object

**Kullanım:** Money, Email, gibi compound concept'ler.

```php
<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use Domain\Shared\Abstracts\AbstractValueObject;
use Domain\Shared\Exceptions\ValidationException;

final readonly class Money extends AbstractValueObject
{
    public function __construct(
        public readonly float $amount,
        public readonly Currency $currency,
    ) {
        $this->validate($amount);
    }

    public static function fromAmount(float $amount, string $currencyCode): self
    {
        return new self($amount, Currency::from($currencyCode));
    }

    public function value(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->value,
        ];
    }

    protected function validate(mixed $value): void
    {
        if (!is_float($value) && !is_int($value)) {
            throw new ValidationException('Amount must be numeric');
        }
        if ($value < 0) {
            throw new ValidationException('Amount cannot be negative');
        }
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new ValidationException('Cannot add different currencies');
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }
}
```

### 2.3 Enum Value Object

```php
<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

enum Currency: string
{
    case TRY = 'TRY';
    case USD = 'USD';
    case EUR = 'EUR';

    public function symbol(): string
    {
        return match($this) {
            self::TRY => '₺',
            self::USD => '$',
            self::EUR => '€',
        };
    }
}
```

**Rules:**
- ✅ Backed enum, `: string`
- ✅ Behavior method'ları varsa enum içine
- ❌ Magic string yerine her yerde enum kullan

---

## 3. ENTITIES

```php
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
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // Getters
    public function getId(): ProductId { return $this->id; }
    public function getSku(): string { return $this->sku; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getPrice(): Money { return $this->price; }
    public function getStock(): int { return $this->stock; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedBy(): UserId { return $this->createdBy; }

    // Behavior — invariants enforced here
    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        $this->name = $name;
        $this->markAsUpdated();
    }

    public function changePrice(Money $price): void
    {
        $this->price = $price;
        $this->markAsUpdated();
    }

    public function decreaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
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
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        $this->stock += $quantity;
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

    public static function fromArray(array $data): static
    {
        return new self(
            id: ProductId::fromString($data['id']),
            sku: $data['sku'],
            name: $data['name'],
            description: $data['description'] ?? null,
            price: new Money((float) $data['price'], Currency::from($data['currency'])),
            stock: (int) $data['stock'],
            isActive: (bool) ($data['isActive'] ?? true),
            createdBy: UserId::fromString($data['createdBy']),
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt']) ? self::parseTimestamp($data['updatedAt']) : null,
        );
    }
}
```

**Rules:**
- ✅ ID ve `createdBy` (immutable referans) `readonly`
- ✅ Mutable business properties için setter YOK; **behavior method'ları** var (`decreaseStock`, `rename`, `activate`)
- ✅ Behavior method'ları invariant'ları enforce eder (negatif stok yok, boş isim yok)
- ✅ State değişiklikleri `markAsUpdated()` çağırır
- ✅ `toArray()` — Mapper'ın okuyacağı format
- ✅ `fromArray()` — Mapper'ın yazacağı format

---

## 4. DOMAIN SERVICES

Birden fazla entity'yi koordine eden veya state'siz domain logic için kullanılır.

```php
<?php

declare(strict_types=1);

namespace Domain\Currency\Services;

use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\Exceptions\ExchangeRateNotFoundException;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;

final readonly class CurrencyConverter
{
    /**
     * @param array<string, ExchangeRate> $rates Keyed by "{base}-{target}"
     */
    public function convert(Money $amount, Currency $target, array $rates): Money
    {
        if ($amount->currency === $target) {
            return $amount;
        }

        $key = "{$amount->currency->value}-{$target->value}";

        if (!isset($rates[$key])) {
            throw ExchangeRateNotFoundException::forPair(
                $amount->currency->value,
                $target->value
            );
        }

        $convertedAmount = $amount->amount * $rates[$key]->getRate();

        return new Money(round($convertedAmount, 2), $target);
    }
}
```

**Rules:**
- ✅ `final readonly class`
- ✅ Pure functions — state YOK
- ✅ Domain layer, framework dependency YOK
- ✅ Sadece domain entity ve value object'ler kullanılır

---

## 5. DOMAIN EXCEPTIONS

### 5.1 Exception Hierarchy

```
DomainException (abstract, in Shared)
├── NotFoundException (abstract)
│   └── ProductNotFoundException (concrete, per module)
├── ValidationException (abstract)
├── BusinessRuleException (abstract)
│   └── InsufficientStockException (concrete)
└── InfrastructureException (abstract)
```

### 5.2 Concrete Exception

```php
<?php

declare(strict_types=1);

namespace Domain\Catalog\Exceptions;

use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\NotFoundException;

final class ProductNotFoundException extends NotFoundException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::NOT_FOUND;
    }

    public static function withId(string $id): self
    {
        return new self(
            message: __('catalog.product.not_found', ['id' => $id]),
            errorCode: 'PRODUCT_NOT_FOUND',
            context: ['id' => $id],
        );
    }

    public static function withSku(string $sku): self
    {
        return new self(
            message: __('catalog.product.not_found_sku', ['sku' => $sku]),
            errorCode: 'PRODUCT_NOT_FOUND',
            context: ['sku' => $sku],
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Domain\Catalog\Exceptions;

use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\BusinessRuleException;

final class InsufficientStockException extends BusinessRuleException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::CONFLICT;  // 409
    }

    public static function forProduct(ProductId $productId, int $available, int $requested): self
    {
        return new self(
            message: __('catalog.product.insufficient_stock', [
                'available' => $available,
                'requested' => $requested,
            ]),
            errorCode: 'INSUFFICIENT_STOCK',
            context: [
                'productId' => $productId->value(),
                'available' => $available,
                'requested' => $requested,
            ],
        );
    }
}
```

**Rules:**
- ✅ `final class`
- ✅ Static factory method'lar (`withId`, `forProduct`) — `new` kullanma
- ✅ `getHttpStatusCode()` HER concrete exception'da
- ✅ `errorCode` machine-readable (UPPER_SNAKE)
- ✅ `context` array'de debug verisi
- ✅ i18n key'i: `{module}.{entity}.{reason}`

---

## 6. COMMANDS

```php
<?php

declare(strict_types=1);

namespace Application\Catalog\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class CreateProductCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        // Command-specific
        public string $sku,
        public string $name,
        public ?string $description,
        public float $price,
        public string $currency,
        public int $stock,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'CreateProduct';
    }
}
```

**Rules:**
- ✅ `final readonly class`
- ✅ `extends AbstractCommand`
- ✅ Context (userId, correlationId, userIp) önce; command-specific alanlar sonra
- ✅ `parent::__construct()` çağrılır
- ✅ `getCommandName()` implement edilir

---

## 7. QUERIES

```php
<?php

declare(strict_types=1);

namespace Application\Catalog\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class ListProductsQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        // Query-specific
        public int $page = 1,
        public int $pageSize = 20,
        public ?string $search = null,
        public ?bool $activeOnly = true,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
```

**Rules:**
- ✅ `final readonly class`
- ✅ `extends AbstractQuery`
- ✅ Pagination default değerleri ile gelir
- ✅ Filtre alanları nullable

---

## 8. COMMAND HANDLERS

```php
<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Commands;

use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\DTOs\ProductDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Entities\Product;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class CreateProductHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return CreateProductCommand::class;
    }

    /**
     * @throws \Domain\Catalog\Exceptions\DuplicateSkuException
     */
    protected function doHandle(CommandInterface $command): ProductDTO
    {
        assert($command instanceof CreateProductCommand);

        // 1. Convert primitives to Value Objects
        $userId = UserId::fromString($command->getUserId());
        $productId = ProductId::generate();
        $price = new Money($command->price, Currency::from($command->currency));

        // 2. Business rule check
        if ($this->productRepository->existsBySku($command->sku)) {
            throw \Domain\Catalog\Exceptions\DuplicateSkuException::withSku($command->sku);
        }

        // 3. Construct entity
        $product = new Product(
            id: $productId,
            sku: $command->sku,
            name: $command->name,
            description: $command->description,
            price: $price,
            stock: $command->stock,
            isActive: true,
            createdBy: $userId,
        );

        // 4. Persist
        $this->productRepository->save($product);

        $this->logger->info('Product created', [
            'productId' => $productId->value(),
            'sku' => $command->sku,
            'createdBy' => $userId->value(),
            'correlationId' => $command->getCorrelationId(),
        ]);

        // 5. Return DTO
        return ProductDTO::fromEntity($product);
    }
}
```

**Rules:**
- ✅ `final class` (NOT readonly — state'siz ama base class'ta property var)
- ✅ Constructor sırası: domain dependencies → `TransactionInterface` → `LoggerInterface`
- ✅ `parent::__construct($transaction, $logger)` zorunlu
- ✅ `getHandledCommandClass()` `public static`
- ✅ `doHandle()` `protected`, `CommandInterface` parametresi
- ✅ `assert($command instanceof ...)` — type narrowing
- ✅ İlk adım: primitive → Value Object dönüşümü
- ✅ Logger her önemli adımda
- ✅ Return: DTO (asla Entity)
- ✅ `@throws` PHPDoc

---

## 9. QUERY HANDLERS

```php
<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Queries;

use Application\Catalog\DTOs\ProductDTO;
use Application\Catalog\Queries\GetProductQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Psr\Log\LoggerInterface;

final class GetProductHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetProductQuery::class;
    }

    /**
     * @throws ProductNotFoundException
     */
    protected function doHandle(QueryInterface $query): ProductDTO
    {
        assert($query instanceof GetProductQuery);

        $productId = ProductId::fromString($query->productId);

        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            throw ProductNotFoundException::withId($query->productId);
        }

        return ProductDTO::fromEntity($product);
    }
}
```

**Rules:**
- ✅ Constructor: dependencies → LoggerInterface (Transaction YOK — read operation)
- ✅ `parent::__construct($logger)`
- ✅ Side effect olmamalı — sadece okuma
- ✅ Cache kullanılacaksa burada (Repository content değil)

---

## 10. DTOS

```php
<?php

declare(strict_types=1);

namespace Application\Catalog\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Catalog\Entities\Product;

final readonly class ProductDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $sku,
        public string $name,
        public ?string $description,
        public float $price,
        public string $currency,
        public int $stock,
        public bool $isActive,
        public string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId()->value(),
            sku: $product->getSku(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice()->amount,
            currency: $product->getPrice()->currency->value,
            stock: $product->getStock(),
            isActive: $product->isActive(),
            createdAt: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $product->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, Product> $products
     * @return array<int, self>
     */
    public static function collection(array $products): array
    {
        return array_map(fn(Product $p) => self::fromEntity($p), $products);
    }
}
```

**Rules:**
- ✅ `final readonly class`
- ✅ `extends AbstractOutputDTO` (toArray, jsonSerialize from base)
- ✅ `fromEntity()` static factory
- ✅ Tüm property'ler `public readonly`
- ✅ Date/time → `DateTimeInterface::ATOM` (ISO 8601)
- ✅ Money → ayrı `price` ve `currency` field'ları (frontend için kolay)

---

## 11. REPOSITORY INTERFACES

```php
<?php

declare(strict_types=1);

namespace Domain\Catalog\Contracts;

use Domain\Catalog\Entities\Product;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Contracts\RepositoryInterface;

/**
 * @extends RepositoryInterface<Product, ProductId>
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findById(ProductId $id): ?Product;

    public function findBySku(string $sku): ?Product;

    public function existsBySku(string $sku): bool;

    /**
     * @return array<int, Product>
     */
    public function paginate(int $page, int $pageSize, ?string $search = null, bool $activeOnly = true): array;

    public function countAll(?string $search = null, bool $activeOnly = true): int;

    public function save(Product $product): void;

    public function softDelete(ProductId $id): void;
}
```

**Rules:**
- ✅ Domain layer'da, **interface only**
- ✅ Tüm parametreler Value Object (string değil)
- ✅ Tek result için `?Entity`, çoğul için `array`
- ✅ Generic-style PHPDoc: `@extends RepositoryInterface<Entity, EntityId>`
- ✅ Save method `void` (Entity zaten reference)

---

## 12. ELOQUENT MODELS

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProductModel extends Model
{
    use SoftDeletes;

    protected $table = 'catalog.products';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = 'created_at';
}
```

**Rules:**
- ✅ Sadece `Infrastructure/{Module}/Models/`'da
- ✅ `final class`
- ✅ `protected $table = 'schema.table'` — schema-qualified
- ✅ UUID PK için: `$keyType = 'string'`, `$incrementing = false`
- ✅ Snake_case kolon isimleri (DB schema ile aynı)
- ✅ `$casts` net tanımlı
- ❌ Relationship method'ları YASAK (cross-module model erişimi olmasın diye)
- ❌ Scope method'ları YASAK (sorgular Repository'de)
- ❌ Business logic YASAK

---

## 13. MAPPERS

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Catalog\Mappers;

use Domain\Catalog\Entities\Product;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Catalog\Models\ProductModel;

final class ProductMapper extends AbstractMapper
{
    public function toEntity(object $model): Product
    {
        assert($model instanceof ProductModel);

        return Product::fromArray([
            'id' => $model->id,
            'sku' => $model->sku,
            'name' => $model->name,
            'description' => $model->description,
            'price' => (float) $model->price,
            'currency' => $model->currency,
            'stock' => (int) $model->stock,
            'isActive' => (bool) $model->is_active,
            'createdBy' => $model->created_by,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function toPersistence(object $entity): array
    {
        assert($entity instanceof Product);

        return [
            'id' => $entity->getId()->value(),
            'sku' => $entity->getSku(),
            'name' => $entity->getName(),
            'description' => $entity->getDescription(),
            'price' => $entity->getPrice()->amount,
            'currency' => $entity->getPrice()->currency->value,
            'stock' => $entity->getStock(),
            'is_active' => $entity->isActive(),
            'created_by' => $entity->getCreatedBy()->value(),
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
```

**Rules:**
- ✅ `final class extends AbstractMapper`
- ✅ İki yön de implement: `toEntity`, `toPersistence`
- ✅ `assert()` ile type guard
- ✅ DB kolon isimleri (snake_case) ↔ Entity property (camelCase) dönüşümü burada

---

## 14. REPOSITORY IMPLEMENTATIONS

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Catalog\Repositories;

use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Entities\Product;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Infrastructure\Catalog\Mappers\ProductMapper;
use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<Product, ProductId, ProductModel>
 */
final class EloquentProductRepository extends AbstractEloquentRepository implements ProductRepositoryInterface
{
    public function __construct(ProductMapper $mapper)
    {
        parent::__construct($mapper);
    }

    protected function getModelClass(): string
    {
        return ProductModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return ProductId::fromString($id);
    }

    public function findById(ProductId $id): ?Product
    {
        $model = $this->query()
            ->where('id', $id->value())
            ->first();

        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function findBySku(string $sku): ?Product
    {
        $model = $this->query()
            ->where('sku', $sku)
            ->first();

        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function existsBySku(string $sku): bool
    {
        return $this->query()
            ->where('sku', $sku)
            ->exists();
    }

    public function paginate(int $page, int $pageSize, ?string $search = null, bool $activeOnly = true): array
    {
        $query = $this->query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search !== null && trim($search) !== '') {
            $query->where('name', 'ILIKE', '%' . $search . '%');
        }

        $models = $query
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        return $models->map(fn($m) => $this->mapper->toEntity($m))->all();
    }

    public function countAll(?string $search = null, bool $activeOnly = true): int
    {
        $query = $this->query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search !== null && trim($search) !== '') {
            $query->where('name', 'ILIKE', '%' . $search . '%');
        }

        return $query->count();
    }

    public function save(Product $product): void
    {
        $data = $this->mapper->toPersistence($product);

        ProductModel::query()->updateOrInsert(
            ['id' => $product->getId()->value()],
            $data,
        );
    }

    public function softDelete(ProductId $id): void
    {
        $this->query()->where('id', $id->value())->update([
            'deleted_at' => now(),
        ]);
    }
}
```

**Rules:**
- ✅ `final class extends AbstractEloquentRepository implements ...Interface`
- ✅ `getModelClass()`, `createId()` template method'ları
- ✅ `$this->query()` — base class'ta `softDelete` filter'ı dahil
- ✅ Always: `$this->mapper->toEntity()` ve `toPersistence()` kullan
- ✅ Pagination Eloquent'in built-in paginate'i değil, manuel — DTO mantığıyla uyumlu
- ❌ N+1 query — eager load gerekiyorsa açık şekilde

---

## 15. CONTROLLERS

```php
<?php

declare(strict_types=1);

namespace Presentation\Catalog\Controllers;

use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\Commands\DeleteProductCommand;
use Application\Catalog\Commands\UpdateProductCommand;
use Application\Catalog\Queries\GetProductQuery;
use Application\Catalog\Queries\ListProductsQuery;
use Domain\Shared\Constants\HttpStatus;
use Illuminate\Http\JsonResponse;
use Presentation\Catalog\Requests\CreateProductRequest;
use Presentation\Catalog\Requests\ListProductsRequest;
use Presentation\Catalog\Requests\UpdateProductRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class ProductController extends AbstractApiController
{
    public function index(ListProductsRequest $request): JsonResponse
    {
        $query = new ListProductsQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            page: (int) $request->input('page', 1),
            pageSize: (int) $request->input('pageSize', 20),
            search: $request->input('search'),
            activeOnly: (bool) $request->input('activeOnly', true),
        );

        $result = $this->queryBus->ask($query);

        return $this->success($result);
    }

    public function show(string $id): JsonResponse
    {
        $query = new GetProductQuery(
            userId: $this->getUserId(request()),
            correlationId: $this->getCorrelationId(request()),
            productId: $id,
        );

        $result = $this->queryBus->ask($query);

        return $this->success($result);
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $command = new CreateProductCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            sku: $request->validated('sku'),
            name: $request->validated('name'),
            description: $request->validated('description'),
            price: (float) $request->validated('price'),
            currency: $request->validated('currency', 'TRY'),
            stock: (int) $request->validated('stock', 0),
        );

        $result = $this->commandBus->dispatch($command);

        return $this->success(
            data: $result,
            message: __('catalog.product.created'),
            status: HttpStatus::CREATED,
        );
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $command = new UpdateProductCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            productId: $id,
            name: $request->validated('name'),
            description: $request->validated('description'),
            price: (float) $request->validated('price'),
            currency: $request->validated('currency'),
            stock: (int) $request->validated('stock'),
        );

        $result = $this->commandBus->dispatch($command);

        return $this->success(
            data: $result,
            message: __('catalog.product.updated'),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $command = new DeleteProductCommand(
            userId: $this->getUserId(request()),
            correlationId: $this->getCorrelationId(request()),
            userIp: request()->ip() ?? '0.0.0.0',
            productId: $id,
        );

        $this->commandBus->dispatch($command);

        return $this->noContent();
    }
}
```

**Rules:**
- ✅ `final class extends AbstractApiController`
- ✅ **WEIGHTLESS** — sadece Command/Query yarat ve dispatch et
- ✅ `$this->getUserId($request)`, `$this->getCorrelationId($request)` — trait'ten gelir
- ✅ `$this->commandBus`, `$this->queryBus` — base class'ta inject edilmiş
- ✅ Response helper'ları: `success()`, `noContent()`, `error()`
- ✅ HTTP status: `HttpStatus::*` constants

---

## 16. FORM REQUESTS

```php
<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // JwtAuthMiddleware handles auth
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9\-]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', 'string', Rule::in(['TRY', 'USD', 'EUR'])],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => __('catalog.validation.sku_format'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku', ''))),
            'name' => strip_tags(trim((string) $this->input('name', ''))),
            'description' => $this->input('description')
                ? strip_tags((string) $this->input('description'))
                : null,
        ]);
    }
}
```

**Rules:**
- ✅ `final class`
- ✅ `authorize()` → `true` (JwtAuthMiddleware yapıyor)
- ✅ `rules()` — Laravel built-in validators
- ✅ `prepareForValidation()` — sanitization (XSS, trim, normalize)
- ✅ Custom error message i18n key'inden

---

## 17. SERVICE PROVIDER REGISTRATION

### 17.1 BusServiceProvider

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Application\Shared\Bus\CommandBus;
use Application\Shared\Bus\QueryBus;
use Application\Shared\Contracts\CommandBusInterface;
use Application\Shared\Contracts\QueryBusInterface;
use Illuminate\Support\ServiceProvider;

final class BusServiceProvider extends ServiceProvider
{
    /**
     * Command class => Handler class map
     */
    protected array $commandHandlers = [
        // ─── Identity ───
        \Application\Identity\Commands\RegisterUserCommand::class
            => \Application\Identity\Handlers\Commands\RegisterUserHandler::class,
        \Application\Identity\Commands\LoginUserCommand::class
            => \Application\Identity\Handlers\Commands\LoginUserHandler::class,
        \Application\Identity\Commands\RefreshTokenCommand::class
            => \Application\Identity\Handlers\Commands\RefreshTokenHandler::class,
        \Application\Identity\Commands\LogoutUserCommand::class
            => \Application\Identity\Handlers\Commands\LogoutUserHandler::class,

        // ─── Catalog ───
        \Application\Catalog\Commands\CreateProductCommand::class
            => \Application\Catalog\Handlers\Commands\CreateProductHandler::class,
        \Application\Catalog\Commands\UpdateProductCommand::class
            => \Application\Catalog\Handlers\Commands\UpdateProductHandler::class,
        \Application\Catalog\Commands\DeleteProductCommand::class
            => \Application\Catalog\Handlers\Commands\DeleteProductHandler::class,
        \Application\Catalog\Commands\AdjustStockCommand::class
            => \Application\Catalog\Handlers\Commands\AdjustStockHandler::class,

        // ─── Sales ───
        \Application\Sales\Commands\AddItemToCartCommand::class
            => \Application\Sales\Handlers\Commands\AddItemToCartHandler::class,
        \Application\Sales\Commands\UpdateCartItemQuantityCommand::class
            => \Application\Sales\Handlers\Commands\UpdateCartItemQuantityHandler::class,
        \Application\Sales\Commands\RemoveCartItemCommand::class
            => \Application\Sales\Handlers\Commands\RemoveCartItemHandler::class,
        \Application\Sales\Commands\ClearCartCommand::class
            => \Application\Sales\Handlers\Commands\ClearCartHandler::class,
        \Application\Sales\Commands\PlaceOrderCommand::class
            => \Application\Sales\Handlers\Commands\PlaceOrderHandler::class,
        \Application\Sales\Commands\CancelOrderCommand::class
            => \Application\Sales\Handlers\Commands\CancelOrderHandler::class,

        // ─── Currency ───
        \Application\Currency\Commands\SyncCurrencyRatesCommand::class
            => \Application\Currency\Handlers\Commands\SyncCurrencyRatesHandler::class,
    ];

    protected array $queryHandlers = [
        // ─── Identity ───
        \Application\Identity\Queries\GetCurrentUserQuery::class
            => \Application\Identity\Handlers\Queries\GetCurrentUserHandler::class,

        // ─── Catalog ───
        \Application\Catalog\Queries\GetProductQuery::class
            => \Application\Catalog\Handlers\Queries\GetProductHandler::class,
        \Application\Catalog\Queries\ListProductsQuery::class
            => \Application\Catalog\Handlers\Queries\ListProductsHandler::class,

        // ─── Sales ───
        \Application\Sales\Queries\GetCartQuery::class
            => \Application\Sales\Handlers\Queries\GetCartHandler::class,
        \Application\Sales\Queries\GetOrderQuery::class
            => \Application\Sales\Handlers\Queries\GetOrderHandler::class,
        \Application\Sales\Queries\ListUserOrdersQuery::class
            => \Application\Sales\Handlers\Queries\ListUserOrdersHandler::class,

        // ─── Currency ───
        \Application\Currency\Queries\GetExchangeRateQuery::class
            => \Application\Currency\Handlers\Queries\GetExchangeRateHandler::class,
        \Application\Currency\Queries\ListSupportedCurrenciesQuery::class
            => \Application\Currency\Handlers\Queries\ListSupportedCurrenciesHandler::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CommandBusInterface::class, function ($app) {
            return new CommandBus($app, $this->commandHandlers);
        });

        $this->app->singleton(QueryBusInterface::class, function ($app) {
            return new QueryBus($app, $this->queryHandlers);
        });
    }
}
```

### 17.2 RepositoryServiceProvider

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface => Implementation map
     */
    protected array $bindings = [
        // ─── Identity ───
        \Domain\Identity\Contracts\UserRepositoryInterface::class
            => \Infrastructure\Identity\Repositories\EloquentUserRepository::class,
        \Domain\Identity\Contracts\RefreshTokenRepositoryInterface::class
            => \Infrastructure\Identity\Repositories\EloquentRefreshTokenRepository::class,

        // ─── Catalog ───
        \Domain\Catalog\Contracts\ProductRepositoryInterface::class
            => \Infrastructure\Catalog\Repositories\EloquentProductRepository::class,

        // ─── Sales ───
        \Domain\Sales\Contracts\CartRepositoryInterface::class
            => \Infrastructure\Sales\Repositories\EloquentCartRepository::class,
        \Domain\Sales\Contracts\OrderRepositoryInterface::class
            => \Infrastructure\Sales\Repositories\EloquentOrderRepository::class,

        // ─── Currency ───
        \Domain\Currency\Contracts\ExchangeRateRepositoryInterface::class
            => \Infrastructure\Currency\Repositories\EloquentExchangeRateRepository::class,
        \Domain\Currency\Contracts\ExchangeRateProviderInterface::class
            => \Infrastructure\Currency\Providers\FrankfurterApiProvider::class,

        // ─── Domain Service Bindings ───
        \Domain\Identity\Contracts\PasswordHasherInterface::class
            => \Infrastructure\Identity\Services\BcryptPasswordHasher::class,
        \Domain\Identity\Contracts\JwtIssuerInterface::class
            => \Infrastructure\Identity\Services\TymonJwtIssuer::class,

        // ─── Application/Domain Infrastructure ───
        \Application\Shared\Contracts\TransactionInterface::class
            => \Infrastructure\Shared\Persistence\EloquentTransaction::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
```

---

## 18. ROUTES

```
routes/
├── api.php                # Module route includes
└── api/
    ├── identity.php
    ├── catalog.php
    ├── sales.php
    └── currency.php
```

```php
// routes/api.php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['proxy.origin', 'correlation.id'])->group(function () {
    require __DIR__ . '/api/identity.php';
    require __DIR__ . '/api/catalog.php';
    require __DIR__ . '/api/sales.php';
    require __DIR__ . '/api/currency.php';
});
```

```php
// routes/api/catalog.php
<?php

use Illuminate\Support\Facades\Route;
use Presentation\Catalog\Controllers\ProductController;

Route::prefix('catalog')->group(function () {
    // Public read endpoints (still through proxy)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    // Auth required for mutations
    Route::middleware('jwt.auth')->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        });
    });
});
```

**Rules:**
- ✅ Modül başına ayrı dosya
- ✅ RESTful naming
- ✅ Middleware: `proxy.origin` (sadece Next.js'den gelir), `correlation.id`, `jwt.auth`, `role:*`
- ✅ Throttle global config'te (per-route override gerekmedikçe)

---

## 19. TESTING STANDARDS

### 19.1 Framework: Pest 3

`composer require pestphp/pest --dev` + `composer require pestphp/pest-plugin-laravel --dev`

### 19.2 Test Structure

```
tests/
├── Pest.php                       # Pest configuration
├── TestCase.php                   # Base test case
├── Unit/
│   ├── Domain/                    # Pure domain tests (no DB, no Laravel)
│   │   ├── Catalog/
│   │   │   ├── Entities/
│   │   │   │   └── ProductTest.php
│   │   │   └── ValueObjects/
│   │   │       └── ProductIdTest.php
│   │   └── Shared/
│   │       └── ValueObjects/
│   │           └── MoneyTest.php
│   └── Application/               # Handler tests with mocked repos
│       ├── Catalog/
│       │   ├── CreateProductHandlerTest.php
│       │   ├── UpdateProductHandlerTest.php
│       │   ├── DeleteProductHandlerTest.php
│       │   ├── GetProductHandlerTest.php
│       │   └── ListProductsHandlerTest.php
│       ├── Identity/
│       └── Sales/
└── Feature/                       # HTTP-level integration tests
    ├── Identity/
    │   ├── RegisterTest.php
    │   ├── LoginTest.php
    │   └── RefreshTokenTest.php
    ├── Catalog/
    │   ├── ListProductsTest.php
    │   ├── GetProductTest.php
    │   ├── CreateProductTest.php
    │   ├── UpdateProductTest.php
    │   └── DeleteProductTest.php
    └── Sales/
        ├── CartTest.php
        ├── PlaceOrderTest.php
        └── CancelOrderTest.php
```

### 19.3 Coverage Requirements

**Her modül için minimum coverage:**
- ✅ Domain entities: **% 100** behavior coverage (her invariant test edilmiş)
- ✅ Value Objects: **% 100**
- ✅ Application handlers: **% 100** — her command/query'nin handler'ı için happy path + error path
- ✅ Feature tests: **CRUD'ın her endpoint'i için** en az 1 happy + 1 error test
- ✅ Repository: integration test'ler feature test'lerin bir parçası

### 19.4 Domain Entity Test (Unit, no Laravel)

```php
<?php

declare(strict_types=1);

use Domain\Catalog\Entities\Product;
use Domain\Catalog\Exceptions\InsufficientStockException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;

function makeProduct(int $stock = 10): Product
{
    return new Product(
        id: ProductId::generate(),
        sku: 'TEST-001',
        name: 'Test Product',
        description: null,
        price: new Money(100.00, Currency::TRY),
        stock: $stock,
        isActive: true,
        createdBy: UserId::generate(),
    );
}

describe('Product entity', function () {
    it('decreases stock correctly', function () {
        $product = makeProduct(stock: 10);

        $product->decreaseStock(3);

        expect($product->getStock())->toBe(7);
    });

    it('throws when decreasing stock below zero', function () {
        $product = makeProduct(stock: 5);

        $product->decreaseStock(10);
    })->throws(InsufficientStockException::class);

    it('throws when decreasing by zero or negative', function () {
        $product = makeProduct();

        $product->decreaseStock(0);
    })->throws(\InvalidArgumentException::class);

    it('marks updatedAt when state changes', function () {
        $product = makeProduct();
        expect($product->getUpdatedAt())->toBeNull();

        $product->rename('New Name');

        expect($product->getUpdatedAt())->not->toBeNull();
    });

    it('rejects empty name', function () {
        $product = makeProduct();
        $product->rename('   ');
    })->throws(\InvalidArgumentException::class);
});
```

### 19.5 Handler Test (Unit, mocked repository)

```php
<?php

declare(strict_types=1);

use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\DTOs\ProductDTO;
use Application\Catalog\Handlers\Commands\CreateProductHandler;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\DuplicateSkuException;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->repo = Mockery::mock(ProductRepositoryInterface::class);
    $this->transaction = Mockery::mock(TransactionInterface::class);
    $this->transaction->shouldReceive('run')
        ->andReturnUsing(fn ($callback) => $callback());

    $this->handler = new CreateProductHandler(
        $this->repo,
        $this->transaction,
        new NullLogger(),
    );
});

it('creates product successfully', function () {
    $command = new CreateProductCommand(
        userId: '550e8400-e29b-41d4-a716-446655440000',
        correlationId: 'corr-123',
        userIp: '127.0.0.1',
        sku: 'TEST-001',
        name: 'Test Product',
        description: null,
        price: 100.00,
        currency: 'TRY',
        stock: 10,
    );

    $this->repo->shouldReceive('existsBySku')->with('TEST-001')->andReturnFalse();
    $this->repo->shouldReceive('save')->once();

    $result = $this->handler->handle($command);

    expect($result)->toBeInstanceOf(ProductDTO::class)
        ->and($result->sku)->toBe('TEST-001')
        ->and($result->name)->toBe('Test Product')
        ->and($result->price)->toBe(100.00)
        ->and($result->stock)->toBe(10);
});

it('rejects duplicate sku', function () {
    $command = new CreateProductCommand(
        userId: '550e8400-e29b-41d4-a716-446655440000',
        correlationId: 'corr-123',
        userIp: '127.0.0.1',
        sku: 'EXISTING',
        name: 'Test',
        description: null,
        price: 50.00,
        currency: 'TRY',
        stock: 5,
    );

    $this->repo->shouldReceive('existsBySku')->with('EXISTING')->andReturnTrue();
    $this->repo->shouldNotReceive('save');

    $this->handler->handle($command);
})->throws(DuplicateSkuException::class);
```

### 19.6 Feature Test (HTTP integration, real DB)

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AuthHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = AuthHelper::createAdmin();
    $this->customer = AuthHelper::createCustomer();
});

it('admin can create product', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/catalog/products', [
            'sku' => 'NEW-001',
            'name' => 'Brand New',
            'description' => 'Description here',
            'price' => 199.99,
            'currency' => 'TRY',
            'stock' => 50,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.sku', 'NEW-001')
        ->assertJsonPath('data.name', 'Brand New')
        ->assertJsonPath('data.stock', 50);

    $this->assertDatabaseHas('catalog.products', [
        'sku' => 'NEW-001',
    ]);
});

it('customer cannot create product', function () {
    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/catalog/products', [
            'sku' => 'CUST-001',
            'name' => 'Customer Try',
            'price' => 100,
            'stock' => 10,
        ]);

    $response->assertForbidden();
});

it('rejects invalid sku format', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/catalog/products', [
            'sku' => 'lowercase-not-allowed',  // SKU must be uppercase
            'name' => 'Test',
            'price' => 10,
            'stock' => 1,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

it('rejects duplicate sku', function () {
    \Infrastructure\Catalog\Models\ProductModel::query()->create([
        'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'sku' => 'DUP-001',
        'name' => 'Existing',
        'price' => 50,
        'currency' => 'TRY',
        'stock' => 5,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/catalog/products', [
            'sku' => 'DUP-001',
            'name' => 'Duplicate',
            'price' => 100,
            'stock' => 10,
        ]);

    $response->assertConflict()
        ->assertJsonPath('error.code', 'DUPLICATE_SKU');
});
```

### 19.7 Required Test Scenarios per CRUD Module

Her modül için **minimum** aşağıdaki feature test'ler yazılır:

**CREATE:**
- ✅ Authorized user successful create
- ✅ Unauthorized user rejected (401)
- ✅ Forbidden role rejected (403)
- ✅ Invalid input validation (422)
- ✅ Business rule violation (409)

**READ (list):**
- ✅ Returns paginated results
- ✅ Filters work (search, status)
- ✅ Empty list returns proper structure

**READ (single):**
- ✅ Existing item returned
- ✅ Non-existent returns 404
- ✅ Deleted item returns 404

**UPDATE:**
- ✅ Authorized successful update
- ✅ Unauthorized rejected
- ✅ Non-existent returns 404
- ✅ Validation errors

**DELETE:**
- ✅ Authorized successful delete (soft)
- ✅ Unauthorized rejected
- ✅ Non-existent returns 404
- ✅ Cascade rules tested (cart_item silinince diğer şeyler etkilenmez)

### 19.8 Test Helpers

```php
// tests/Helpers/AuthHelper.php
final class AuthHelper
{
    public static function createAdmin(): UserModel { /* ... */ }
    public static function createCustomer(): UserModel { /* ... */ }
    public static function loginAs(UserModel $user): string  // returns JWT
}
```

### 19.9 Pest Configuration

```php
// tests/Pest.php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class)->in('Feature', 'Unit/Application');

// Domain unit tests don't extend Laravel TestCase
// They run as pure PHP tests

expect()->extend('toBeUuid', function () {
    return $this->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
```

### 19.10 CI Test Command

```bash
# Run all tests with coverage
./vendor/bin/pest --coverage --min=80

# Only domain (fast)
./vendor/bin/pest --testsuite=Unit/Domain

# Only one module
./vendor/bin/pest tests/Unit/Application/Catalog tests/Feature/Catalog
```

---

## 20. LOGGING INTEGRATION

> **Detay:** `LOGGING_SERVICE.md` dokümanında tam mimari var. Burada sadece coding pattern.

### 20.1 Logger Injection

Her handler'da `LoggerInterface` (PSR-3) inject edilir. Concrete implementation `BusServiceProvider` benzeri bir yerde bind edilir.

### 20.2 Audit Logging (Critical Events)

```php
// In handler:
$this->auditLogger->log(new AuditEvent(
    actor: $userId->value(),
    action: 'product.created',
    resource: 'product:' . $productId->value(),
    correlationId: $command->getCorrelationId(),
    metadata: ['sku' => $command->sku, 'price' => $command->price],
));
```

### 20.3 Application Logging

```php
$this->logger->info('Order placed successfully', [
    'orderId' => $orderId->value(),
    'userId' => $userId->value(),
    'total' => $total,
    'correlationId' => $command->getCorrelationId(),
]);

$this->logger->warning('Stock running low', [
    'productId' => $productId->value(),
    'remainingStock' => $stock,
]);

$this->logger->error('Frankfurter API failed', [
    'error' => $e->getMessage(),
    'attempt' => $retryCount,
]);
```

### 20.4 Log Structure Convention

Her log entry'de zorunlu alanlar:
- ✅ `correlationId` — request boyunca trace
- ✅ Domain entity ID'leri (productId, orderId)
- ✅ İlgili user ID

---

## 21. FORBIDDEN PATTERNS

### 21.1 Domain Layer

❌ `use Illuminate\...`
❌ `Eloquent` model erişimi
❌ `request()`, `auth()`, `app()` helper'ları
❌ Static method'lar (factory'ler hariç)
❌ Direct DB access
❌ HTTP/Request kavramı

### 21.2 Application Layer

❌ Eloquent Model'i doğrudan kullanmak
❌ Repository pattern dışında DB erişimi
❌ Cross-module direct repository erişimi (Bus üzerinden olmalı)
❌ Entity'yi handler'dan dışarı çıkarmak (DTO zorunlu)
❌ Magic command/query — registration zorunlu

### 21.3 Infrastructure Layer

❌ Domain entity'yi mutate etmek (sadece okuma + persistence)
❌ Business logic
❌ Direct controller injection

### 21.4 Presentation Layer

❌ Controller'da iş mantığı
❌ Direct repository erişimi
❌ Direct entity erişimi
❌ Hardcoded HTTP status (`200`, `404`) — `HttpStatus::*` zorunlu
❌ Hardcoded mesajlar — `__()` zorunlu

### 21.5 General

❌ `dd()`, `dump()` production'a sızabilen debug çıktıları
❌ Magic numbers / strings — constants veya enums
❌ String IDs — Value Object zorunlu
❌ `new Exception()` — domain exception'ların factory method'ları

---

## ✅ NEW FEATURE CHECKLIST

Yeni bir use case eklerken her madde işaretlenmiş olmalı:

**Domain:**
- [ ] Value Object'ler (varsa) `Domain/{Module}/ValueObjects/`'de
- [ ] Entity behavior method'ları invariant'ları enforce ediyor
- [ ] Repository interface `Domain/{Module}/Contracts/`'da
- [ ] Domain exception'lar factory method'larıyla
- [ ] i18n key'leri Turkish + English

**Application:**
- [ ] Command/Query `final readonly`, base class extend ediyor
- [ ] Handler `final`, base class extend ediyor
- [ ] DTO `final readonly`, `fromEntity` ve `collection` static method'ları
- [ ] BusServiceProvider'a registered
- [ ] Logger calls eklenmiş (info + error)
- [ ] Audit log gerekli mi? Evetse eklendi

**Infrastructure:**
- [ ] Eloquent Model'in `$table` schema-qualified
- [ ] Mapper iki yön de implement
- [ ] Repository implementation `final`, base class extend ediyor
- [ ] RepositoryServiceProvider'a binding eklendi

**Presentation:**
- [ ] Controller WEIGHTLESS
- [ ] FormRequest validation + sanitization
- [ ] Routes registered, middleware doğru
- [ ] HttpStatus constants kullanılmış

**Tests:**
- [ ] Domain entity behavior testleri
- [ ] Handler unit testi (happy + error path)
- [ ] Feature test (HTTP) her CRUD method için
- [ ] Coverage % 80+ doğrulanmış

**Documentation:**
- [ ] OpenAPI/Swagger annotation
- [ ] CHANGELOG güncellemesi (opsiyonel)

---

**Last Updated:** 2026-05-04
**Version:** 1.0
**Architecture:** Onion + CQRS + DDD (Lite)
