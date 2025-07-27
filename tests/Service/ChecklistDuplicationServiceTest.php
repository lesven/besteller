<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use App\Service\ChecklistDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ChecklistDuplicationServiceTest extends TestCase
{
    public function testDuplicateCreatesDeepCopy(): void
    {
        $checklist = new Checklist();
        $checklist->setTitle('Original');
        $checklist->setTargetEmail('target@example.com');
        $checklist->setReplyEmail('reply@example.com');

        $group = new ChecklistGroup();
        $group->setTitle('Group');
        $group->setDescription('Desc');
        $group->setSortOrder(1);
        $group->setChecklist($checklist);

        $item = new GroupItem();
        $item->setLabel('Item');
        $item->setType(GroupItem::TYPE_TEXT);
        $item->setSortOrder(2);
        $item->setGroup($group);

        $group->addItem($item);
        $checklist->addGroup($group);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new ChecklistDuplicationService($em);
        $duplicate = $service->duplicate($checklist);

        $this->assertNotSame($checklist, $duplicate);
        $this->assertSame('Duplikat von Original', $duplicate->getTitle());
        $this->assertSame('target@example.com', $duplicate->getTargetEmail());
        $this->assertCount(1, $duplicate->getGroups());
        $newGroup = $duplicate->getGroups()->first();
        $this->assertNotSame($group, $newGroup);
        $this->assertSame('Group', $newGroup->getTitle());
        $this->assertCount(1, $newGroup->getItems());
        $newItem = $newGroup->getItems()->first();
        $this->assertSame('Item', $newItem->getLabel());
        $this->assertNotSame($item, $newItem);
    }
}
