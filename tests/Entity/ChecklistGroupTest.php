<?php

namespace App\Tests\Entity;

use App\Entity\ChecklistGroup;
use App\Entity\Checklist;
use App\Entity\GroupItem;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;

class ChecklistGroupTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $group = new ChecklistGroup();
        
        $this->assertNull($group->getId(), 'ID sollte initial null sein');
        $this->assertNull($group->getTitle(), 'Titel sollte initial null sein');
        $this->assertNull($group->getDescription(), 'Beschreibung sollte initial null sein');
        $this->assertSame(0, $group->getSortOrder(), 'SortOrder sollte initial 0 sein');
        $this->assertNull($group->getChecklist(), 'Checklist sollte initial null sein');
        $this->assertInstanceOf(ArrayCollection::class, $group->getItems(), 'Items sollte eine ArrayCollection sein');
        $this->assertCount(0, $group->getItems(), 'Items sollte initial leer sein');
    }

    public function testSetTitle(): void
    {
        $group = new ChecklistGroup();
        $result = $group->setTitle('Testgruppe');
        
        $this->assertSame($group, $result, 'setTitle sollte das Objekt zurückgeben (fluent interface)');
        $this->assertSame('Testgruppe', $group->getTitle(), 'Titel sollte gesetzt werden');
    }

    public function testSetDescription(): void
    {
        $group = new ChecklistGroup();
        $result = $group->setDescription('Test Beschreibung');
        
        $this->assertSame($group, $result, 'setDescription sollte das Objekt zurückgeben');
        $this->assertSame('Test Beschreibung', $group->getDescription(), 'Beschreibung sollte gesetzt werden');
        
        // Test mit null
        $group->setDescription(null);
        $this->assertNull($group->getDescription(), 'Beschreibung sollte auf null gesetzt werden können');
    }

    public function testSetSortOrder(): void
    {
        $group = new ChecklistGroup();
        $result = $group->setSortOrder(42);
        
        $this->assertSame($group, $result, 'setSortOrder sollte das Objekt zurückgeben');
        $this->assertSame(42, $group->getSortOrder(), 'SortOrder sollte gesetzt werden');
    }

    public function testSetChecklist(): void
    {
        $group = new ChecklistGroup();
        $checklist = new Checklist();
        $result = $group->setChecklist($checklist);
        
        $this->assertSame($group, $result, 'setChecklist sollte das Objekt zurückgeben');
        $this->assertSame($checklist, $group->getChecklist(), 'Checklist sollte gesetzt werden');
        
        // Test mit null
        $group->setChecklist(null);
        $this->assertNull($group->getChecklist(), 'Checklist sollte auf null gesetzt werden können');
    }

    public function testAddItem(): void
    {
        $group = new ChecklistGroup();
        $item = new GroupItem();
        
        $result = $group->addItem($item);
        
        $this->assertSame($group, $result, 'addItem sollte das Objekt zurückgeben');
        $this->assertTrue($group->getItems()->contains($item), 'Item sollte zur Collection hinzugefügt werden');
        $this->assertSame($group, $item->getGroup(), 'Die Gruppe des Items sollte gesetzt werden');
    }

    public function testAddItemTwice(): void
    {
        $group = new ChecklistGroup();
        $item = new GroupItem();
        
        $group->addItem($item);
        $group->addItem($item); // Nochmal hinzufügen
        
        $this->assertCount(1, $group->getItems(), 'Item sollte nur einmal in der Collection sein');
    }

    public function testRemoveItem(): void
    {
        $group = new ChecklistGroup();
        $item = new GroupItem();
        
        // Item hinzufügen
        $group->addItem($item);
        $this->assertTrue($group->getItems()->contains($item), 'Item sollte in der Collection sein');
        
        // Item entfernen
        $result = $group->removeItem($item);
        
        $this->assertSame($group, $result, 'removeItem sollte das Objekt zurückgeben');
        $this->assertFalse($group->getItems()->contains($item), 'Item sollte aus der Collection entfernt werden');
        $this->assertNull($item->getGroup(), 'Die Gruppe des Items sollte auf null gesetzt werden');
    }

    public function testRemoveItemNotInCollection(): void
    {
        $group = new ChecklistGroup();
        $item = new GroupItem();
        
        // Item war nie in der Collection
        $result = $group->removeItem($item);
        
        $this->assertSame($group, $result, 'removeItem sollte das Objekt zurückgeben auch wenn Item nicht in Collection');
        $this->assertFalse($group->getItems()->contains($item), 'Item sollte nicht in der Collection sein');
    }
}
