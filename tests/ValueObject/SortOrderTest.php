<?php

namespace App\Tests\ValueObject;

use App\ValueObject\SortOrder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SortOrderTest extends TestCase
{
    public function testValidSortOrders(): void
    {
        $validOrders = [0, 1, 5, 10, 100, 999];

        foreach ($validOrders as $order) {
            $sortOrder = new SortOrder($order);
            $this->assertSame($order, $sortOrder->getValue());
            $this->assertSame((string) $order, (string) $sortOrder);
        }
    }

    public function testNegativeSortOrderThrowsException(): void
    {
        $invalidOrders = [-1, -5, -10, -100];

        foreach ($invalidOrders as $order) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf('Sort order must be non-negative, got %d', $order));
            new SortOrder($order);
        }
    }

    public function testEquals(): void
    {
        $order1 = new SortOrder(5);
        $order2 = new SortOrder(5);
        $order3 = new SortOrder(10);

        $this->assertTrue($order1->equals($order2));
        $this->assertFalse($order1->equals($order3));
    }

    public function testIncrement(): void
    {
        $order = new SortOrder(5);
        $incremented = $order->increment();

        $this->assertNotSame($order, $incremented, 'Increment should return new instance');
        $this->assertSame(6, $incremented->getValue());
        $this->assertSame(5, $order->getValue(), 'Original should remain unchanged');
    }

    public function testDecrement(): void
    {
        $order = new SortOrder(5);
        $decremented = $order->decrement();

        $this->assertNotSame($order, $decremented, 'Decrement should return new instance');
        $this->assertSame(4, $decremented->getValue());
        $this->assertSame(5, $order->getValue(), 'Original should remain unchanged');
    }

    public function testDecrementAtZeroStaysAtZero(): void
    {
        $order = new SortOrder(0);
        $decremented = $order->decrement();

        $this->assertSame(0, $decremented->getValue(), 'Decrement at zero should stay at zero');
    }

    public function testDecrementAtOneGoesToZero(): void
    {
        $order = new SortOrder(1);
        $decremented = $order->decrement();

        $this->assertSame(0, $decremented->getValue());
    }

    public function testIsGreaterThan(): void
    {
        $order5 = new SortOrder(5);
        $order10 = new SortOrder(10);
        $order3 = new SortOrder(3);

        $this->assertTrue($order10->isGreaterThan($order5));
        $this->assertTrue($order5->isGreaterThan($order3));
        $this->assertFalse($order5->isGreaterThan($order10));
        $this->assertFalse($order5->isGreaterThan($order5)); // Equal values
    }

    public function testIsLessThan(): void
    {
        $order5 = new SortOrder(5);
        $order10 = new SortOrder(10);
        $order3 = new SortOrder(3);

        $this->assertTrue($order5->isLessThan($order10));
        $this->assertTrue($order3->isLessThan($order5));
        $this->assertFalse($order10->isLessThan($order5));
        $this->assertFalse($order5->isLessThan($order5)); // Equal values
    }

    public function testFirst(): void
    {
        $first = SortOrder::first();

        $this->assertSame(0, $first->getValue());
        $this->assertInstanceOf(SortOrder::class, $first);
    }

    public function testFromInt(): void
    {
        $order = SortOrder::fromInt(15);

        $this->assertSame(15, $order->getValue());
        $this->assertInstanceOf(SortOrder::class, $order);
    }

    public function testFromIntWithNegativeValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SortOrder::fromInt(-1);
    }

    public function testToString(): void
    {
        $order = new SortOrder(42);

        $this->assertSame('42', (string) $order);
    }

    public function testImmutability(): void
    {
        $original = new SortOrder(5);
        $incremented = $original->increment();
        $decremented = $original->decrement();

        // Original should remain unchanged
        $this->assertSame(5, $original->getValue());
        
        // New instances should have different values
        $this->assertSame(6, $incremented->getValue());
        $this->assertSame(4, $decremented->getValue());
        
        // All should be different instances
        $this->assertNotSame($original, $incremented);
        $this->assertNotSame($original, $decremented);
        $this->assertNotSame($incremented, $decremented);
    }
}