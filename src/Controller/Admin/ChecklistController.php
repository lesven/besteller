<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Repository\ChecklistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ChecklistController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChecklistRepository $checklistRepository
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
}