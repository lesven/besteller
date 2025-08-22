<?php

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class ChecklistControllerFunctionalTest extends KernelTestCase
{
    public function testAdminChecklistIndexResponds(): void
    {
    $kernel = static::createKernel();
    $kernel->boot();

    $container = $kernel->getContainer();

    // Simple integration assertion: route is registered and has expected path
    $router = $container->get('router');
    $route = $router->getRouteCollection()->get('admin_checklists');

    $this->assertNotNull($route, 'Route "admin_checklists" should be registered');
    $this->assertSame('/admin/checklists', $route->getPath());
    }
}
