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

    public function testCollectIgnoresEmptyAndMissing(): void
    {
        $service = new SubmissionService();

        $checklist = new Checklist();
        $group = new ChecklistGroup();
        $group->setTitle('G');
        $group->setChecklist($checklist);

        $empty = $this->createItem(10, 'Empty', GroupItem::TYPE_TEXT);
        $empty->setGroup($group);
        $group->addItem($empty);

        $filled = $this->createItem(11, 'Filled', GroupItem::TYPE_TEXT);
        $filled->setGroup($group);
        $group->addItem($filled);

        $checklist->addGroup($group);

        $request = new Request([], [
            // item_10 is empty string -> should be ignored
            'item_10' => '',
            // item_11 present -> should be collected
            'item_11' => 'Present',
        ]);

        $result = $service->collectSubmissionData($checklist, $request);
        $this->assertArrayHasKey('G', $result);
        $groupData = $result['G'];
        $this->assertArrayNotHasKey('Empty', $groupData);
        $this->assertSame('Present', $groupData['Filled']['value']);
    }

    public function testCollectRadio(): void
    {
        $service = new SubmissionService();

        $checklist = new Checklist();
        $group = new ChecklistGroup();
        $group->setTitle('R');
        $group->setChecklist($checklist);

        $radio = $this->createItem(20, 'Choice', GroupItem::TYPE_RADIO);
        $radio->setGroup($group);
        $group->addItem($radio);

        $checklist->addGroup($group);

        $request = new Request([], [
            'item_20' => 'opt1',
        ]);

        $result = $service->collectSubmissionData($checklist, $request);
        $this->assertArrayHasKey('R', $result);
        $this->assertSame('opt1', $result['R']['Choice']['value']);
    }

    public function testFormatHandlesLegacyAndMultiline(): void
    {
        $service = new SubmissionService();

        $data = [
            'Group' => [
                'Text' => ['type' => GroupItem::TYPE_TEXT, 'value' => "Line1\nLine2"],
                // legacy structure: directly an array of values
                'Legacy' => ['X', 'Y'],
            ],
        ];

        $html = $service->formatSubmissionForEmail($data);

        $this->assertStringContainsString('<h3>Group</h3>', $html);
        // nl2br should convert newlines to <br />
        $this->assertStringContainsString('Line1<br', $html);
        $this->assertStringContainsString('<strong>Legacy:</strong><ul>', $html);
        $this->assertStringContainsString('<li>X</li>', $html);
        $this->assertStringContainsString('<li>Y</li>', $html);
    }

    public function testCollectIgnoresEmptyCheckboxAndMissing(): void
    {
        $service = new SubmissionService();

        $checklist = new Checklist();
        $group = new ChecklistGroup();
        $group->setTitle('C');
        $group->setChecklist($checklist);

        $cbEmpty = $this->createItem(30, 'CBEmpty', GroupItem::TYPE_CHECKBOX);
        $cbEmpty->setGroup($group);
        $group->addItem($cbEmpty);

        $cbPresent = $this->createItem(31, 'CBPresent', GroupItem::TYPE_CHECKBOX);
        $cbPresent->setGroup($group);
        $group->addItem($cbPresent);

        $missing = $this->createItem(32, 'Missing', GroupItem::TYPE_TEXT);
        $missing->setGroup($group);
        $group->addItem($missing);

        $checklist->addGroup($group);

        $request = new Request([], [
            // item_30 present but empty array -> should be ignored
            'item_30' => [],
            // item_31 present with values -> should be collected
            'item_31' => ['one'],
            // item_32 missing -> should be ignored
        ]);

        $result = $service->collectSubmissionData($checklist, $request);
        $this->assertArrayHasKey('C', $result);
        $groupData = $result['C'];
        $this->assertArrayNotHasKey('CBEmpty', $groupData);
        $this->assertArrayNotHasKey('Missing', $groupData);
        $this->assertSame(['one'], $groupData['CBPresent']['value']);
    }

    public function testFormatHandlesDirectScalarItem(): void
    {
        $service = new SubmissionService();

        $data = [
            'G' => [
                'Note' => 'Just a note',
            ],
        ];

        $html = $service->formatSubmissionForEmail($data);

        $this->assertStringContainsString('<h3>G</h3>', $html);
        $this->assertStringContainsString('<strong>Note:</strong> Just a note', $html);
    }
}
