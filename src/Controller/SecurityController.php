<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * Zeigt das Login-Formular und verarbeitet Login-Fehler.
     *
     * @param AuthenticationUtils $authenticationUtils Hilfsobjekt für Authentifizierung
     * @param Request $request                          Aktuelle HTTP-Anfrage
     *
     * @return Response Login-Seite oder Weiterleitung
     */
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Add CSRF token debugging in dev environment
        if ($this->getParameter('kernel.environment') === 'dev' && $error) {
            // Log CSRF-related errors for debugging
            if (str_contains($error->getMessage(), 'CSRF')) {
                $this->addFlash('info', 'CSRF-Token-Fehler erkannt. Bitte versuchen Sie es erneut.');
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Wird von der Firewall abgefangen und führt den Logout aus.
     *
     * @return void
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
