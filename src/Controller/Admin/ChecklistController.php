<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Repository\ChecklistRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ChecklistController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChecklistRepository $checklistRepository,
        private EmailService $emailService
    ) {
    }

    public function index(): Response
    {
        $checklists = $this->checklistRepository->findAll();

        return $this->render('admin/checklist/index.html.twig', [
            'checklists' => $checklists,
        ]);
    }

    public function new(Request $request): Response
    {
        $checklist = new Checklist();

        if ($request->isMethod('POST')) {
            $checklist->setTitle($request->request->get('title'));
            $checklist->setTargetEmail($request->request->get('target_email'));
            $checklist->setEmailTemplate($request->request->get('email_template'));

            $this->entityManager->persist($checklist);
            $this->entityManager->flush();

            $this->addFlash('success', 'Checkliste wurde erfolgreich erstellt.');

            return $this->redirectToRoute('admin_checklists');
        }

        return $this->render('admin/checklist/new.html.twig', [
            'checklist' => $checklist,
        ]);
    }

    public function edit(Request $request, Checklist $checklist): Response
    {
        if ($request->isMethod('POST')) {
            $checklist->setTitle($request->request->get('title'));
            $checklist->setTargetEmail($request->request->get('target_email'));
            $checklist->setEmailTemplate($request->request->get('email_template'));

            $this->entityManager->flush();

            $this->addFlash('success', 'Checkliste wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('admin_checklists');
        }

        return $this->render('admin/checklist/edit.html.twig', [
            'checklist' => $checklist,
        ]);
    }

    public function delete(Request $request, Checklist $checklist): Response
    {
        if ($this->isCsrfTokenValid('delete' . $checklist->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($checklist);
            $this->entityManager->flush();

            $this->addFlash('success', 'Checkliste wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_checklists');
    }

    public function emailTemplate(Request $request, Checklist $checklist): Response
    {
        // Handle template upload
        if ($request->isMethod('POST')) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get('template_file');
            $templateContent = $request->request->get('template_content');
            
            if ($uploadedFile) {
                // Validate file type
                $mimeType = $uploadedFile->getMimeType();
                $extension = strtolower($uploadedFile->getClientOriginalExtension());
                
                if (!in_array($mimeType, ['text/html', 'text/plain']) && 
                    !in_array($extension, ['html', 'htm'])) {
                    $this->addFlash('error', 'Bitte laden Sie nur HTML-Dateien hoch (.html oder .htm). Erkannter Dateityp: ' . $mimeType);
                    return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
                }
                
                // Check file size (max 1MB)
                if ($uploadedFile->getSize() > 1024 * 1024) {
                    $this->addFlash('error', 'Die Datei ist zu groß. Maximale Größe: 1MB.');
                    return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
                }
                
                // Read file content
                $templateContent = file_get_contents($uploadedFile->getPathname());
                
                if ($templateContent === false) {
                    $this->addFlash('error', 'Die hochgeladene Datei konnte nicht gelesen werden.');
                    return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
                }
            }
            
            if ($templateContent) {
                $checklist->setEmailTemplate($templateContent);
                $this->entityManager->flush();
                
                $this->addFlash('success', 'E-Mail-Template wurde erfolgreich aktualisiert.');
            } else {
                $this->addFlash('error', 'Bitte geben Sie Template-Inhalt ein oder laden eine Datei hoch.');
            }
            
            return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
        }

        return $this->render('admin/checklist/email_template.html.twig', [
            'checklist' => $checklist,
            'currentTemplate' => $checklist->getEmailTemplate() ?? $this->emailService->getDefaultTemplate(),
            'placeholders' => [
                '{{name}}' => 'Name/Vorname der Person (aus Link)',
                '{{mitarbeiter_id}}' => 'Mitarbeitenden-ID (aus Link)',
                '{{stückliste}}' => 'Name der Stückliste',
                '{{auswahl}}' => 'Strukturierte Ausgabe aller getätigten Auswahlen nach Gruppe'
            ]
        ]);
    }

    public function downloadEmailTemplate(Checklist $checklist): Response
    {
        $template = $checklist->getEmailTemplate() ?? $this->emailService->getDefaultTemplate();
        
        $response = new Response($template);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('Content-Disposition', 
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'email-template-' . $checklist->getId() . '.html'
            )
        );
        
        return $response;
    }

    public function resetEmailTemplate(Request $request, Checklist $checklist): Response
    {
        if ($this->isCsrfTokenValid('reset_template' . $checklist->getId(), $request->request->get('_token'))) {
            $checklist->setEmailTemplate(null); // Dies wird das Standard-Template verwenden
            $this->entityManager->flush();
            
            $this->addFlash('success', 'E-Mail-Template wurde auf Standard zurückgesetzt.');
        }
        
        return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
    }
}