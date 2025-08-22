<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\DashboardController;
use App\Entity\Checklist;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class DashboardControllerTest extends TestCase
{
    public function testIndexRendersDashboardWithChecklists(): void
    {
        $checklists = [new Checklist(), new Checklist()];

        $repository = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['findAll'])
            ->getMock();
        $repository->expects($this->once())->method('findAll')->willReturn($checklists);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('getRepository')->with(Checklist::class)->willReturn($repository);

        $controller = $this->getMockBuilder(DashboardController::class)
            ->setConstructorArgs([$entityManager])
            ->onlyMethods(['render'])
            ->getMock();

        $expectedResponse = new Response('dashboard');

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/dashboard.html.twig', $this->callback(function ($params) use ($checklists) {
                return isset($params['checklists']) && $params['checklists'] === $checklists;
            }))
            ->willReturn($expectedResponse);

        $result = $controller->index();

        $this->assertSame($expectedResponse, $result);
    }
}
