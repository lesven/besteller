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
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($id);
        
        if (!$checklist) {
            throw new NotFoundHttpException('Stückliste nicht gefunden');
        }

        // Parameter aus URL prüfen
        $name = $request->query->get('name');
        $mitarbeiterId = $request->query->get('mitarbeiter_id');
        $email = $request->query->get('email');

        if (!$name || !$mitarbeiterId || !$email) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        // Prüfen ob bereits eingereicht
        $existingSubmission = $this->entityManager->getRepository(Submission::class)
            ->findOneBy([
                'checklist' => $checklist,
                'mitarbeiterId' => $mitarbeiterId
            ]);

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
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($id);
        
        if (!$checklist) {
            throw new NotFoundHttpException('Stückliste nicht gefunden');
        }

        $name = $request->request->get('name');
        $mitarbeiterId = $request->request->get('mitarbeiter_id');
        $email = $request->request->get('email');

        if (!$name || !$mitarbeiterId || !$email) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        // Nochmals prüfen ob bereits eingereicht
        $existingSubmission = $this->entityManager->getRepository(Submission::class)
            ->findOneBy([
                'checklist' => $checklist,
                'mitarbeiterId' => $mitarbeiterId
            ]);

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
        // Parameter aus URL prüfen
        $stücklisteId = $request->query->get('stückliste_id');
        $name = $request->query->get('name');
        $mitarbeiterId = $request->query->get('mitarbeiter_id');
        $email = $request->query->get('email');

        if (!$stücklisteId || !$name || !$mitarbeiterId || !$email) {
            throw new NotFoundHttpException('Ungültige Parameter');
        }

        $checklist = $this->entityManager->getRepository(Checklist::class)->find($stücklisteId);
        
        if (!$checklist) {
            throw new NotFoundHttpException('Stückliste nicht gefunden');
        }

        // Prüfen ob bereits eingereicht
        $existingSubmission = $this->entityManager->getRepository(Submission::class)
            ->findOneBy([
                'checklist' => $checklist,
                'mitarbeiterId' => $mitarbeiterId
            ]);

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
                $submissionData = $this->collectFormData($request, $checklist);
                
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

    /**
     * Sammelt alle Formulardaten aus der Anfrage.
     *
     * @param Request   $request   Aktuelle HTTP-Anfrage
     * @param Checklist $checklist Die zu verarbeitende Stückliste
     *
     * @return array Aufbereitete Formulardaten
     */
    private function collectFormData(Request $request, Checklist $checklist): array
    {
        $formData = [];
        
        foreach ($checklist->getGroups() as $group) {
            $groupData = [];
            
            foreach ($group->getItems() as $item) {
                $fieldName = 'item_' . $item->getId();
                
                if ($item->getType() === 'checkbox') {
                    // Checkbox arrays
                    $value = $request->request->all($fieldName);
                } else {
                    // Text and radio
                    $value = $request->request->get($fieldName);
                }
                
                if ($value !== null && $value !== '' && $value !== []) {
                    $groupData[$item->getLabel()] = [
                        'type' => $item->getType(),
                        'value' => $value
                    ];
                }
            }
            
            if (!empty($groupData)) {
                $formData[$group->getTitle()] = $groupData;
            }
        }
        
        return $formData;
    }
}
