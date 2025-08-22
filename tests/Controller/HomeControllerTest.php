<?php

namespace App\Tests\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class HomeControllerTest extends TestCase
{
    public function testIndexRendersHomeTemplate(): void
    {
        $controller = $this->getMockBuilder(HomeController::class)
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('home/index.html.twig')
            ->willReturn(new Response('home'));

        $response = $controller->index();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('home', $response->getContent());
    }
}
