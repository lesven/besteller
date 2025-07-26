<?php

namespace App\Controller;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChecklistController extends AbstractController
{
    /**
     * Konstruktor speichert benötigte Services.
     *
     * @param EntityManagerInterface $entityManager  Datenbankzugriff
     * @param SubmissionService      $submissionService Service zum Sammeln der Formularwerte
     * @param EmailService           $emailService     Versand der E-Mails
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubmissionService $submissionService,
        private EmailService $emailService
    ) {}

    private function getChecklistOr404(int $id): Checklist
    {
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($id);
        if (!$checklist) {
            throw new NotFoundHttpException('Stückliste nicht gefunden');
        }

        return $checklist;
    }

    private function getRequestValues(Request $request, bool $useQuery = true): array
    {
        $source = $useQuery ? $request->query : $request->request;

        $name = $source->get('name');
        $mitarbeiterId = $source->get('mitarbeiter_id');
        $email = $source->get('email');

        if (!$name || !$mitarbeiterId || !$email) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        return [$name, $mitarbeiterId, $email];
    }

    private function findExistingSubmission(Checklist $checklist, string $mitarbeiterId): ?Submission
    {
        return $this->entityManager->getRepository(Submission::class)->findOneBy([
            'checklist' => $checklist,
            'mitarbeiterId' => $mitarbeiterId,
        ]);
    }

    /**
     * Zeigt eine Stückliste anhand der ID an und prüft Parameter.
     *
     * @param int     $id      ID der Stückliste
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response HTML-Seite der Stückliste
     */
    public function show(int $id, Request $request): Response
    {
        $checklist = $this->getChecklistOr404($id);
        [$name, $mitarbeiterId, $email] = $this->getRequestValues($request);

        $existingSubmission = $this->findExistingSubmission($checklist, $mitarbeiterId);

        if ($existingSubmission) {
            return $this->render('checklist/already_submitted.html.twig', [
                'checklist' => $checklist,
                'name' => $name,
                'submission' => $existingSubmission
            ]);
        }

        return $this->render('checklist/show.html.twig', [
            'checklist' => $checklist,
            'name' => $name,
            'mitarbeiterId' => $mitarbeiterId,
            'email' => $email
        ]);
    }

    /**
     * Verarbeitet eine eingereichte Stückliste und speichert sie.
     *
     * @param int     $id      ID der Stückliste
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response Erfolgsmeldung nach dem Speichern
     */
    public function submit(int $id, Request $request): Response
    {
        $checklist = $this->getChecklistOr404($id);
        [$name, $mitarbeiterId, $email] = $this->getRequestValues($request, false);

        $existingSubmission = $this->findExistingSubmission($checklist, $mitarbeiterId);

        if ($existingSubmission) {
            return $this->redirectToRoute('checklist_show', [
                'id' => $id,
                'name' => $name,
                'mitarbeiter_id' => $mitarbeiterId,
                'email' => $email
            ]);
        }

        // Formulardaten sammeln
        $submissionData = $this->submissionService->collectSubmissionData($checklist, $request);

        // Submission erstellen
        $submission = new Submission();
        $submission->setChecklist($checklist);
        $submission->setName($name);
        $submission->setMitarbeiterId($mitarbeiterId);
        $submission->setEmail($email);
        $submission->setData($submissionData);

        // E-Mail generieren und versenden
        $generatedEmail = $this->emailService->generateAndSendEmail($submission);
        $submission->setGeneratedEmail($generatedEmail);

        // Speichern
        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        return $this->render('checklist/success.html.twig', [
            'checklist' => $checklist,
            'name' => $name
        ]);
    }

    /**
     * Zeigt das Formular zur Stückliste und verarbeitet Eingaben.
     *
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response Formularseite oder Erfolgsmeldung
     */
    public function form(Request $request): Response
    {
        $stücklisteId = $request->query->get('stückliste_id');
        if (!$stücklisteId) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        [$name, $mitarbeiterId, $email] = $this->getRequestValues($request);
        $checklist = $this->getChecklistOr404((int) $stücklisteId);

        $existingSubmission = $this->findExistingSubmission($checklist, $mitarbeiterId);

        if ($existingSubmission) {
            return $this->render('checklist/already_submitted.html.twig', [
                'checklist' => $checklist,
                'name' => $name,
                'mitarbeiterId' => $mitarbeiterId,
                'email' => $email
            ]);
        }

        // Formular verarbeiten
        if ($request->isMethod('POST')) {
            try {
                $submissionData = $this->submissionService->collectSubmissionData($checklist, $request);
                
                $submission = new Submission();
                $submission->setChecklist($checklist);
                $submission->setName($name);
                $submission->setMitarbeiterId($mitarbeiterId);
                $submission->setEmail($email);
                $submission->setData($submissionData);
                $submission->setSubmittedAt(new \DateTimeImmutable());

                // Erst Submission speichern
                $this->entityManager->persist($submission);
                $this->entityManager->flush();

                // Dann E-Mails senden und generierte E-Mail speichern
                try {
                    $generatedEmail = $this->emailService->generateAndSendEmail($submission);
                    $submission->setGeneratedEmail($generatedEmail);
                    
                    // Aktualisierte Submission speichern
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    // E-Mail-Versendung fehlgeschlagen, aber Submission ist gespeichert
                    // Log den Fehler für Admin-Review
                    error_log('E-Mail-Versendung fehlgeschlagen für Submission ' . $submission->getId() . ': ' . $e->getMessage());
                }

                return $this->render('checklist/success.html.twig', [
                    'checklist' => $checklist,
                    'name' => $name
                ]);
                
            } catch (\Exception $e) {
                // Submission fehlgeschlagen
                $this->addFlash('error', 'Es ist ein Fehler beim Übermitteln der Stückliste aufgetreten. Bitte versuchen Sie es erneut.');
                
                return $this->render('checklist/form.html.twig', [
                    'checklist' => $checklist,
                    'name' => $name,
                    'mitarbeiterId' => $mitarbeiterId,
                    'email' => $email
                ]);
            }
        }

        return $this->render('checklist/form.html.twig', [
            'checklist' => $checklist,
            'name' => $name,
            'mitarbeiterId' => $mitarbeiterId,
            'email' => $email
        ]);
    }

}
