<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\UserController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends TestCase
{
    public function testIndexRendersUsers(): void
    {
        $users = [new User(), new User()];

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->getMockBuilder(\stdClass::class)->setMethods(['findAll'])->getMock();
        $repo->method('findAll')->willReturn($users);

        $em->method('getRepository')->willReturn($repo);

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/index.html.twig', $this->callback(function ($vars) use ($users) {
                return isset($vars['users']) && $vars['users'] === $users;
            }))
            ->willReturn(new Response('ok'));

        $response = $controller->index();

        $this->assertEquals('ok', $response->getContent());
    }

    public function testNewGetRendersForm(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/new.html.twig')
            ->willReturn(new Response('ok'));

        $request = Request::create('/admin/users/new', 'GET');

        $response = $controller->new($request);

        $this->assertEquals('ok', $response->getContent());
    }

    public function testNewPostShortPasswordShowsError(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['addFlash', 'render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('Passwort'));

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/new.html.twig')
            ->willReturn(new Response('ok'));

        $request = Request::create('/admin/users/new', 'POST', ['email' => 'a@b.c', 'password' => 'short']);

        $response = $controller->new($request);

        $this->assertEquals('ok', $response->getContent());
    }

    public function testNewPostDuplicateEmailShowsError(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->getMockBuilder(\stdClass::class)->setMethods(['findOneBy'])->getMock();
        $repo->method('findOneBy')->willReturn(new User());
        $em->method('getRepository')->willReturn($repo);

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['addFlash', 'render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('existiert bereits'));

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/new.html.twig')
            ->willReturn(new Response('ok'));

        $pw = str_repeat('x', 16);
        $request = Request::create('/admin/users/new', 'POST', ['email' => 'dup@example.org', 'password' => $pw]);

        $response = $controller->new($request);

        $this->assertEquals('ok', $response->getContent());
    }

    public function testNewPostSuccessPersistsAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->getMockBuilder(\stdClass::class)->setMethods(['findOneBy'])->getMock();
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);

        $em->expects($this->once())->method('persist');
    $repo = $this->getMockBuilder(\stdClass::class)->setMethods(['findOneBy'])->getMock();
    $repo->method('findOneBy')->willReturn(null);
    $em->method('getRepository')->willReturn($repo);

    $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->willReturn('hashed');

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $hasher])
            ->onlyMethods(['addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('erstellt'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_users')
            ->willReturn(new RedirectResponse('/admin/users'));

        $pw = str_repeat('y', 16);
        $request = Request::create('/admin/users/new', 'POST', ['email' => 'new@example.org', 'password' => $pw]);

        $response = $controller->new($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testEditGetRendersForm(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['render'])
            ->getMock();

        $user = $this->createMock(User::class);

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/edit.html.twig', ['user' => $user])
            ->willReturn(new Response('ok'));

        $request = Request::create('/admin/users/1/edit', 'GET');

        $response = $controller->edit($request, $user);

        $this->assertEquals('ok', $response->getContent());
    }

    public function testEditPostShortPasswordShowsError(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['addFlash', 'render'])
            ->getMock();

        $user = $this->createMock(User::class);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('Passwort'));

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/edit.html.twig', ['user' => $user])
            ->willReturn(new Response('ok'));

        $request = Request::create('/admin/users/1/edit', 'POST', ['email' => 'a@b.c', 'password' => 'short']);

        $response = $controller->edit($request, $user);

        $this->assertEquals('ok', $response->getContent());
    }

    public function testEditPostDuplicateEmailShowsError(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->getMockBuilder(\stdClass::class)->setMethods(['findOneBy'])->getMock();
        $existing = $this->createMock(User::class);
        $existing->method('getId')->willReturn(2);
        $repo->method('findOneBy')->willReturn($existing);
        $em->method('getRepository')->willReturn($repo);

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['addFlash', 'render'])
            ->getMock();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('existiert bereits'));

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/user/edit.html.twig', ['user' => $user])
            ->willReturn(new Response('ok'));

        $pw = '';
        $request = Request::create('/admin/users/1/edit', 'POST', ['email' => 'dup@example.org', 'password' => $pw]);

        $response = $controller->edit($request, $user);

        $this->assertEquals('ok', $response->getContent());
    }

    public function testEditPostSuccessFlushesAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
    $repo = $this->getMockBuilder(\stdClass::class)->setMethods(['findOneBy'])->getMock();
    $repo->method('findOneBy')->willReturn(null);
    $em->method('getRepository')->willReturn($repo);

    $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->willReturn('newhash');

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $hasher])
            ->onlyMethods(['addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('aktualisiert'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_users')
            ->willReturn(new RedirectResponse('/admin/users'));

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(5);

        $pw = str_repeat('z', 16);
        $request = Request::create('/admin/users/5/edit', 'POST', ['email' => 'u@example.org', 'password' => $pw]);

        $response = $controller->edit($request, $user);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteCallsCsrfDeletionAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([$em, $this->createMock(UserPasswordHasherInterface::class)])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('gelöscht'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_users')
            ->willReturn(new RedirectResponse('/admin/users'));

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(13);

        $request = Request::create('/admin/users/13/delete', 'POST', ['_token' => 'tok']);

        $response = $controller->delete($request, $user);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
