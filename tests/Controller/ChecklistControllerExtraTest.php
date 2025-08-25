<?php

namespace App\Tests\Controller;

use App\Controller\ChecklistController;
use App\Entity\Checklist;
use App\Entity\Submission;
use App\Exception\ChecklistNotFoundException;
use App\Exception\InvalidParametersException;
use App\Exception\SubmissionAlreadyExistsException;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use App\Service\ValidationService;
use App\Service\TemplateResolverService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChecklistControllerExtraTest extends TestCase
{
    private function createBaseMocks(): array
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);
        $validationService = $this->createMock(ValidationService::class);
        $templateResolver = $this->createMock(TemplateResolverService::class);

        return [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver];
    }

    private const TEST_EMAIL = 'b@example.com';

    public function testShowRendersAlreadySubmittedWhenSubmissionExists(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $existingSubmission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn($existingSubmission);
        $submissionRepo->method('existsForChecklistAndEmployee')->willReturn(true);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

        $templateResolver->expects($this->once())
            ->method('renderAlreadySubmitted')
            ->with($checklist, $existingSubmission, 'Bob')
            ->willReturn(new Response('already'));

        $request = new Request();
        $request->query->set('name', 'Bob');
        $request->query->set('mitarbeiter_id', 'm123');
    $request->query->set('email', self::TEST_EMAIL);
        
    // Additional context for the next change

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testShowRendersShowWhenNoSubmission(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);
        $submissionRepo->method('existsForChecklistAndEmployee')->willReturn(false);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

        $templateResolver->expects($this->once())
            ->method('renderChecklistShow')
            ->with($checklist, 'Bob', 'm123', self::TEST_EMAIL)
            ->willReturn(new Response('show'));

        $request = new Request();
        $request->query->set('name', 'Bob');
        $request->query->set('mitarbeiter_id', 'm123');
    $request->query->set('email', self::TEST_EMAIL);
        
    // Additional context for the next change

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testFormPostSuccessSendsEmailAndFlushes(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);
        $submissionRepo->method('existsForChecklistAndEmployee')->willReturn(false);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $submissionService->method('collectSubmissionData')->willReturn(['k' => 'v']);
        $submissionFactory->method('createSubmission')->willReturn($submission);

        $emailService->expects($this->once())
            ->method('generateAndSendEmail')
            ->with($submission)
            ->willReturn('generated');

        $entityManager->expects($this->once())->method('flush');

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

        $templateResolver->method('renderSuccess')->willReturn(new Response('success'));

        $request = new Request([], [], [], [], [], [], null);
        $request->query->set('checklist_id', '1');
        $request->query->set('name', 'Bob');
        $request->query->set('mitarbeiter_id', 'm123');
    $request->query->set('email', self::TEST_EMAIL);
        
    // Additional context for the next change
        $request->setMethod('POST');

        $response = $controller->form($request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testSubmitThrowsWhenExistingSubmission(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $existingSubmission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn($existingSubmission);
        $submissionRepo->method('existsForChecklistAndEmployee')->willReturn(true);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

    $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

    /** @var ChecklistController|\PHPUnit\Framework\MockObject\MockObject $controller */

        $this->expectException(SubmissionAlreadyExistsException::class);

        $request = new Request();
        $request->request->set('name', 'Bob');
        $request->request->set('mitarbeiter_id', 'm123');
    $request->request->set('email', self::TEST_EMAIL);
        
    // Additional context for the next change
        $request->setMethod('POST');

        $response = $controller->submit(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testSubmitSavesAndSendsEmail(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);
        $submissionRepo->method('existsForChecklistAndEmployee')->willReturn(false);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $submissionService->method('collectSubmissionData')->willReturn(['k' => 'v']);
        $submissionFactory->method('createSubmission')->willReturn($submission);

        $emailService->expects($this->once())
            ->method('generateAndSendEmail')
            ->with($submission)
            ->willReturn('generated');

        $entityManager->expects($this->once())->method('persist')->with($submission);
        $entityManager->expects($this->once())->method('flush');

    $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

    /** @var ChecklistController|\PHPUnit\Framework\MockObject\MockObject $controller */

        $templateResolver->method('renderSuccess')->willReturn(new Response('success'));

        $request = new Request();
        $request->request->set('name', 'Bob');
        $request->request->set('mitarbeiter_id', 'm123');
    $request->request->set('email', self::TEST_EMAIL);
        
    // Additional context for the next change
        $request->setMethod('POST');

        $response = $controller->submit(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testFormThrowsWhenNoChecklistId(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

    $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

    /** @var ChecklistController|\PHPUnit\Framework\MockObject\MockObject $controller */

        $this->expectException(InvalidParametersException::class);

        $request = new Request();
        // no checklist_id in query

        $controller->form($request);
    }

    public function testShowThrowsWhenChecklistNotFound(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willThrowException(new ChecklistNotFoundException(999));

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

        $this->expectException(ChecklistNotFoundException::class);

        $request = new Request();
        $request->query->set('name', 'Bob');
        $request->query->set('mitarbeiter_id', 'm123');
    $request->query->set('email', self::TEST_EMAIL);

        $controller->show(999, $request);
    }

    public function testShowThrowsWhenMissingQueryParams(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

        $this->expectException(InvalidParametersException::class);

        $request = new Request();
        // missing name/mitarbeiter_id/email

        $controller->show(1, $request);
    }

    public function testSubmitThrowsWhenMissingRequestParams(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);
        $submissionRepo->method('existsForChecklistAndEmployee')->willReturn(false);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService, $templateResolver])
            ->onlyMethods(['render'])
            ->getMock();

        $this->expectException(InvalidParametersException::class);

        $request = new Request();
        $request->setMethod('POST');
        // no name/mitarbeiter_id/email in request->request

        $controller->submit(1, $request);
    }
}
