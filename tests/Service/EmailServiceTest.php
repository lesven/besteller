<?php
namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class EmailServiceTest extends TestCase
{
    public function testGenerateAndSendEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))->method("send");
        $submissionService = $this->createMock(SubmissionService::class);
        $submissionService->method("formatSubmissionForEmail")->willReturn("<ul></ul>");
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method("find")->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method("getRepository")->willReturn($repo);
        $service = new EmailService($mailer, $submissionService, $em);
        $checklist = (new Checklist())->setTitle("List")->setTargetEmail("target@test")->setReplyEmail("reply@test");
        $submission = (new Submission())->setChecklist($checklist)->setName("Alice")->setMitarbeiterId("123")->setEmail("alice@test")->setData([]);
        $html = $service->generateAndSendEmail($submission);
        $this->assertStringContainsString("Alice", $html);
        $this->assertStringContainsString("reply@test", $html);
    }

    public function testGetDefaultTemplateForAdmin(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $submissionService = $this->createMock(SubmissionService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new EmailService($mailer, $submissionService, $em);
        $this->assertSame($service->getDefaultTemplate(), $service->getDefaultTemplateForAdmin());
    }

    public function testSendLinkEmailUsesMailer(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method("send");
        $submissionService = $this->createMock(SubmissionService::class);
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method("find")->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method("getRepository")->willReturn($repo);
        $service = new EmailService($mailer, $submissionService, $em);
        $checklist = (new Checklist())->setTitle("List");
        $service->sendLinkEmail($checklist, "Manager", "m@example.com", "123", "Alice", "Intro", "http://example.com");
    }
}
