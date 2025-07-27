<?php
namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\SubmissionFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SubmissionFactoryTest extends TestCase
{
    public function testCreateSubmissionWithoutPersist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $factory = new SubmissionFactory($em);
        $checklist = new Checklist();

        $submission = $factory->createSubmission($checklist, 'Alice', '1', 'a@test', ['foo' => []]);

        $this->assertInstanceOf(Submission::class, $submission);
        $this->assertSame($checklist, $submission->getChecklist());
        $this->assertSame('Alice', $submission->getName());
        $this->assertSame('1', $submission->getMitarbeiterId());
        $this->assertSame('a@test', $submission->getEmail());
        $this->assertSame(['foo' => []], $submission->getData());
    }

    public function testCreateSubmissionWithPersist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Submission::class));
        $em->expects($this->once())->method('flush');

        $factory = new SubmissionFactory($em);
        $checklist = new Checklist();

        $factory->createSubmission($checklist, 'Bob', '2', 'b@test', [], true);
    }
}
