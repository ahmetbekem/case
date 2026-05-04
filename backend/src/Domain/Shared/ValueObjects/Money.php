<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use Domain\Shared\Abstracts\AbstractValueObject;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\Exceptions\ValidationException;

final readonly class Money extends AbstractValueObject
{
    public function __construct(
        public float $amount,
        public Currency $currency,
    ) {
        if ($amount < 0) {
            throw ValidationException::withMessage(
                'Money amount cannot be negative',
                ['amount' => $amount, 'currency' => $currency->value],
            );
        }
    }

    public static function fromAmount(float $amount, string $currencyCode): self
    {
        return new self($amount, Currency::from($currencyCode));
    }

    /**
     * @return array{amount: float, currency: string}
     */
    public function value(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->value,
        ];
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return $other instanceof self
            && $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other, 'add');
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other, 'subtract');
        $diff = $this->amount - $other->amount;
        if ($diff < 0) {
            throw ValidationException::withMessage(
                'Money subtraction would yield a negative amount',
                ['minuend' => $this->amount, 'subtrahend' => $other->amount],
            );
        }
        return new self($diff, $this->currency);
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw ValidationException::withMessage(
                'Money multiplier cannot be negative',
                ['factor' => $factor],
            );
        }
        return new self($this->amount * $factor, $this->currency);
    }

    private function assertSameCurrency(self $other, string $operation): void
    {
        if ($this->currency !== $other->currency) {
            throw ValidationException::withMessage(
                "Cannot {$operation} amounts in different currencies",
                ['left' => $this->currency->value, 'right' => $other->currency->value],
            );
        }
    }
}
