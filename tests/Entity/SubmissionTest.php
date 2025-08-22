<?php

namespace App\Tests\Entity;

use App\Entity\Submission;
use App\Entity\Checklist;
use PHPUnit\Framework\TestCase;

class SubmissionTest extends TestCase
{
    public function testDataAndChecklistLink(): void
    {
        $sub = new Submission();
        $sub->setName('Anna')->setMitarbeiterId('ID11')->setEmail('a@e');
        $this->assertNotNull($sub->getSubmittedAt());

        $data = ['foo' => ['bar' => 1]];
        $sub->setData($data);
        $this->assertSame($data, $sub->getData());

        $cl = new Checklist();
        $sub->setChecklist($cl);
        $this->assertSame($cl, $sub->getChecklist());
    }
}
