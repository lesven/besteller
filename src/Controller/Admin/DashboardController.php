<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function index(): Response
    {
        $checklists = $this->entityManager->getRepository(Checklist::class)->findAll();
        
        return $this->render('admin/dashboard.html.twig', [
            'checklists' => $checklists,
        ]);
    }
}
