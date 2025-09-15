<?php

namespace App\ValueObject;

use InvalidArgumentException;

class SortOrder
{
    private int $order;

    public function __construct(int $order)
    {
        if ($order < 0) {
            throw new InvalidArgumentException(
                sprintf('Sort order must be non-negative, got %d', $order)
            );
        }
        
        $this->order = $order;
    }

    public function getValue(): int
    {
        return $this->order;
    }

    public function __toString(): string
    {
        return (string) $this->order;
    }

    public function equals(SortOrder $other): bool
    {
        return $this->order === $other->order;
    }

    public function increment(): SortOrder
    {
        return new SortOrder($this->order + 1);
    }

    public function decrement(): SortOrder
    {
        return new SortOrder(max(0, $this->order - 1));
    }

    public function isGreaterThan(SortOrder $other): bool
    {
        return $this->order > $other->order;
    }

    public function isLessThan(SortOrder $other): bool
    {
        return $this->order < $other->order;
    }

    public static function first(): SortOrder
    {
        return new SortOrder(0);
    }

    public static function fromInt(int $order): SortOrder
    {
        return new SortOrder($order);
    }
}