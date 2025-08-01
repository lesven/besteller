<?php

namespace App\Controller\Admin;

use App\Entity\EmailSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EmailSettingsController extends AbstractController
{
    /**
     * Konstruktor mit EntityManager für den Zugriff auf die E-Mail-Einstellungen.
     *
     * @param EntityManagerInterface $entityManager Datenbankzugriff
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Bearbeitet die globalen E-Mail-Einstellungen.
     *
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response Formularseite für die Einstellungen
     */
    public function edit(Request $request): Response
    {
        $repository = $this->entityManager->getRepository(EmailSettings::class);
        $settings = $repository->find(1);

        if (!$settings) {
            $settings = new EmailSettings();
            $this->entityManager->persist($settings);
        }

        if ($request->isMethod('POST')) {
            $settings->setHost($request->request->getString('host'));
            $settings->setPort($request->request->getInt('port'));

            $username = trim($request->request->getString('username', ''));
            $settings->setUsername($username === '' ? null : $username);

            $password = trim($request->request->getString('password', ''));
            $settings->setPassword($password === '' ? null : $password);

            $settings->setIgnoreSsl($request->request->getBoolean('ignore_ssl'));
            $settings->setSenderEmail($request->request->getString('sender_email'));

            $this->entityManager->flush();

            $this->addFlash('success', 'E-Mail Einstellungen wurden gespeichert.');
        }

        return $this->render('admin/email_settings/edit.html.twig', [
            'settings' => $settings,
        ]);
    }
}
