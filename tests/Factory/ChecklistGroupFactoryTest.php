<?php

namespace App\Tests\Factory;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Factory\ChecklistGroupFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests für ChecklistGroupFactory.
 */
class ChecklistGroupFactoryTest extends TestCase
{
    private ChecklistGroupFactory $factory;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->factory = new ChecklistGroupFactory($this->entityManager);
    }

    public function testCreateGroupWithPersistTrue(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        // EntityManager erwartet persist() Aufruf
        $this->entityManager->expects($this->once())->method('persist');
        
        $group = $this->factory->createGroup(
            $checklist,
            'Test Group',
            'Test Description',
            1,
            true
        );
        
        $this->assertInstanceOf(ChecklistGroup::class, $group);
        $this->assertSame('Test Group', $group->getTitle());
        $this->assertSame('Test Description', $group->getDescription());
        $this->assertSame(1, $group->getSortOrder());
        $this->assertSame($checklist, $group->getChecklist());
    }

    public function testCreateGroupWithPersistFalse(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        // EntityManager erwartet KEINEN persist() Aufruf
        $this->entityManager->expects($this->never())->method('persist');
        
        $group = $this->factory->createGroup(
            $checklist,
            'Test Group',
            'Test Description',
            1,
            false
        );
        
        $this->assertInstanceOf(ChecklistGroup::class, $group);
        $this->assertSame('Test Group', $group->getTitle());
    }

    public function testCreateGroupDefaultPersistIsTrue(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        // EntityManager erwartet persist() Aufruf (Standard-Verhalten)
        $this->entityManager->expects($this->once())->method('persist');
        
        $group = $this->factory->createGroup(
            $checklist,
            'Test Group',
            'Test Description',
            1
        );
        
        $this->assertInstanceOf(ChecklistGroup::class, $group);
    }
}