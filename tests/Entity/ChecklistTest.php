<?php

namespace App\Tests\Entity;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\Submission;
use PHPUnit\Framework\TestCase;

class ChecklistTest extends TestCase
{
    public function testAddAndRemoveGroupAndSubmission(): void
    {
        $checklist = new Checklist();
        $checklist->setTitle('T1')->setTargetEmail('t@example.com')->setReplyEmail('r@example.com');

        $group = new ChecklistGroup();
        $group->setTitle('G1');

        $this->assertCount(0, $checklist->getGroups());

        $checklist->addGroup($group);
        $this->assertCount(1, $checklist->getGroups());
        $this->assertSame($checklist, $group->getChecklist());

        $checklist->removeGroup($group);
        $this->assertCount(0, $checklist->getGroups());
        $this->assertNull($group->getChecklist());

        $submission = new Submission();
        $submission->setName('Max')->setMitarbeiterId('M123')->setEmail('max@example.com');
        $this->assertCount(0, $checklist->getSubmissions());

        $checklist->addSubmission($submission);
        $this->assertCount(1, $checklist->getSubmissions());
        $this->assertSame($checklist, $submission->getChecklist());

        $checklist->removeSubmission($submission);
        $this->assertCount(0, $checklist->getSubmissions());
        $this->assertNull($submission->getChecklist());
    }
}
