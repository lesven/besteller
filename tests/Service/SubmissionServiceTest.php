<?php
namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use App\Service\SubmissionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SubmissionServiceTest extends TestCase
{
    private function createItem(int $id, string $label, string $type): GroupItem
    {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType($type);
    $ref = new \ReflectionProperty(GroupItem::class, 'id');
        $ref->setAccessible(True);
        $ref->setValue($item, $id);
        return $item;
    }

    private function createChecklist(): Checklist
    {
        $checklist = new Checklist();
        $group = new ChecklistGroup();
        $group->setTitle('Group');
        $group->setChecklist($checklist);
        $item1 = $this->createItem(1, 'Text', GroupItem::TYPE_TEXT);
        $item1->setGroup($group);
        $group->addItem($item1);
        $item2 = $this->createItem(2, 'Options', GroupItem::TYPE_CHECKBOX);
        $item2->setGroup($group);
        $group->addItem($item2);
        $checklist->addGroup($group);
        return $checklist;
    }

    public function testCollectSubmissionData(): void
    {
        $service = new SubmissionService();
        $checklist = $this->createChecklist();
        $request = new Request([], [
            'item_1' => 'Value',
            'item_2' => ['A', 'B'],
        ]);
        $result = $service->collectSubmissionData($checklist, $request);
        $this->assertArrayHasKey('Group', $result);
        $groupData = $result['Group'];
        $this->assertSame('Value', $groupData['Text']['value']);
        $this->assertSame(['A', 'B'], $groupData['Options']['value']);
    }

    public function testFormatSubmissionForEmail(): void
    {
        $service = new SubmissionService();
        $data = [
            'Group' => [
                'Text' => ['type' => GroupItem::TYPE_TEXT, 'value' => 'Foo'],
                'Options' => ['type' => GroupItem::TYPE_CHECKBOX, 'value' => ['A', 'B']],
            ],
        ];
        $html = $service->formatSubmissionForEmail($data);
        $this->assertStringContainsString('<h3>Group</h3>', $html);
        $this->assertStringContainsString('<strong>Text:</strong> Foo', $html);
        $this->assertStringContainsString('<strong>Options:</strong><ul>', $html);
        $this->assertStringContainsString('<li>A</li>', $html);
        $this->assertStringContainsString('<li>B</li>', $html);
    }
}
