<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\LinkSenderService;
use App\Service\EmployeeIdValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LinkSenderServiceTest extends TestCase
{
    public function testSendChecklistLinkCallsEmailService(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->getMockBuilder(\App\Repository\SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $em->method('getRepository')->with(Submission::class)->willReturn($repo);

        $emailService = $this->createMock(\App\Service\EmailService::class);
        $emailService->expects($this->once())->method('sendLinkEmail')->with(
            $this->isInstanceOf(Checklist::class),
            'Manager',
            'm@example.com',
            '123',
            'Alice',
            'Intro',
            'http://example.com'
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://example.com');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('123')->willReturn(true);

        $service = new LinkSenderService($em, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = (new Checklist())->setTitle('List');

        $service->sendChecklistLink($checklist, 'Manager', 'm@example.com', '123', 'Alice', 'Intro');
    }

    public function testSendChecklistLinkThrowsOnDuplicate(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->getMockBuilder(\App\Repository\SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(new Submission());

        $em->method('getRepository')->with(Submission::class)->willReturn($repo);

        $emailService = $this->createMock(\App\Service\EmailService::class);
        $emailService->expects($this->never())->method('sendLinkEmail');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('123')->willReturn(true);

        $service = new LinkSenderService($em, $emailService, $urlGenerator, $employeeIdValidator);

        $this->expectException(\RuntimeException::class);

        $service->sendChecklistLink(new Checklist(), 'Manager', 'm@example.com', '123', 'Alice', 'Intro');
    }

    public function testSendChecklistLinkThrowsOnInvalidEmail(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->getMockBuilder(\App\Repository\SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $em->method('getRepository')->with(Submission::class)->willReturn($repo);

        $emailService = $this->createMock(\App\Service\EmailService::class);
        $emailService->expects($this->never())->method('sendLinkEmail');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        // isValid should not be called because email validation fails first

        $service = new LinkSenderService($em, $emailService, $urlGenerator, $employeeIdValidator);

        $this->expectException(\InvalidArgumentException::class);

        $service->sendChecklistLink(new Checklist(), 'Manager', 'not-an-email', '123', 'Alice', 'Intro');
    }

    public function testSendChecklistLinkThrowsOnInvalidEmployeeId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->getMockBuilder(\App\Repository\SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $em->method('getRepository')->with(Submission::class)->willReturn($repo);

        $emailService = $this->createMock(\App\Service\EmailService::class);
        $emailService->expects($this->never())->method('sendLinkEmail');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('bad-id')->willReturn(false);

        $service = new LinkSenderService($em, $emailService, $urlGenerator, $employeeIdValidator);

        $this->expectException(\InvalidArgumentException::class);

        $service->sendChecklistLink(new Checklist(), 'Manager', 'm@example.com', 'bad-id', 'Alice', 'Intro');
    }

    public function testSendChecklistLinkUsesRecipientNameWhenPersonNameNull(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->getMockBuilder(\App\Repository\SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $em->method('getRepository')->with(Submission::class)->willReturn($repo);

        $emailService = $this->createMock(\App\Service\EmailService::class);
        $emailService->expects($this->once())->method('sendLinkEmail')->with(
            $this->isInstanceOf(Checklist::class),
            'Manager',
            'm@example.com',
            '123',
            null,
            'Intro',
            'http://example.com'
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with(
            $this->equalTo('checklist_form'),
            $this->callback(function ($params) {
                return isset($params['name']) && $params['name'] === 'Manager'
                    && isset($params['mitarbeiter_id']) && $params['mitarbeiter_id'] === '123'
                    && isset($params['email']) && $params['email'] === 'm@example.com';
            }),
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('http://example.com');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('123')->willReturn(true);

        $service = new LinkSenderService($em, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = (new Checklist())->setTitle('List');

        $service->sendChecklistLink($checklist, 'Manager', 'm@example.com', '123', null, 'Intro');
    }
}
