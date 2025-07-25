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
}
