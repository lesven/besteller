<?php

namespace App\Tests\Factory;

use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use App\Factory\GroupItemFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests für GroupItemFactory.
 */
class GroupItemFactoryTest extends TestCase
{
    private GroupItemFactory $factory;
    private EntityManagerInterface $entityManager;
    private ChecklistGroup $group;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->group = $this->createMock(ChecklistGroup::class);
        $this->factory = new GroupItemFactory($this->entityManager);
    }

    public function testCreateCheckboxItemWithPersistTrue(): void
    {
        $options = ['Option 1', 'Option 2', 'Option 3'];
        
        // EntityManager erwartet persist() Aufruf
        $this->entityManager->expects($this->once())->method('persist');
        
        $item = $this->factory->createCheckboxItem(
            $this->group,
            'Test Checkbox',
            $options,
            1,
            true
        );
        
        $this->assertInstanceOf(GroupItem::class, $item);
        $this->assertSame('Test Checkbox', $item->getLabel());
        $this->assertSame(GroupItem::TYPE_CHECKBOX, $item->getType());
        $this->assertSame(json_encode($options), $item->getOptions());
        $this->assertSame(1, $item->getSortOrder());
        $this->assertSame($this->group, $item->getGroup());
    }

    public function testCreateRadioItemWithPersistFalse(): void
    {
        $options = ['Radio 1', 'Radio 2'];
        
        // EntityManager erwartet KEINEN persist() Aufruf
        $this->entityManager->expects($this->never())->method('persist');
        
        $item = $this->factory->createRadioItem(
            $this->group,
            'Test Radio',
            $options,
            2,
            false
        );
        
        $this->assertInstanceOf(GroupItem::class, $item);
        $this->assertSame('Test Radio', $item->getLabel());
        $this->assertSame(GroupItem::TYPE_RADIO, $item->getType());
        $this->assertSame(json_encode($options), $item->getOptions());
        $this->assertSame(2, $item->getSortOrder());
    }

    public function testCreateTextItemWithDefaultPersist(): void
    {
        // EntityManager erwartet persist() Aufruf (Standard-Verhalten)
        $this->entityManager->expects($this->once())->method('persist');
        
        $item = $this->factory->createTextItem(
            $this->group,
            'Test Text Field',
            3
        );
        
        $this->assertInstanceOf(GroupItem::class, $item);
        $this->assertSame('Test Text Field', $item->getLabel());
        $this->assertSame(GroupItem::TYPE_TEXT, $item->getType());
        $this->assertNull($item->getOptions());
        $this->assertSame(3, $item->getSortOrder());
    }

    public function testCreateCheckboxItemDefaultPersistIsTrue(): void
    {
        $options = ['Default Option'];
        
        // EntityManager erwartet persist() Aufruf (Standard-Verhalten)
        $this->entityManager->expects($this->once())->method('persist');
        
        $item = $this->factory->createCheckboxItem(
            $this->group,
            'Default Checkbox',
            $options,
            1
        );
        
        $this->assertInstanceOf(GroupItem::class, $item);
    }

    public function testCreateRadioItemDefaultPersistIsTrue(): void
    {
        $options = ['Default Radio'];
        
        // EntityManager erwartet persist() Aufruf (Standard-Verhalten)
        $this->entityManager->expects($this->once())->method('persist');
        
        $item = $this->factory->createRadioItem(
            $this->group,
            'Default Radio',
            $options,
            1
        );
        
        $this->assertInstanceOf(GroupItem::class, $item);
    }
}