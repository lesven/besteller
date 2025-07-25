<?php
namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Form\ChecklistType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChecklistController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function index(): Response
    {
        $checklists = $this->entityManager->getRepository(Checklist::class)->findAll();

        return $this->render('admin/checklist/index.html.twig', [
            'checklists' => $checklists,
        ]);
    }

    public function new(Request $request): Response
    {
        $checklist = new Checklist();
        $form = $this->createForm(ChecklistType::class, $checklist);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($checklist);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_checklists');
        }

        return $this->render('admin/checklist/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($id);
        if (!$checklist) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ChecklistType::class, $checklist);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_checklists');
        }

        return $this->render('admin/checklist/edit.html.twig', [
            'form' => $form->createView(),
            'checklist' => $checklist,
        ]);
    }

    public function delete(Request $request, int $id): Response
    {
        $checklist = $this->entityManager->getRepository(Checklist::class)->find($id);
        if ($checklist) {
            $this->entityManager->remove($checklist);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_checklists');
    }
}
