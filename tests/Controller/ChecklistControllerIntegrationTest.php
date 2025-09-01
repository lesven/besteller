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

    public function testRouterGeneratesSubmitAndFormUrls(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        $submitUrl = $router->generate('checklist_submit', ['id' => 5]);
        $this->assertSame('/checklist/5/submit', $submitUrl);

        $formUrl = $router->generate('checklist_form', ['checklist_id' => 1, 'name' => 'A', 'id' => 'x', 'email' => 'a@b']);
        // form is a query-style route; generator should output /form and parameters are query params
        $this->assertStringStartsWith('/form', $formUrl);
        $this->assertStringContainsString('checklist_id=1', $formUrl);
        $this->assertStringContainsString('name=A', $formUrl);
    }
}
