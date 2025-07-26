<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * Zeigt die Startseite an.
     *
     * @return Response Antwort mit der gerenderten Startseite
     */
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
