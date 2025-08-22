<?php

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DashboardControllerFunctionalTest extends KernelTestCase
{
    public function testAdminDashboardRouteIsRegistered(): void
    {
        $kernel = static::createKernel();
        $kernel->boot();

        $container = $kernel->getContainer();
        $router = $container->get('router');
        $route = $router->getRouteCollection()->get('admin_dashboard');

        $this->assertNotNull($route, 'Route "admin_dashboard" should be registered');
        $this->assertSame('/admin', $route->getPath());
    }
}
