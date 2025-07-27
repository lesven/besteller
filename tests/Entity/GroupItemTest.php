<?php
namespace App\Tests\Entity;

use App\Entity\GroupItem;
use PHPUnit\Framework\TestCase;

class GroupItemTest extends TestCase
{
    public function testGetOptionsArrayEmptyByDefault(): void
    {
        $item = new GroupItem();
        $this->assertSame([], $item->getOptionsArray());
    }

    public function testSetAndGetOptionsArray(): void
    {
        $item = new GroupItem();
        $item->setOptionsArray(['A', 'B']);
        $this->assertSame(['A', 'B'], $item->getOptionsArray());
        $this->assertSame([
            ['label' => 'A', 'active' => false],
            ['label' => 'B', 'active' => false],
        ], $item->getOptionsWithActive());
    }

    public function testSetOptionsArrayWithActiveTrue(): void
    {
        $item = new GroupItem();
        $item->setOptionsArray(['A', 'B'], true);
        $this->assertSame([
            ['label' => 'A', 'active' => true],
            ['label' => 'B', 'active' => true],
        ], $item->getOptionsWithActive());
    }

    public function testGetOptionsLines(): void
    {
        $item = new GroupItem();
        $item->setOptionsWithActive([
            ['label' => 'Foo', 'active' => true],
            ['label' => 'Bar', 'active' => false],
        ]);
        $this->assertSame(['Foo (aktiv)', 'Bar'], $item->getOptionsLines());
    }

    public function testGetOptionsWithActiveHandlesInvalidJson(): void
    {
        $item = new GroupItem();
        $item->setOptions('{invalid');
        $this->assertSame([], $item->getOptionsWithActive());

        $item->setOptions('"foo"');
        $this->assertSame([], $item->getOptionsWithActive());
    }
}
