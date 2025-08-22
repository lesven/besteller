<?php

namespace App\Tests\Controller;

use App\Controller\ChecklistController;
use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
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

        return [$entityManager, $submissionService, $emailService, $submissionFactory, $logger];
    }

    private const TEST_EMAIL = 'b@example.com';

    public function testShowRendersAlreadySubmittedWhenSubmissionExists(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $existingSubmission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn($existingSubmission);

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
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())->method('render')->with(
            'checklist/already_submitted.html.twig',
            $this->arrayHasKey('submission')
        )->willReturn(new Response('already'));

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
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);

        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

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
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())->method('render')->with(
            'checklist/show.html.twig',
            $this->arrayHasKey('checklist')
        )->willReturn(new Response('show'));

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
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

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
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->method('render')->willReturn(new Response('success'));

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

    public function testSubmitRedirectsWhenExistingSubmission(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $existingSubmission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn($existingSubmission);

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
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['redirectToRoute'])
            ->getMock();

    /** @var ChecklistController|\PHPUnit\Framework\MockObject\MockObject $controller */

        $controller->expects($this->once())->method('redirectToRoute')->with(
            'checklist_show',
            $this->arrayHasKey('mitarbeiter_id')
        )->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/checklist/1'));

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
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);

        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

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
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

    /** @var ChecklistController|\PHPUnit\Framework\MockObject\MockObject $controller */

        $controller->method('render')->willReturn(new Response('success'));

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
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

    $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

    /** @var ChecklistController|\PHPUnit\Framework\MockObject\MockObject $controller */

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request();
        // no checklist_id in query

        $controller->form($request);
    }

    public function testShowThrowsWhenChecklistNotFound(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn(null);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request();
        $request->query->set('name', 'Bob');
        $request->query->set('mitarbeiter_id', 'm123');
    $request->query->set('email', self::TEST_EMAIL);

        $controller->show(999, $request);
    }

    public function testShowThrowsWhenMissingQueryParams(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            return null;
        });

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request();
        // missing name/mitarbeiter_id/email

        $controller->show(1, $request);
    }

    public function testSubmitThrowsWhenMissingRequestParams(): void
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger] = $this->createBaseMocks();

        $checklist = $this->createMock(Checklist::class);
        $checklistRepo = $this->createMock(ObjectRepository::class);
        $checklistRepo->method('find')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

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
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger])
            ->onlyMethods(['render'])
            ->getMock();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request();
        $request->setMethod('POST');
        // no name/mitarbeiter_id/email in request->request

        $controller->submit(1, $request);
    }
}
