<?php

namespace App\Tests\Controller;

use App\Controller\ChecklistController;
use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

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

        // Create controller with mocked dependencies
        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $submissionService,
                $emailService,
                $submissionFactory,
                $logger
            ])
            ->onlyMethods(['render', 'addFlash', 'getChecklistOr404', 'getRequestValuesFromQuery', 'findExistingSubmission'])
            ->getMock();

        // Configure the controller to simulate the flow in the form method
        $checklist = $this->createMock(Checklist::class);
        $controller->method('getChecklistOr404')->willReturn($checklist);
        $controller->method('getRequestValuesFromQuery')->willReturn(['TestUser', '12345', 'test@example.com']);
        $controller->method('findExistingSubmission')->willReturn(null); // No existing submission

        $submissionService->method('collectSubmissionData')->willReturn(['some' => 'data']);
        $submissionFactory->method('createSubmission')->willReturn($submission);
        $entityManager->expects($this->never())->method('flush'); // Should not flush when email fails

        $controller->method('render')->willReturn(new \Symfony\Component\HttpFoundation\Response('success'));

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
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
    }
}