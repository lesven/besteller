<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Helfertrait zum Löschen von Entitäten mit CSRF-Schutz.
 */
trait CsrfDeletionHelper
{
    /**
     * Prüft den CSRF-Token und entfernt anschließend die Entität.
     *
     * @param Request        $request     Aktuelle HTTP-Anfrage
     * @param object         $entity      Zu löschende Entität
     * @param string         $flashMessage Erfolgsmeldung für den Benutzer
     * @param callable|null  $preRemoval  Optionaler Callback vor dem Entfernen
     */
    private function handleCsrfDeletion(Request $request, object $entity, string $flashMessage, ?callable $preRemoval = null): void
    {
        $token = $request->request->get('_token');
        $token = is_string($token) ? $token : null;

        $entityId = $entity->getId();
        if (empty($entityId)) {
            $this->addFlash('error', 'Ungültige Entität: Löschung nicht möglich.');
            return;
        }

        if ($this->isCsrfTokenValid('delete' . $entityId, $token)) {
            if ($preRemoval !== null) {
                $preRemoval($entity);
            }

            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            $this->addFlash('success', $flashMessage);
        }
    }
}
