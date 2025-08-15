<?php

namespace App\Controller\Admin;

use App\Entity\Submission;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\CsrfDeletionHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
class SubmissionController extends AbstractController
{
    use CsrfDeletionHelper;
    /**
     * Konstruktor stellt benötigte Repositories bereit.
     *
     * @param EntityManagerInterface $entityManager      Datenbankzugriff
     * @param ChecklistRepository    $checklistRepository Repository für Checklisten
     * @param SubmissionRepository   $submissionRepository Repository für Einsendungen
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChecklistRepository $checklistRepository,
        private SubmissionRepository $submissionRepository
    ) {
    }

    /**
     * Übersicht über alle Stücklisten zur Auswahl.
     *
     * @return Response Auswahlseite der Stücklisten
     */
    public function index(): Response
    {
        $checklists = $this->checklistRepository->findAll();

        return $this->render('admin/submission/index.html.twig', [
            'checklists' => $checklists,
        ]);
    }

    /**
     * Zeigt alle Einsendungen einer bestimmten Checkliste an.
     *
     * @param Request $request    Aktuelle HTTP-Anfrage
     * @param int     $checklistId ID der Stückliste
     *
     * @return Response Tabelle der Einsendungen
     */
    public function byChecklist(Request $request, int $checklistId): Response
    {
        $checklist = $this->checklistRepository->find($checklistId);
        if (!$checklist) {
            throw $this->createNotFoundException('Stückliste nicht gefunden');
        }

        $search = $request->query->get('q');
        $search = is_string($search) ? $search : null;
        $submissions = $this->submissionRepository->findByChecklist($checklist, $search);

        return $this->render('admin/submission/by_checklist.html.twig', [
            'checklist' => $checklist,
            'submissions' => $submissions,
            'search' => $search,
        ]);
    }

    /**
     * Gibt die gespeicherte HTML-E-Mail einer Einsendung aus.
     *
     * @param Submission $submission Die gewählte Einsendung
     *
     * @return Response HTML-Ausgabe der gespeicherten E-Mail
     */
    public function viewHtml(Submission $submission): Response
    {
        return new Response($submission->getGeneratedEmail(), 200, [
            'Content-Type' => 'text/html'
        ]);
    }

    /**
     * Löscht eine Einsendung.
     *
     * @param Request    $request    Aktuelle HTTP-Anfrage
     * @param Submission $submission Die zu löschende Einsendung
     *
     * @return Response Weiterleitung zur Einsendungsübersicht
     */
    public function delete(Request $request, Submission $submission): Response
    {
        $checklist = $submission->getChecklist();
        if (!$checklist) {
            throw $this->createNotFoundException(sprintf('Zugehörige Checkliste für Submission #%d nicht gefunden.', $submission->getId()));
        }
        $checklistId = $checklist->getId();

        $this->handleCsrfDeletion($request, $submission, 'Einsendung wurde erfolgreich gelöscht.');

        return $this->redirectToRoute('admin_submissions_checklist', ['checklistId' => $checklistId]);
    }
}
