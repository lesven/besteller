<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    /**
     * Konstruktor zum Zugriff auf die Datenbank.
     *
     * @param EntityManagerInterface $entityManager Datenbankzugriff
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Zeigt das Dashboard mit einer Liste aller Checklisten.
     *
     * @return Response Anzeige des Dashboards
     */
    public function index(): Response
    {
        $checklists = $this->entityManager->getRepository(Checklist::class)->findAll();
        
        return $this->render('admin/dashboard.html.twig', [
            'checklists' => $checklists,
        ]);
    }
}
