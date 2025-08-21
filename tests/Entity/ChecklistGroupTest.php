<?php

namespace App\Tests\Entity;

use App\Entity\ChecklistGroup;
use App\Entity\Checklist;
use App\Entity\GroupItem;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;

class ChecklistGroupTest extends TestCase
{
    public function testDefaultValues()
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

    public function testSettersAndGetters()
    {
        $group = new ChecklistGroup();
        $group->setTitle('Testgruppe');
        $group->setDescription('Beschreibung');
        $group->setSortOrder(5);
        $checklist = new Checklist();
        $group->setChecklist($checklist);

        $this->assertSame('Testgruppe', $group->getTitle(), 'Titel sollte gesetzt werden');
        $this->assertSame('Beschreibung', $group->getDescription(), 'Beschreibung sollte gesetzt werden');
        $this->assertSame(5, $group->getSortOrder(), 'SortOrder sollte gesetzt werden');
        $this->assertSame($checklist, $group->getChecklist(), 'Checklist sollte gesetzt werden');
    }

    public function testAddAndRemoveItem()
    {
        $group = new ChecklistGroup();
        $item = new GroupItem();
        $item->setSortOrder(1);
        $group->addItem($item);
        $this->assertTrue($group->getItems()->contains($item), 'Item sollte hinzugefügt werden');

        $group->removeItem($item);
        $this->assertFalse($group->getItems()->contains($item), 'Item sollte entfernt werden');
        $this->assertNull($item->getGroup(), 'Die Gruppe des Items sollte nach Entfernen null sein');
    }
}
