<?php

namespace App\Tests\Controller;

use App\Controller\ChecklistController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ChecklistControllerIntegrationTest extends KernelTestCase
{
    public function testControllerIsAutowired(): void
    {
        self::bootKernel();
        $controller = self::getContainer()->get(ChecklistController::class);
        $this->assertInstanceOf(ChecklistController::class, $controller);
    }

    public function testRouterGeneratesChecklistShowUrl(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        $url = $router->generate('checklist_show', ['id' => 99]);
        $this->assertSame('/checklist/99', $url);
    }
}
