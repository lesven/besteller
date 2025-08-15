<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Checklist;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test für rollenbasierte Weiterleitung nach Link-Versand.
 */
class ChecklistLinkRedirectTest extends WebTestCase
{
    /**
     * Test: Versender werden nach erfolgreichem Link-Versand zum Dashboard weitergeleitet.
     */
    public function testSenderRedirectsToDashboardAfterLinkSend(): void
    {
        $client = static::createClient();
        
        // EntityManager nach Client-Erstellung holen
        $entityManager = $client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Testbenutzer mit ROLE_SENDER erstellen
        $user = new User();
        $user->setEmail('sender@test.com');
        $user->setRoles(['ROLE_SENDER']);
        $user->setPassword('hashedPassword');

        $entityManager->persist($user);

        // Test-Stückliste erstellen
        $checklist = new Checklist();
        $checklist->setTitle('Test Stückliste');
        $checklist->setTargetEmail('target@test.com');
        $checklist->setEmailTemplate('<html>Test</html>');

        $entityManager->persist($checklist);
        $entityManager->flush();

        // Als Versender anmelden
        $client->loginUser($user);

        // Link-Versand-Formular aufrufen
        $crawler = $client->request('GET', "/admin/checklists/{$checklist->getId()}/send-link");
        $this->assertResponseIsSuccessful();

        // Formular ausfüllen und abschicken
        $form = $crawler->selectButton('Link versenden')->form();
        $form['recipient_name'] = 'Test Manager';
        $form['recipient_email'] = 'manager@test.com';
        $form['mitarbeiter_id'] = 'TEST-123';
        $form['person_name'] = 'Test Person';
        $form['intro'] = 'Test Einleitung';

        // POST-Request abschicken
        $client->submit($form);

        // Überprüfen, dass zur Dashboard-Route weitergeleitet wird
        $this->assertResponseRedirects('/admin');

        // Weiterleitung folgen
        $client->followRedirect();

        // Prüfen, dass wir auf dem Dashboard sind
        $this->assertRouteSame('admin_dashboard');

        // Prüfen, dass Flash-Nachricht angezeigt wird
        $this->assertSelectorTextContains('.alert-success', 'Link wurde erfolgreich versendet');
    }

    /**
     * Test: Administratoren werden nach Link-Versand zur Stücklisten-Übersicht weitergeleitet.
     */
    public function testAdminRedirectsToChecklistsAfterLinkSend(): void
    {
        $client = static::createClient();
        
        // EntityManager nach Client-Erstellung holen
        $entityManager = $client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Testbenutzer mit ROLE_ADMIN erstellen
        $user = new User();
        $user->setEmail('admin@test.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('hashedPassword');

        $entityManager->persist($user);

        // Test-Stückliste erstellen
        $checklist = new Checklist();
        $checklist->setTitle('Test Stückliste Admin');
        $checklist->setTargetEmail('target@test.com');
        $checklist->setEmailTemplate('<html>Test</html>');

        $entityManager->persist($checklist);
        $entityManager->flush();

        // Als Administrator anmelden
        $client->loginUser($user);

        // Link-Versand-Formular aufrufen
        $crawler = $client->request('GET', "/admin/checklists/{$checklist->getId()}/send-link");
        $this->assertResponseIsSuccessful();

        // Formular ausfüllen und abschicken
        $form = $crawler->selectButton('Link versenden')->form();
        $form['recipient_name'] = 'Test Manager Admin';
        $form['recipient_email'] = 'manager-admin@test.com';
        $form['mitarbeiter_id'] = 'ADMIN-456';
        $form['person_name'] = 'Test Person Admin';
        $form['intro'] = 'Test Einleitung Admin';

        // POST-Request abschicken
        $client->submit($form);

        // Überprüfen, dass zur Stücklisten-Route weitergeleitet wird
        $this->assertResponseRedirects('/admin/checklists');
    }
}
