<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\GroupItem;
use Symfony\Component\HttpFoundation\Request;

class SubmissionService
{
    /**
     * Sammelt die vom Nutzer übermittelten Daten einer Stückliste.
     *
     * @param Checklist $checklist Die aktuelle Stückliste
     * @param Request   $request   Der HTTP-Request mit den Formdaten
     *
     * @return array<string, array<string, mixed>> Die strukturierten Bestelldaten
     */
    public function collectSubmissionData(Checklist $checklist, Request $request): array
    {
        $data = [];

        foreach ($checklist->getGroups() as $group) {
            $groupData = [];

            foreach ($group->getItems() as $item) {
                $fieldName = 'item_' . $item->getId();

                $value = match ($item->getType()) {
                    GroupItem::TYPE_CHECKBOX => $request->request->all($fieldName),
                    default => $request->request->get($fieldName),
                };

                if ($value !== null && $value !== '' && $value !== []) {
                    $groupData[$item->getLabel()] = [
                        'type' => $item->getType(),
                        'value' => $value,
                    ];
                }
            }

            if (!empty($groupData)) {
                $data[$group->getTitle()] = $groupData;
            }
        }

        return $data;
    }
    
    /**
     * Formatiert übermittelte Daten für den Versand per E-Mail.
     *
     * @param array<string, array<string, mixed>> $data Die zuvor gesammelten Bestelldaten
     *
     * @return string HTML-Markup für die E-Mail
     */
    public function formatSubmissionForEmail(array $data): string
    {
        $output = '';
        
        foreach ($data as $groupTitle => $groupData) {
            $output .= "<h3>{$groupTitle}</h3>\n<ul>\n";
            
            foreach ($groupData as $itemLabel => $itemData) {
                // Neue Datenstruktur verwenden
                if (is_array($itemData) && isset($itemData['type'], $itemData['value'])) {
                    $value = $itemData['value'];
                    if (is_array($value)) {
                        // Checkbox-Arrays
                        $output .= "<li><strong>{$itemLabel}:</strong> " . implode(', ', $value) . "</li>\n";
                    } else {
                        // Text und Radio
                        if (is_scalar($value)) {
                            $value = nl2br((string) $value);
                        } else {
                            $value = nl2br('');
                        }
                        $output .= "<li><strong>{$itemLabel}:</strong> {$value}</li>\n";
                    }
                } else {
                    // Fallback für alte Datenstruktur
                    if (is_array($itemData)) {
                        $output .= "<li><strong>{$itemLabel}:</strong> " . implode(', ', $itemData) . "</li>\n";
                    } else {
                        if (is_scalar($itemData)) {
                            $itemData = nl2br((string) $itemData);
                        } else {
                            $itemData = nl2br('');
                        }
                        $output .= "<li><strong>{$itemLabel}:</strong> {$itemData}</li>\n";
                    }
                }
            }
            
            $output .= "</ul>\n";
        }
        
        return $output;
    }
}
