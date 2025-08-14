<?php

namespace App\Controller;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
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
        private EmailService $emailService,
        private SubmissionFactory $submissionFactory
    ) {}

    private function getChecklistOr404(int $checklistId): Checklist
    {
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($checklistId);
        if (!$checklist) {
            throw new NotFoundHttpException('Stückliste nicht gefunden');
        }

        return $checklist;
    }

    /**
     * @return list{string, string, string}
     */
    private function extractRequestValues(ParameterBag $source): array
    {
        $name = urldecode($source->getString('name', ''));
        $mitarbeiterId = urldecode($source->getString('mitarbeiter_id', $source->getString('id', '')));
        $email = urldecode($source->getString('email', ''));

        if ($name === '' || $mitarbeiterId === '' || $email === '') {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        return [$name, $mitarbeiterId, $email];
    }

    /**
     * @return list{string, string, string}
     */
    private function getRequestValuesFromQuery(Request $request): array
    {
        return $this->extractRequestValues($request->query);
    }

    /**
     * @return list{string, string, string}
     */
    private function getRequestValuesFromRequest(Request $request): array
    {
        return $this->extractRequestValues($request->request);
    }

    private function findExistingSubmission(Checklist $checklist, string $mitarbeiterId): ?Submission
    {
        /** @var SubmissionRepository $repo */
        $repo = $this->entityManager->getRepository(Submission::class);

        return $repo->findOneByChecklistAndMitarbeiterId($checklist, $mitarbeiterId);
    }

    /**
     * Zeigt eine Stückliste anhand der ID an und prüft Parameter.
     *
     * @param int     $checklistId      ID der Stückliste
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response HTML-Seite der Stückliste
     */
    public function show(int $checklistId, Request $request): Response
    {
        $checklist = $this->getChecklistOr404($checklistId);
        [$name, $mitarbeiterId, $email] = $this->getRequestValuesFromQuery($request);

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
     * @param int     $checklistId      ID der Stückliste
     * @param Request $request Aktuelle HTTP-Anfrage
     *
     * @return Response Erfolgsmeldung nach dem Speichern
     */
    public function submit(int $checklistId, Request $request): Response
    {
        $checklist = $this->getChecklistOr404($checklistId);
        [$name, $mitarbeiterId, $email] = $this->getRequestValuesFromRequest($request);

        $existingSubmission = $this->findExistingSubmission($checklist, $mitarbeiterId);

        if ($existingSubmission) {
            return $this->redirectToRoute('checklist_show', [
                'id' => $checklistId,
                'name' => $name,
                'mitarbeiter_id' => $mitarbeiterId,
                'email' => $email
            ]);
        }

        // Formulardaten sammeln
        $submissionData = $this->submissionService->collectSubmissionData($checklist, $request);

        // Submission erstellen
        $submission = $this->submissionFactory->createSubmission(
            $checklist,
            $name,
            $mitarbeiterId,
            $email,
            $submissionData,
            false
        );

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
        $checklistIdParam = $request->query->get('checklist_id');
        if (!$checklistIdParam) {
            $checklistIdParam = $request->query->get('list');
        }
        if (!$checklistIdParam) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        [$name, $mitarbeiterId, $email] = $this->getRequestValuesFromQuery($request);
        $checklist = $this->getChecklistOr404((int) $checklistIdParam);

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
                
                $submission = $this->submissionFactory->createSubmission(
                    $checklist,
                    $name,
                    $mitarbeiterId,
                    $email,
                    $submissionData,
                    true
                );

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
