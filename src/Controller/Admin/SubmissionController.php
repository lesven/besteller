<?php

namespace App\Controller\Admin;

use App\Entity\Submission;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SubmissionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChecklistRepository $checklistRepository,
        private SubmissionRepository $submissionRepository
    ) {
    }

    public function index(): Response
    {
        $checklists = $this->checklistRepository->findAll();

        return $this->render('admin/submission/index.html.twig', [
            'checklists' => $checklists,
        ]);
    }

    public function byChecklist(Request $request, int $checklistId): Response
    {
        $checklist = $this->checklistRepository->find($checklistId);
        if (!$checklist) {
            throw $this->createNotFoundException('Stückliste nicht gefunden');
        }

        $search = $request->query->get('q');
        $submissions = $this->submissionRepository->findByChecklist($checklist, $search);

        return $this->render('admin/submission/by_checklist.html.twig', [
            'checklist' => $checklist,
            'submissions' => $submissions,
            'search' => $search,
        ]);
    }

    public function viewHtml(Submission $submission): Response
    {
        return new Response($submission->getGeneratedEmail(), 200, [
            'Content-Type' => 'text/html'
        ]);
    }

    public function delete(Request $request, Submission $submission): Response
    {
        $checklistId = $submission->getChecklist()->getId();

        if ($this->isCsrfTokenValid('delete' . $submission->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($submission);
            $this->entityManager->flush();
            $this->addFlash('success', 'Einsendung wurde erfolgreich gel\xC3\xB6scht.');
        }

        return $this->redirectToRoute('admin_submissions_checklist', ['checklistId' => $checklistId]);
    }
}
