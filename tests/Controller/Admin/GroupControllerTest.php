<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\GroupController;
use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class GroupControllerTest extends TestCase
{
    public function testCreateGetRendersForm(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/group/create.html.twig', $this->callback(function ($vars) {
                return isset($vars['checklist']) && isset($vars['group']);
            }))
            ->willReturn(new Response('ok'));

        $checklist = $this->createMock(Checklist::class);

        $request = Request::create('/admin/group/create', 'GET');

        $response = $controller->create($request, $checklist);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('ok', $response->getContent());
    }

    public function testCreatePostPersistsAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ChecklistGroup::class));

        $em->expects($this->once())
            ->method('flush');

        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('Gruppe'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_edit', ['id' => 42])
            ->willReturn(new RedirectResponse('/admin/checklist/42'));

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(42);

        $post = ['title' => 'T', 'description' => '', 'sort_order' => '1'];
        $request = Request::create('/admin/group/create', 'POST', $post);

        $response = $controller->create($request, $checklist);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testEditPostFlushesAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('flush');

        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('aktualisiert'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_edit', ['id' => 7])
            ->willReturn(new RedirectResponse('/admin/checklist/7'));

        $group = $this->createMock(ChecklistGroup::class);
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(7);
        $group->method('getChecklist')->willReturn($checklist);

        $request = Request::create('/admin/group/edit', 'POST', ['title' => 'X']);

        $response = $controller->edit($request, $group);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteCallsCsrfDeletionAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        // Erwartung: remove und flush werden aufgerufen
        $em->expects($this->once())
            ->method('remove')
            ->with($this->isInstanceOf(ChecklistGroup::class));
        $em->expects($this->once())
            ->method('flush');

        // Controller mock: isCsrfTokenValid wird true zurückgeben, addFlash und redirectToRoute werden überwacht
        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('gelöscht'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_edit', ['id' => 9])
            ->willReturn(new RedirectResponse('/admin/checklist/9'));

        $group = $this->createMock(ChecklistGroup::class);
        $group->method('getId')->willReturn(9);
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(9);
        $group->method('getChecklist')->willReturn($checklist);

        // request enthält _token wie vom Trait erwartet
        $request = Request::create('/admin/group/delete', 'POST', ['_token' => 'tok']);

        $response = $controller->delete($request, $group);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testAddItemPostPersistsAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(GroupItem::class));

        $em->expects($this->once())
            ->method('flush');

        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('Element'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_edit', ['id' => 11])
            ->willReturn(new RedirectResponse('/admin/checklist/11'));

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(11);

        $group = $this->createMock(ChecklistGroup::class);
        $group->method('getChecklist')->willReturn($checklist);

        $post = ['label' => 'L', 'type' => GroupItem::TYPE_TEXT, 'sort_order' => '1'];
        $request = Request::create('/admin/group/add-item', 'POST', $post);

        $response = $controller->addItem($request, $group);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testEditItemPostFlushesAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('flush');

        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('aktualisiert'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_edit', ['id' => 21])
            ->willReturn(new RedirectResponse('/admin/checklist/21'));

    $item = $this->createMock(GroupItem::class);
    $item->method('getId')->willReturn(33);
        $group = $this->createMock(ChecklistGroup::class);
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(21);
        $group->method('getChecklist')->willReturn($checklist);
        $item->method('getGroup')->willReturn($group);

        $request = Request::create('/admin/group/edit-item', 'POST', ['label' => 'X']);

        $response = $controller->editItem($request, $item);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteItemCallsCsrfDeletionAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('remove')
            ->with($this->isInstanceOf(GroupItem::class));
        $em->expects($this->once())
            ->method('flush');

        $controller = $this->getMockBuilder(GroupController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('gelöscht'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_edit', ['id' => 33])
            ->willReturn(new RedirectResponse('/admin/checklist/33'));

    $item = $this->createMock(GroupItem::class);
    $item->method('getId')->willReturn(33);
        $group = $this->createMock(ChecklistGroup::class);
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(33);
        $group->method('getChecklist')->willReturn($checklist);
        $item->method('getGroup')->willReturn($group);

        $request = Request::create('/admin/group/delete-item', 'POST', ['_token' => 'tok']);

        $response = $controller->deleteItem($request, $item);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
