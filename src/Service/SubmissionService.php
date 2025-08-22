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
            $escapedGroupTitle = htmlspecialchars($groupTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $output .= "<h3>{$escapedGroupTitle}</h3>\n<ul>\n";
            
            foreach ($groupData as $itemLabel => $itemData) {
                $escapedItemLabel = htmlspecialchars($itemLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Neue Datenstruktur verwenden
                if (is_array($itemData) && isset($itemData['type'], $itemData['value'])) {
                    $value = $itemData['value'];

                    if (is_array($value)) {
                        // Checkbox-Arrays als Unterliste ausgeben
                        $output .= "<li><strong>{$escapedItemLabel}:</strong><ul>\n";
                        foreach ($value as $val) {
                            $escapedVal = is_scalar($val) ? htmlspecialchars((string) $val, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                            $escapedVal = nl2br($escapedVal);
                            $output .= "<li>{$escapedVal}</li>\n";
                        }
                        $output .= "</ul></li>\n";
                        continue;
                    }

                    // Text und Radio
                    $escapedValue = is_scalar($value) ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                    $escapedValue = nl2br($escapedValue);
                    $output .= "<li><strong>{$escapedItemLabel}:</strong> {$escapedValue}</li>\n";
                    continue;
                }

                // Fallback für alte Datenstruktur
                if (is_array($itemData)) {
                    $output .= "<li><strong>{$escapedItemLabel}:</strong><ul>\n";
                    foreach ($itemData as $val) {
                        $escapedVal = is_scalar($val) ? htmlspecialchars((string) $val, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                        $escapedVal = nl2br($escapedVal);
                        $output .= "<li>{$escapedVal}</li>\n";
                    }
                    $output .= "</ul></li>\n";
                    continue;
                }

                $escapedItemData = is_scalar($itemData) ? htmlspecialchars((string) $itemData, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                $escapedItemData = nl2br($escapedItemData);

                $output .= "<li><strong>{$escapedItemLabel}:</strong> {$escapedItemData}</li>\n";
            }
            
            $output .= "</ul>\n";
        }
        
        return $output;
    }
}
