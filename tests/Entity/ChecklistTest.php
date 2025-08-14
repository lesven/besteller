<?php
namespace App\Tests\Entity;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use PHPUnit\Framework\TestCase;

class ChecklistTest extends TestCase
{
    public function testChecklistGroupsCollectionExists(): void
    {
        $checklist = new Checklist();
        $this->assertNotNull($checklist->getGroups());
        $this->assertCount(0, $checklist->getGroups());
    }

    public function testAddGroup(): void
    {
        $checklist = new Checklist();
        $group = new ChecklistGroup();
        $group->setTitle('Test Group')
              ->setSortOrder(10);

        $checklist->addGroup($group);
        
        $this->assertCount(1, $checklist->getGroups());
        $this->assertSame($group, $checklist->getGroups()->first());
        $this->assertSame($checklist, $group->getChecklist());
    }

    public function testSortOrderPropertyExists(): void
    {
        $group = new ChecklistGroup();
        $this->assertSame(0, $group->getSortOrder()); // default value
        
        $group->setSortOrder(42);
        $this->assertSame(42, $group->getSortOrder());
    }
}