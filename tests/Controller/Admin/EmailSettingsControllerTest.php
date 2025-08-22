<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\EmailSettingsController;
use App\Entity\EmailSettings;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit-Tests für den EmailSettingsController.
 */
class EmailSettingsControllerTest extends TestCase
{
    /**
     * GET: Wenn keine Einstellungen vorhanden sind, wird ein neues EmailSettings-Objekt persistiert und das Template gerendert.
     */
    public function testEditCreatesSettingsWhenNoneExists(): void
    {
        // Mock Repository, das null zurückgibt
        $repo = $this->createMock(ObjectRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        // Mock EntityManager: getRepository() -> $repo, expect persist called once
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(EmailSettings::class)
            ->willReturn($repo);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EmailSettings::class));

        // Controller-Partial-Mock, render und addFlash abfangen
        $controller = $this->getMockBuilder(EmailSettingsController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['render', 'addFlash'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                'admin/email_settings/edit.html.twig',
                $this->callback(function ($vars) {
                    // Sicherstellen, dass 'settings' vorhanden und korrekt ist
                    return isset($vars['settings']) && $vars['settings'] instanceof EmailSettings;
                })
            )
            ->willReturn(new Response('ok'));

        // Kein POST -> GET
        $request = Request::create('/admin/email-settings', 'GET');

        $response = $controller->edit($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('ok', $response->getContent());
    }

    /**
     * POST: Wenn Einstellungen existieren, werden Werte gesetzt, flush aufgerufen und ein Flash gesetzt.
     */
    public function testEditSavesOnPost(): void
    {
        // Vorhandene Einstellungen
        $settings = new EmailSettings();

        $repo = $this->createMock(ObjectRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($settings);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(EmailSettings::class)
            ->willReturn($repo);

        // Erwartung: flush wird aufgerufen
        $em->expects($this->once())
            ->method('flush');

        // Controller-Partial-Mock, render und addFlash abfangen
        $controller = $this->getMockBuilder(EmailSettingsController::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['render', 'addFlash'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('E-Mail Einstellungen'));

        $controller->expects($this->once())
            ->method('render')
            ->with(
                'admin/email_settings/edit.html.twig',
                $this->callback(function ($vars) use ($settings) {
                    // Das übergebene Settings-Objekt muss das gleiche sein
                    return isset($vars['settings']) && $vars['settings'] === $settings;
                })
            )
            ->willReturn(new Response('ok'));

        // POST-Daten wie im Controller verwendet
        $post = [
            'host' => 'smtp.example.org',
            'port' => '587',
            'username' => 'user',
            'password' => 'secret',
            'ignore_ssl' => '1',
            'sender_email' => 'noreply@example.org',
        ];

        $request = Request::create('/admin/email-settings', 'POST', $post);

        $response = $controller->edit($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('ok', $response->getContent());
    }
}
