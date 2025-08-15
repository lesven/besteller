<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RoleAccessTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;
    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
    self::ensureKernelShutdown();
        // createClient() benötigt framework.test=true; wenn dies nicht gesetzt ist,
        // wird eine LogicException geworfen — in diesem Fall überspringen wir den Test.
        try {
            $this->client = static::createClient();
        } catch (\LogicException $e) {
            $this->markTestSkipped('Functional tests require framework.test=true (message: ' . $e->getMessage() . ')');
            return;
        }

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
        $client->request('GET', '/admin/users');
        $status = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 403], true), 'Editor should not access user management (expected redirect or 403)');
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
