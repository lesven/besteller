<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class GroupController extends AbstractController
{
    /**
     * Konstruktor mit EntityManager für Gruppenoperationen.
     *
     * @param EntityManagerInterface $entityManager Datenbankzugriff
     */
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Erstellt eine neue Gruppe innerhalb einer Checkliste.
     *
     * @param Request   $request   Aktuelle HTTP-Anfrage
     * @param Checklist $checklist Checkliste, der die Gruppe hinzugefügt wird
     *
     * @return Response Formular oder Weiterleitung
     */
    public function create(Request $request, Checklist $checklist): Response
    {
        $group = new ChecklistGroup();
        $group->setChecklist($checklist);

        if ($request->isMethod('POST')) {
            $group->setTitle($request->request->get('title'));
            $group->setDescription($request->request->get('description'));
            $group->setSortOrder((int) $request->request->get('sort_order', 0));

            $this->entityManager->persist($group);
            $this->entityManager->flush();

            $this->addFlash('success', 'Gruppe wurde erfolgreich erstellt.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checklist->getId()]);
        }

        return $this->render('admin/group/create.html.twig', [
            'checklist' => $checklist,
            'group' => $group,
        ]);
    }

    /**
     * Bearbeitet die Angaben einer Gruppe.
     *
     * @param Request       $request Aktuelle HTTP-Anfrage
     * @param ChecklistGroup $group   Die zu bearbeitende Gruppe
     *
     * @return Response Formular oder Weiterleitung
     */
    public function edit(Request $request, ChecklistGroup $group): Response
    {
        if ($request->isMethod('POST')) {
            $group->setTitle($request->request->get('title'));
            $group->setDescription($request->request->get('description'));
            $group->setSortOrder((int) $request->request->get('sort_order', 0));

            $this->entityManager->flush();

            $this->addFlash('success', 'Gruppe wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $group->getChecklist()->getId()]);
        }

        return $this->render('admin/group/edit.html.twig', [
            'group' => $group,
        ]);
    }

    /**
     * Löscht eine Gruppe aus der Checkliste.
     *
     * @param Request       $request Aktuelle HTTP-Anfrage
     * @param ChecklistGroup $group   Die zu löschende Gruppe
     *
     * @return Response Weiterleitung zur Übersicht
     */
    public function delete(Request $request, ChecklistGroup $group): Response
    {
        $checklistId = $group->getChecklist()->getId();

        if ($this->isCsrfTokenValid('delete' . $group->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($group);
            $this->entityManager->flush();

            $this->addFlash('success', 'Gruppe wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_checklist_edit', ['id' => $checklistId]);
    }

    /**
     * Fügt einer Gruppe ein neues Element hinzu.
     *
     * @param Request       $request Aktuelle HTTP-Anfrage
     * @param ChecklistGroup $group   Gruppe, der das Element hinzugefügt wird
     *
     * @return Response Formular oder Weiterleitung
     */
    public function addItem(Request $request, ChecklistGroup $group): Response
    {
        $item = new GroupItem();
        $item->setGroup($group);

        if ($request->isMethod('POST')) {
            $item->setLabel($request->request->get('label'));
            $item->setType($request->request->get('type'));
            $item->setSortOrder((int) $request->request->get('sort_order', 0));

            // Handle options für Checkbox/Radio
            if (in_array($item->getType(), [GroupItem::TYPE_CHECKBOX, GroupItem::TYPE_RADIO])) {
                $lines = array_filter(array_map('trim', explode("\n", $request->request->get('options', ''))));
                $options = [];
                foreach ($lines as $line) {
                    $active = false;
                    if (preg_match('/\(aktiv\)$/i', $line)) {
                        $active = true;
                        $line = trim(preg_replace('/\(aktiv\)$/i', '', $line));
                    }
                    $options[] = ['label' => $line, 'active' => $active];
                }
                $item->setOptionsWithActive($options);
            }

            $this->entityManager->persist($item);
            $this->entityManager->flush();

            $this->addFlash('success', 'Element wurde erfolgreich hinzugefügt.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $group->getChecklist()->getId()]);
        }

        return $this->render('admin/group/add_item.html.twig', [
            'group' => $group,
            'item' => $item,
        ]);
    }

    /**
     * Bearbeitet ein bestehendes Gruppen-Element.
     *
     * @param Request  $request Aktuelle HTTP-Anfrage
     * @param GroupItem $item   Das zu bearbeitende Element
     *
     * @return Response Formular oder Weiterleitung
     */
    public function editItem(Request $request, GroupItem $item): Response
    {
        if ($request->isMethod('POST')) {
            $item->setLabel($request->request->get('label'));
            $item->setType($request->request->get('type'));
            $item->setSortOrder((int) $request->request->get('sort_order', 0));

            // Handle options für Checkbox/Radio
            if (in_array($item->getType(), [GroupItem::TYPE_CHECKBOX, GroupItem::TYPE_RADIO])) {
                $lines = array_filter(array_map('trim', explode("\n", $request->request->get('options', ''))));
                $options = [];
                foreach ($lines as $line) {
                    $active = false;
                    if (preg_match('/\(aktiv\)$/i', $line)) {
                        $active = true;
                        $line = trim(preg_replace('/\(aktiv\)$/i', '', $line));
                    }
                    $options[] = ['label' => $line, 'active' => $active];
                }
                $item->setOptionsWithActive($options);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Element wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $item->getGroup()->getChecklist()->getId()]);
        }

        return $this->render('admin/group/edit_item.html.twig', [
            'item' => $item,
        ]);
    }

    /**
     * Entfernt ein Element aus einer Gruppe.
     *
     * @param Request  $request Aktuelle HTTP-Anfrage
     * @param GroupItem $item   Das zu löschende Element
     *
     * @return Response Weiterleitung zur Checkliste
     */
    public function deleteItem(Request $request, GroupItem $item): Response
    {
        $checklistId = $item->getGroup()->getChecklist()->getId();

        if ($this->isCsrfTokenValid('delete' . $item->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($item);
            $this->entityManager->flush();

            $this->addFlash('success', 'Element wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_checklist_edit', ['id' => $checklistId]);
    }
}
