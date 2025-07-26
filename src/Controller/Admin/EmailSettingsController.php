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
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function edit(Request $request): Response
    {
        $repository = $this->entityManager->getRepository(EmailSettings::class);
        $settings = $repository->find(1);

        if (!$settings) {
            $settings = new EmailSettings();
            $this->entityManager->persist($settings);
        }

        if ($request->isMethod('POST')) {
            $settings->setHost($request->request->get('host'));
            $settings->setPort((int) $request->request->get('port'));
            $settings->setUsername($request->request->get('username') ?: null);
            $settings->setPassword($request->request->get('password') ?: null);
            $settings->setIgnoreSsl($request->request->getBoolean('ignore_ssl'));

            $this->entityManager->flush();

            $this->addFlash('success', 'E-Mail Einstellungen wurden gespeichert.');
        }

        return $this->render('admin/email_settings/edit.html.twig', [
            'settings' => $settings,
        ]);
    }
}
