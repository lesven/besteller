<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RoleAccessTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;
    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
    self::ensureKernelShutdown();
    // Ensure the test environment is active for functional tests (useful inside containers/CI)
    $_SERVER['APP_ENV'] = 'test';
    $_ENV['APP_ENV'] = 'test';
    putenv('APP_ENV=test');

    // createClient() benötigt framework.test=true; falls createClient() trotzdem eine
    // LogicException wirft, soll der Test fehlschlagen, damit die Ursache sichtbar wird.
    $this->client = static::createClient();

        $this->em = $this->client->getContainer()->get('doctrine')->getManager();

        // Test-DB: create a user with ROLE_EDITOR
        $user = new User();
        $user->setEmail('editor@example.test');

    // Passwort hier nicht hashen: loginUser() in Tests setzt das Security-Token direkt,
    // daher ist das Passwort in der DB für diesen Test irrelevant.
    $user->setPassword('not-used-for-test');
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function testEditorAccess(): void
    {
        $client = $this->client;

        // Direktes Login des Test-Users (bypasst Formular + CSRF) für Integrationstest
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'editor@example.test']);
    $this->assertNotNull($user, 'Test user must exist');

    // loginUser() ist verfügbar auf dem Test-Client und setzt das Security-Token
    $client->loginUser($user);

        // Access checklists page (should be allowed for editors)
    $client->request('GET', '/admin/checklists');
    $this->assertSame(200, $client->getResponse()->getStatusCode(), 'Editor should access checklists');

        // Access user management (should be forbidden)
        try {
            $client->request('GET', '/admin/users');
            $status = $client->getResponse()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 403], true), 'Editor should not access user management (expected redirect or 403)');
        } catch (AccessDeniedHttpException $e) {
            // explicit assertion for environments that throw an exception instead of returning a response
            $this->assertInstanceOf(AccessDeniedHttpException::class, $e);
        }
    }

    protected function tearDown(): void
    {
        if ($this->em) {
            // Remove test user
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'editor@example.test']);
            if ($user) {
                $this->em->remove($user);
                $this->em->flush();
            }
        }

        parent::tearDown();
    }
}
