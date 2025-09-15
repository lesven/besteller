<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\LinkSenderService;
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

        $service = new LinkSenderService($em, $emailService, $urlGenerator);

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

        $service = new LinkSenderService($em, $emailService, $urlGenerator);

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

        $service = new LinkSenderService($em, $emailService, $urlGenerator);

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

        $service = new LinkSenderService($em, $emailService, $urlGenerator);

        $this->expectException(\InvalidArgumentException::class);

        $service->sendChecklistLink(new Checklist(), 'Manager', 'm@example.com', 'invalid@id', 'Alice', 'Intro');
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

        $service = new LinkSenderService($em, $emailService, $urlGenerator);

        $checklist = (new Checklist())->setTitle('List');

        $service->sendChecklistLink($checklist, 'Manager', 'm@example.com', '123', null, 'Intro');
    }
}
