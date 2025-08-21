<?php

namespace App\Tests\Service;

use App\Service\CsrfDeletionHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CsrfDeletionHelperTest extends TestCase
{
    private function makeController(): object
    {
        // kleine Testklasse, die das Trait verwendet
        return new class {
            use CsrfDeletionHelper;

            public EntityManagerInterface $entityManager;
            private array $flashes = [];
            private bool $tokenValid = false;

            public function addFlash(string $type, string $message): void
            {
                $this->flashes[$type][] = $message;
            }

            public function getFlashes(): array
            {
                return $this->flashes;
            }

            public function isCsrfTokenValid(string $id, ?string $token): bool
            {
                return $this->tokenValid;
            }

            public function setTokenValid(bool $v): void
            {
                $this->tokenValid = $v;
            }
        };
    }

    public function testHandleCsrfDeletion_withEmptyEntityId_addsErrorFlashAndDoesNotRemove(): void
    {
        $controller = $this->makeController();

        $em = $this->createMock(EntityManagerInterface::class);
        // remove/flush müssen nicht aufgerufen werden
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $controller->entityManager = $em;

        $entity = new class {
            public function getId() { return null; }
        };

        $request = new Request([], ['_token' => 'any']);

        // Trait-Methode ist private; rufe sie via Reflection auf
        $ref = new \ReflectionObject($controller);
        $method = $ref->getMethod('handleCsrfDeletion');
        $method->setAccessible(true);
        $method->invoke($controller, $request, $entity, 'Erfolgreich gelöscht');

        $flashes = $controller->getFlashes();
        $this->assertArrayHasKey('error', $flashes);
        $this->assertStringContainsString('Ungültige Entität', $flashes['error'][0]);
    }

    public function testHandleCsrfDeletion_withInvalidToken_doesNothing(): void
    {
        $controller = $this->makeController();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $controller->entityManager = $em;

        $entity = new class {
            public function getId() { return 5; }
        };

        $request = new Request([], ['_token' => 'wrong']);
        $controller->setTokenValid(false);

        $ref = new \ReflectionObject($controller);
        $method = $ref->getMethod('handleCsrfDeletion');
        $method->setAccessible(true);
        $method->invoke($controller, $request, $entity, 'Erfolgreich gelöscht');

        $flashes = $controller->getFlashes();
        $this->assertEmpty($flashes, 'Erwartet: keine Flash-Meldungen wenn Token ungültig');
    }

    public function testHandleCsrfDeletion_withValidToken_callsPreRemovalAndRemovesEntityAndFlushes(): void
    {
        $controller = $this->makeController();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $controller->entityManager = $em;

        $entity = new class {
            public function getId() { return 42; }
        };

        $request = new Request([], ['_token' => 'token42']);
        $controller->setTokenValid(true);

        $called = false;
        $arg = null;
        $pre = function($e) use (&$called, &$arg) { $called = true; $arg = $e; };

        $ref = new \ReflectionObject($controller);
        $method = $ref->getMethod('handleCsrfDeletion');
        $method->setAccessible(true);
        $method->invoke($controller, $request, $entity, 'Erfolgreich gelöscht', $pre);

        $this->assertTrue($called, 'Erwartet: preRemoval Callback wurde aufgerufen');
        $this->assertSame($entity, $arg);

        $flashes = $controller->getFlashes();
        $this->assertArrayHasKey('success', $flashes);
        $this->assertSame('Erfolgreich gelöscht', $flashes['success'][0]);
    }
}
