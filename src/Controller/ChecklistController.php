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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubmissionService $submissionService,
        private EmailService $emailService
    ) {}

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
            $generatedEmail = $this->emailService->generateAndSendEmail($submission);
            $submission->setGeneratedEmail($generatedEmail);
            
            // Aktualisierte Submission speichern
            $this->entityManager->flush();

            return $this->render('checklist/success.html.twig', [
                'checklist' => $checklist,
                'name' => $name
            ]);
        }

        return $this->render('checklist/form.html.twig', [
            'checklist' => $checklist,
            'name' => $name,
            'mitarbeiterId' => $mitarbeiterId,
            'email' => $email
        ]);
    }

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
