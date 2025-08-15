<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Repository\ChecklistRepository;
use App\Service\EmailService;
use App\Service\ChecklistDuplicationService;
use App\Service\CsrfDeletionHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    use CsrfDeletionHelper;
    /**
     * Konstruktor für benötigte Services im Admin-Bereich.
     *
    * @param EntityManagerInterface    $entityManager      Zugriff auf die Datenbank
    * @param ChecklistRepository       $checklistRepository Repository für Checklisten
    * @param EmailService              $emailService       Service zum E-Mail-Versand
    * @param ChecklistDuplicationService $duplicationService Service zum Duplizieren von Checklisten
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChecklistRepository $checklistRepository,
        private EmailService $emailService,
        private ChecklistDuplicationService $duplicationService
    ) {
    }

    /**
     * Listet alle Checklisten auf.
     *
     * @return Response Liste aller Checklisten im Admin-Bereich
     */
    public function index(): Response
    {
        $checklists = $this->checklistRepository->findAll();

        return $this->render('admin/checklist/index.html.twig', [
            'checklists' => $checklists,
        ]);
    }

    /**
     * Erstellt eine neue Checkliste.
     *
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response Formular oder Weiterleitung
     */
    public function new(Request $request): Response
    {
        $checklist = new Checklist();

        if ($request->isMethod('POST')) {
            $title = (string) $request->request->get('title', '');
            $targetEmail = (string) $request->request->get('target_email', '');

            $checklist->setTitle($title);
            $checklist->setTargetEmail($targetEmail);
            $reply = trim((string) $request->request->get('reply_email'));
            if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Bitte eine gültige Rückfragen-E-Mail eingeben.');
                return $this->redirectToRoute('admin_checklist_new');
            }
            $checklist->setReplyEmail($reply !== '' ? $reply : null);

            $templateParam = $request->request->get('email_template');
            $template = is_string($templateParam) ? $templateParam : null;
            $checklist->setEmailTemplate($template);

            $this->entityManager->persist($checklist);
            $this->entityManager->flush();

            $this->addFlash('success', 'Checkliste wurde erfolgreich erstellt.');

            return $this->redirectToRoute('admin_checklists');
        }

        return $this->render('admin/checklist/new.html.twig', [
            'checklist' => $checklist,
        ]);
    }

    /**
     * Bearbeitet eine bestehende Checkliste.
     *
     * @param Request   $request   Aktuelle HTTP-Anfrage
     * @param Checklist $checklist Die zu bearbeitende Checkliste
     *
     * @return Response Formular oder Weiterleitung
     */
    public function edit(Request $request, Checklist $checklist): Response
    {
        if ($request->isMethod('POST')) {
            $title = (string) $request->request->get('title', '');
            $targetEmail = (string) $request->request->get('target_email', '');

            $checklist->setTitle($title);
            $checklist->setTargetEmail($targetEmail);
            $reply = trim((string) $request->request->get('reply_email'));
            if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Bitte eine gültige Rückfragen-E-Mail eingeben.');
                return $this->redirectToRoute('admin_checklist_edit', ['id' => $checklist->getId()]);
            }
            $checklist->setReplyEmail($reply !== '' ? $reply : null);

            $templateParam = $request->request->get('email_template');
            $template = is_string($templateParam) ? $templateParam : null;
            $checklist->setEmailTemplate($template);

            $this->entityManager->flush();

            $this->addFlash('success', 'Checkliste wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('admin_checklists');
        }

        $exampleMitarbeiterId = $this->generateUuid();

        return $this->render('admin/checklist/edit.html.twig', [
            'checklist' => $checklist,
            'exampleMitarbeiterId' => $exampleMitarbeiterId,
        ]);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $hex = bin2hex($data);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Löscht eine Checkliste mitsamt zugehörigen Einsendungen.
     *
     * @param Request   $request   Aktuelle HTTP-Anfrage
     * @param Checklist $checklist Die zu löschende Checkliste
     *
     * @return Response Weiterleitung zur Übersicht
     */
    public function delete(Request $request, Checklist $checklist): Response
    {
        $this->handleCsrfDeletion(
            $request,
            $checklist,
            'Checkliste wurde erfolgreich gelöscht.',
            function (Checklist $checklist): void {
                foreach ($checklist->getSubmissions() as $submission) {
                    $this->entityManager->remove($submission);
                }
            }
        );

        return $this->redirectToRoute('admin_checklists');
    }

    /**
     * Bearbeitet das E-Mail-Template einer Checkliste.
     *
     * @param Request   $request   Aktuelle HTTP-Anfrage
     * @param Checklist $checklist Die betreffende Checkliste
     *
     * @return Response Formularseite
     */
    public function emailTemplate(Request $request, Checklist $checklist): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('admin/checklist/email_template.html.twig', [
                'checklist' => $checklist,
                'currentTemplate' => $checklist->getEmailTemplate() ?? $this->emailService->getDefaultTemplate(),
                'placeholders' => [
                    '{{name}}' => 'Name/Vorname der Person (aus Link)',
                    '{{mitarbeiter_id}}' => 'Mitarbeitenden-ID (aus Link)',
                    '{{stückliste}}' => 'Name der Stückliste',
                    '{{auswahl}}' => 'Strukturierte Ausgabe aller getätigten Auswahlen nach Gruppe',
                    '{{rueckfragen_email}}' => 'Hinterlegte Rückfragen-Adresse',
                ],
            ]);
        }

        $result = $this->extractTemplateContent($request, $checklist, 'admin_checklist_email_template');

        if ($result instanceof Response) {
            return $result;
        }

        $checklist->setEmailTemplate($result);
        $this->entityManager->flush();

        $this->addFlash('success', 'E-Mail-Template wurde erfolgreich aktualisiert.');

        return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
    }


    /**
     * Dupliziert eine vorhandene Checkliste inklusive Gruppen und Items.
     *
     * @param Checklist $checklist Die zu duplizierende Checkliste
     *
     * @return Response Weiterleitung nach dem Duplizieren
     */
    public function duplicate(Checklist $checklist): Response
    {
        $this->duplicationService->duplicate($checklist);

        $this->addFlash('success', 'Checkliste wurde erfolgreich dupliziert.');

        return $this->redirectToRoute('admin_checklists');
    }

    /**
     * Bearbeitet das Template für die Link-E-Mails.
     *
     * @param Request   $request   Aktuelle HTTP-Anfrage
     * @param Checklist $checklist Die betreffende Checkliste
     *
     * @return Response Formularseite
     */
    public function linkEmailTemplate(Request $request, Checklist $checklist): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('admin/checklist/link_template.html.twig', [
                'checklist' => $checklist,
                'currentTemplate' => $checklist->getLinkEmailTemplate() ?? $this->emailService->getDefaultLinkTemplate(),
            ]);
        }

        $result = $this->extractTemplateContent($request, $checklist, 'admin_checklist_link_template');

        if ($result instanceof Response) {
            return $result;
        }

        $checklist->setLinkEmailTemplate($result);
        $this->entityManager->flush();

        $this->addFlash('success', 'Link-Template wurde erfolgreich aktualisiert.');

        return $this->redirectToRoute('admin_checklist_link_template', ['id' => $checklist->getId()]);
    }

    /**
     * Validiert Uploads und gibt den Template-Inhalt zurück oder eine Response im Fehlerfall.
     */
    private function extractTemplateContent(Request $request, Checklist $checklist, string $route): Response|string
    {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('template_file');
        $templateRaw = $request->request->get('template_content');
        $templateContent = is_string($templateRaw) ? $templateRaw : null;

        if ($uploadedFile) {
            $mimeType = $uploadedFile->getMimeType();
            $extension = strtolower($uploadedFile->getClientOriginalExtension());

            if (!in_array($mimeType, ['text/html', 'text/plain']) && !in_array($extension, ['html', 'htm'])) {
                $this->addFlash('error', 'Bitte laden Sie nur HTML-Dateien hoch (.html oder .htm). Detektierter MIME-Typ: ' . $mimeType . '.');
                return $this->redirectToRoute($route, ['id' => $checklist->getId()]);
            }

            if ($uploadedFile->getSize() > 1024 * 1024) {
                $this->addFlash('error', 'Die Datei ist zu groß. Maximale Größe: 1MB.');
                return $this->redirectToRoute($route, ['id' => $checklist->getId()]);
            }

            // Verwende Symfony's UploadedFile::openFile() für sicheren Dateizugriff
            $fileObject = $uploadedFile->openFile('r');
            $fileContent = $fileObject->fread($uploadedFile->getSize());
            if ($fileContent === false) {
                $this->addFlash('error', 'Die hochgeladene Datei konnte nicht gelesen werden.');
                return $this->redirectToRoute($route, ['id' => $checklist->getId()]);
            }

            // Einfache Inhaltsvalidierung: Blockiere verdächtige Inhalte (case-insensitive)
            $contentLower = strtolower($fileContent);
            if (str_contains($contentLower, '<script') || str_contains($contentLower, 'javascript:') || str_contains($contentLower, 'onclick=') || str_contains($contentLower, 'onload=')) {
                $this->addFlash('error', 'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');
                return $this->redirectToRoute($route, ['id' => $checklist->getId()]);
            }

            $templateContent = $fileContent;
        }

        if ($templateContent === null || $templateContent === '') {
            $this->addFlash('error', 'Bitte geben Sie Template-Inhalt ein oder laden eine Datei hoch.');
            return $this->redirectToRoute($route, ['id' => $checklist->getId()]);
        }

        return $templateContent;
    }
}
