<?php

namespace App\Controller\Admin;

use App\Entity\EmailSettings;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ConfirmationTemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailService $emailService
    ) {
    }

    public function edit(Request $request): Response
    {
        $settings = $this->entityManager->getRepository(EmailSettings::class)->find(1);
        if (!$settings) {
            $settings = new EmailSettings();
            $this->entityManager->persist($settings);
        }

        if (!$request->isMethod('POST')) {
            return $this->render('admin/confirmation_template.html.twig', [
                'currentTemplate' => $settings->getConfirmationEmailTemplate() ?? $this->emailService->getConfirmationTemplate(),
            ]);
        }

        $result = $this->extractTemplateContent($request, 'admin_confirmation_template');
        if ($result instanceof Response) {
            return $result;
        }

        $settings->setConfirmationEmailTemplate($result);
        $this->entityManager->flush();

        $this->addFlash('success', 'Best\u00E4tigungs-Template wurde erfolgreich aktualisiert.');

        return $this->redirectToRoute('admin_confirmation_template');
    }

    public function download(): Response
    {
        $settings = $this->entityManager->getRepository(EmailSettings::class)->find(1);
        $template = $settings?->getConfirmationEmailTemplate() ?? $this->emailService->getConfirmationTemplate();

        $response = new Response($template);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'confirmation-template.html'
            )
        );

        return $response;
    }

    private function extractTemplateContent(Request $request, string $route): Response|string
    {
        $uploadedFile = $request->files->get('template_file');
        $templateRaw = $request->request->get('template_content');
        $templateContent = is_string($templateRaw) ? $templateRaw : null;

        if ($uploadedFile) {
            $mimeType = $uploadedFile->getMimeType();
            $extension = strtolower($uploadedFile->getClientOriginalExtension());

            if (!in_array($mimeType, ['text/html', 'text/plain']) && !in_array($extension, ['html', 'htm'])) {
                $this->addFlash('error', 'Bitte laden Sie nur HTML-Dateien hoch (.html oder .htm). Detektierter MIME-Typ: ' . $mimeType . '.');
                return $this->redirectToRoute($route);
            }

            if ($uploadedFile->getSize() > 1024 * 1024) {
                $this->addFlash('error', 'Die Datei ist zu gro\u00DF. Maximale Gr\u00F6\u00DFe: 1MB.');
                return $this->redirectToRoute($route);
            }

            // Verwende Symfony's UploadedFile::openFile() für sicheren Dateizugriff
            $fileObject = $uploadedFile->openFile('r');
            $fileContent = $fileObject->fread($uploadedFile->getSize());
            if ($fileContent === false) {
                $this->addFlash('error', 'Die hochgeladene Datei konnte nicht gelesen werden.');
                return $this->redirectToRoute($route);
            }

            // Einfache Inhaltsvalidierung: Blockiere verdächtige Inhalte
            if (str_contains($fileContent, '<script>') || str_contains($fileContent, 'javascript:')) {
                $this->addFlash('error', 'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');
                return $this->redirectToRoute($route);
            }

            $templateContent = $fileContent;
        }

        if ($templateContent === null || $templateContent === '') {
            $this->addFlash('error', 'Bitte geben Sie Template-Inhalt ein oder laden eine Datei hoch.');
            return $this->redirectToRoute($route);
        }

        return $templateContent;
    }
}
