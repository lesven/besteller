<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Service\LinkSenderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ChecklistLinkController extends AbstractController
{
    public function __construct(private LinkSenderService $linkSender)
    {
    }

    public function sendLink(Request $request, Checklist $checklist): Response
    {
        if ($request->isMethod('POST')) {
            $recipientName = trim((string) $request->request->get('recipient_name'));
            $recipientEmail = trim((string) $request->request->get('recipient_email'));
            $mitarbeiterId = trim((string) $request->request->get('mitarbeiter_id'));
            $personName = trim((string) $request->request->get('person_name')) ?: null;
            $intro = (string) $request->request->get('intro');

            // CSRF-Token prüfen, falls im Formular vorhanden
            $tokenParam = $request->request->get('_token');
            $token = is_string($tokenParam) ? $tokenParam : null;
            if ($token !== null && !$this->isCsrfTokenValid('send-link' . $checklist->getId(), $token)) {
                $this->addFlash('error', 'Ungültiges Formular-Token.');
                return $this->redirectToRoute('admin_checklists');
            }

            try {
                $this->linkSender->sendChecklistLink($checklist, $recipientName, $recipientEmail, $mitarbeiterId, $personName, $intro);
                $this->addFlash('success', 'Link wurde erfolgreich versendet.');

                return $this->redirectToRoute('admin_checklists');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/checklist/send_link.html.twig', [
            'checklist' => $checklist,
        ]);
    }
}
