<?php

namespace App\Controller\Admin;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\CsrfDeletionHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class GroupController extends AbstractController
{
    use CsrfDeletionHelper;
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
     * Bereinigt einen Wert durch Trimmen und konvertiert leere Strings zu null.
     *
     * @param string $value Der zu bereinigende Wert
     *
     * @return string|null Der bereinigte Wert oder null wenn leer
     */
    private function sanitizeValue(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Mappt Formulardaten auf eine ChecklistGroup-Entität.
     * Alle Inline-Kommentare auf Deutsch gemäß Repo-Richtlinien.
     *
     * @param Request $request Die aktuelle Anfrage mit Formdaten
     * @param ChecklistGroup $group Die zu befüllende Gruppe
     */
    private function handleGroupForm(Request $request, ChecklistGroup $group): void
    {
        // Titel direkt übernehmen
        $group->setTitle($request->request->getString('title'));

        // Beschreibung säubern (leer -> null)
        $group->setDescription($this->sanitizeValue($request->request->getString('description', '')));

        // Sortierung als Integer
        $group->setSortOrder($request->request->getInt('sort_order', 0));
    }

    /**
     * Mappt Formulardaten auf ein GroupItem und parst Optionen falls nötig.
     *
     * @param Request $request Die aktuelle Anfrage
     * @param GroupItem $item Das zu befüllende Item
     */
    private function handleItemForm(Request $request, GroupItem $item): void
    {
        $item->setLabel($request->request->getString('label'));
        $item->setType($request->request->getString('type'));
        $item->setSortOrder($request->request->getInt('sort_order', 0));

        // Für Checkbox/Radio: Optionen parsen
        if (in_array($item->getType(), [GroupItem::TYPE_CHECKBOX, GroupItem::TYPE_RADIO])) {
            $options = $this->parseOptions($request->request->getString('options', ''));
            $item->setOptionsWithActive($options);
        }
    }

    /**
     * Parst eine mehrzeilige Options-Eingabe in ein Array mit label/active.
     * Zeilen, die mit "(aktiv)" enden, werden als aktiv markiert.
     *
     * @param string $raw Raw-Text aus dem Formular
     * @return array<int,array{label:string,active:bool}> Gefilterte Optionen
     */
    private function parseOptions(string $raw): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $raw)));
        $options = [];
        foreach ($lines as $line) {
            $active = false;
            if (preg_match('/\(aktiv\)$/i', $line)) {
                $active = true;
                $line = trim(preg_replace('/\(aktiv\)$/i', '', $line) ?? $line);
            }
            $options[] = ['label' => $line, 'active' => $active];
        }

        return $options;
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
            $this->handleGroupForm($request, $group);

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
            $this->handleGroupForm($request, $group);

            $this->entityManager->flush();

            $this->addFlash('success', 'Gruppe wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $group->getChecklist()?->getId()]);
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
        $checklistId = $group->getChecklist()?->getId();

        $this->handleCsrfDeletion($request, $group, 'Gruppe wurde erfolgreich gelöscht.');

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
            $this->handleItemForm($request, $item);

            $this->entityManager->persist($item);
            $this->entityManager->flush();

            $this->addFlash('success', 'Element wurde erfolgreich hinzugefügt.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $group->getChecklist()?->getId()]);
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
            $this->handleItemForm($request, $item);

            $this->entityManager->flush();

            $this->addFlash('success', 'Element wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $item->getGroup()?->getChecklist()?->getId()]);
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
        $checklistId = $item->getGroup()?->getChecklist()?->getId();

        $this->handleCsrfDeletion($request, $item, 'Element wurde erfolgreich gelöscht.');

        return $this->redirectToRoute('admin_checklist_edit', ['id' => $checklistId]);
    }
}
