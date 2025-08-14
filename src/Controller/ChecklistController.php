<?php

namespace App\Controller;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
     * @param SubmissionFactory      $submissionFactory Factory für Submissions
     * @param LoggerInterface        $logger           Logger für Fehlermeldungen
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubmissionService $submissionService,
        private EmailService $emailService,
        private SubmissionFactory $submissionFactory,
        private LoggerInterface $logger
    ) {}

    /**
     * Holt eine Stückliste anhand der ID oder wirft eine Exception, falls nicht gefunden.
     *
     * @param int $checklistId ID der Stückliste
     * @return Checklist Gefundene Stückliste
     */
    private function getChecklistOr404(int $checklistId): Checklist
    {
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($checklistId);
        if (!$checklist) {
            throw new NotFoundHttpException('Stückliste nicht gefunden');
        }

        return $checklist;
    }

    /**
     * Extrahiert die Werte Name, Mitarbeiter-ID und E-Mail aus einer ParameterBag.
     *
     * @param ParameterBag $source Quelle der Parameter
     * @return array Enthält Name, Mitarbeiter-ID und E-Mail
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
     * Holt die Werte aus der Query der HTTP-Anfrage.
     *
     * @param Request $request HTTP-Anfrage
     * @return array Enthält Name, Mitarbeiter-ID und E-Mail
     */
    private function getRequestValuesFromQuery(Request $request): array
    {
        return $this->extractRequestValues($request->query);
    }

  
    /**
     * Holt die Werte aus dem Request-Body der HTTP-Anfrage.
     *
     * @param Request $request HTTP-Anfrage
     * @return array Enthält Name, Mitarbeiter-ID und E-Mail
     */
    private function getRequestValuesFromRequest(Request $request): array
    {
        return $this->extractRequestValues($request->request);
    }

    /**
     * Sucht eine bereits existierende Submission für die Stückliste und Mitarbeiter-ID.
     *
     * @param Checklist $checklist Stückliste
     * @param string $mitarbeiterId Mitarbeiter-ID
     * @return Submission|null Gefundene Submission oder null
     */
    private function findExistingSubmission(Checklist $checklist, string $mitarbeiterId): ?Submission
    {
        /** @var SubmissionRepository $repo */
        $repo = $this->entityManager->getRepository(Submission::class);

        return $repo->findOneByChecklistAndMitarbeiterId($checklist, $mitarbeiterId);
    }

    /**
     * Zeigt eine Stückliste anhand der ID an und prüft Parameter.
     *
     * @param int $checklistId ID der Stückliste
     * @param Request $request Aktuelle HTTP-Anfrage
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
     * @param int $checklistId ID der Stückliste
     * @param Request $request Aktuelle HTTP-Anfrage
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
     * @return Response Formularseite oder Erfolgsmeldung
     */
    public function form(Request $request): Response
    {
        // Parameter extrahieren und validieren
        $checklistIdParam = $request->query->get('checklist_id') ?? $request->query->get('list');
        if (!$checklistIdParam) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        [$name, $mitarbeiterId, $email] = $this->getRequestValuesFromQuery($request);
        $checklist = $this->getChecklistOr404((int) $checklistIdParam);
        $existingSubmission = $this->findExistingSubmission($checklist, $mitarbeiterId);

        $template = 'checklist/form.html.twig';
        $templateVars = [
            'checklist' => $checklist,
            'name' => $name,
            'mitarbeiterId' => $mitarbeiterId,
            'email' => $email
        ];

        // Wenn bereits eine Submission existiert, zeige die entsprechende Seite
        if ($existingSubmission) {
            $template = 'checklist/already_submitted.html.twig';
        } elseif ($request->isMethod('POST')) {
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
                try {
                    $generatedEmail = $this->emailService->generateAndSendEmail($submission);
                    $submission->setGeneratedEmail($generatedEmail);
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    $this->logger->error('E-Mail-Versendung fehlgeschlagen für Submission ' . $submission->getId() . ': ' . $e->getMessage());
                }
                $template = 'checklist/success.html.twig';
                $templateVars = [
                    'checklist' => $checklist,
                    'name' => $name
                ];
            } catch (\Exception $e) {
                $this->addFlash('error', 'Es ist ein Fehler beim Übermitteln der Stückliste aufgetreten. Bitte versuchen Sie es erneut.');
            }
        }

        return $this->render($template, $templateVars);
    }

}
