<?php

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ChecklistLinkControllerFunctionalTest extends KernelTestCase
{
    public function testAdminChecklistSendLinkRouteIsRegistered(): void
    {
        $kernel = static::createKernel();
        $kernel->boot();

        $router = $kernel->getContainer()->get('router');
        $route = $router->getRouteCollection()->get('admin_checklist_send_link');

        $this->assertNotNull($route, 'Route "admin_checklist_send_link" should be registered');
        $this->assertSame('/admin/checklists/{id}/send-link', $route->getPath());
    }
}
