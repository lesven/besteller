<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ChecklistTemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailService $emailService
    ) {
    }

    public function downloadEmailTemplate(Checklist $checklist): Response
    {
        $template = $checklist->getEmailTemplate() ?? $this->emailService->getDefaultTemplate();

        $response = new Response($template);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'email-template-' . $checklist->getId() . '.html'
            )
        );

        return $response;
    }

    public function resetEmailTemplate(Request $request, Checklist $checklist): Response
    {
        $tokenParam = $request->request->get('_token');
        $token = is_string($tokenParam) ? $tokenParam : null;

        if ($this->isCsrfTokenValid('reset_template' . $checklist->getId(), $token)) {
            $checklist->setEmailTemplate(null);
            $this->entityManager->flush();

            $this->addFlash('success', 'E-Mail-Template wurde auf Standard zurückgesetzt.');
        }

        return $this->redirectToRoute('admin_checklist_email_template', ['id' => $checklist->getId()]);
    }
}
