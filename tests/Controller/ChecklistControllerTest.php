<?php

namespace App\Tests\Controller;

use App\Controller\ChecklistController;
use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChecklistControllerTest extends TestCase
{
    public function testLoggerIsCalledWhenEmailSendingFails(): void
    {
        // Arrange: Create mocks for all dependencies
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);
        $validationService = $this->createMock(ValidationService::class);

        // Mock submission
        $submission = $this->createMock(Submission::class);
        $submission->method('getId')->willReturn(123);

        // Configure emailService to throw an exception
        $emailService->expects($this->once())
            ->method('generateAndSendEmail')
            ->with($submission)
            ->willThrowException(new \Exception('SMTP connection failed'));

        // Assert that logger->error is called with the expected message
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('E-Mail-Versendung fehlgeschlagen für Submission 123: SMTP connection failed'));

        // Create controller with mocked dependencies. Only public methods that we need to
        // override are mocked (render, addFlash). Private methods are exercised via
        // repository behavior on the EntityManager mock.
        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $submissionService,
                $emailService,
                $submissionFactory,
                $logger,
                $validationService
            ])
            ->onlyMethods(['render', 'addFlash'])
            ->getMock();

        // Configure the controller's dependencies so private helper methods work:
        // - getChecklistOr404 uses EntityManager->getRepository(Checklist::class)->find()
        // - findExistingSubmission uses SubmissionRepository->findOneByChecklistAndMitarbeiterId()
        $checklist = $this->createMock(Checklist::class);
        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null); // No existing submission

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === \App\Entity\Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $submissionService->method('collectSubmissionData')->willReturn(['some' => 'data']);
        $submissionFactory->method('createSubmission')->willReturn($submission);
        $entityManager->expects($this->never())->method('flush'); // Should not flush when email fails

        $controller->method('render')->willReturn(new Response('success'));

        // Act: Create a POST request that will trigger the email sending
        $request = new Request();
        $request->query->set('checklist_id', '1');
        $request->query->set('name', 'TestUser');
        $request->query->set('mitarbeiter_id', '12345');
        $request->query->set('email', 'test@example.com');
        $request->setMethod('POST');

        // Act & Assert: Call the form method - this should trigger our expected logger call
        $response = $controller->form($request);

        // The assertions are in the mock expectations above
        $this->assertInstanceOf(Response::class, $response);
    }
}
