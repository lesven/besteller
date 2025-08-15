<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Checklist;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Einfacher Test für rollenbasierte Weiterleitung nach Link-Versand.
 */
class ChecklistLinkRedirectSimpleTest extends WebTestCase
{
    /**
     * Test: Überprüft, dass die sendLink-Route für Benutzer mit ROLE_SENDER zugänglich ist.
     */
    public function testSendLinkRouteAccessible(): void
    {
        $client = static::createClient();
        
        // EntityManager holen
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        // Testbenutzer mit ROLE_SENDER erstellen
        $user = new User();
        $user->setEmail('sender-simple@test.com');
        $user->setRoles(['ROLE_SENDER']);
        $user->setPassword('$2y$13$hashedPassword'); // Gehashtes Passwort

        $entityManager->persist($user);

        // Test-Stückliste erstellen
        $checklist = new Checklist();
        $checklist->setTitle('Test Stückliste Simple');
        $checklist->setTargetEmail('target-simple@test.com');
        $checklist->setEmailTemplate('<html>Test Simple</html>');

        $entityManager->persist($checklist);
        $entityManager->flush();

        // Als Versender anmelden
        $client->loginUser($user);

        // Link-Versand-Formular aufrufen
        $client->request('GET', "/admin/checklists/{$checklist->getId()}/send-link");
        
        // Prüfen, dass die Seite erfolgreich geladen wird
        $this->assertResponseIsSuccessful();
        
        // Prüfen, dass das Formular vorhanden ist
        $this->assertSelectorExists('form');
        $this->assertSelectorTextContains('h1', 'Link versenden');
    }

    /**
     * Test: Überprüft, dass ein Benutzer ohne ROLE_SENDER keinen Zugriff hat.
     */
    public function testSendLinkRouteAccessDeniedWithoutRole(): void
    {
        $client = static::createClient();
        
        // EntityManager holen
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        // Testbenutzer ohne ROLE_SENDER erstellen
        $user = new User();
        $user->setEmail('norole@test.com');
        $user->setRoles(['ROLE_USER']); // Keine SENDER-Rolle
        $user->setPassword('$2y$13$hashedPassword');

        $entityManager->persist($user);

        // Test-Stückliste erstellen
        $checklist = new Checklist();
        $checklist->setTitle('Test Stückliste Access');
        $checklist->setTargetEmail('target-access@test.com');
        $checklist->setEmailTemplate('<html>Test Access</html>');

        $entityManager->persist($checklist);
        $entityManager->flush();

        // Als Benutzer ohne SENDER-Rolle anmelden
        $client->loginUser($user);

        // Zugriff auf Link-Versand versuchen
        $client->request('GET', "/admin/checklists/{$checklist->getId()}/send-link");
        
        // Prüfen, dass der Zugriff verweigert wird
        $this->assertResponseStatusCodeSame(403);
    }
}
