<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\ChecklistLinkController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ChecklistLinkControllerIntegrationTest extends KernelTestCase
{
    public function testControllerIsAutowired(): void
    {
        self::bootKernel();
        $controller = self::getContainer()->get(ChecklistLinkController::class);
        $this->assertInstanceOf(ChecklistLinkController::class, $controller);
    }

    public function testRouterGeneratesSendLinkUrl(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        $url = $router->generate('admin_checklist_send_link', ['id' => 42]);
        $this->assertSame('/admin/checklists/42/send-link', $url);
    }
}
