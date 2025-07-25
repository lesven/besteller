<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\GroupItem;
use Symfony\Component\HttpFoundation\Request;

class SubmissionService
{
    public function collectSubmissionData(Checklist $checklist, Request $request): array
    {
        $data = [];
        
        foreach ($checklist->getGroups() as $group) {
            $groupData = [];
            
            foreach ($group->getItems() as $item) {
                $fieldName = 'item_' . $item->getId();
                
                switch ($item->getType()) {
                    case GroupItem::TYPE_CHECKBOX:
                        $values = $request->request->all($fieldName) ?? [];
                        $groupData[$item->getLabel()] = $values;
                        break;
                        
                    case GroupItem::TYPE_RADIO:
                        $value = $request->request->get($fieldName);
                        if ($value) {
                            $groupData[$item->getLabel()] = $value;
                        }
                        break;
                        
                    case GroupItem::TYPE_TEXT:
                        $value = $request->request->get($fieldName);
                        if ($value) {
                            $groupData[$item->getLabel()] = $value;
                        }
                        break;
                }
            }
            
            if (!empty($groupData)) {
                $data[$group->getTitle()] = $groupData;
            }
        }
        
        return $data;
    }
    
    public function formatSubmissionForEmail(array $data): string
    {
        $output = '';
        
        foreach ($data as $groupTitle => $groupData) {
            $output .= "<h3>{$groupTitle}</h3>\n<ul>\n";
            
            foreach ($groupData as $itemLabel => $itemValue) {
                if (is_array($itemValue)) {
                    $output .= "<li><strong>{$itemLabel}:</strong> " . implode(', ', $itemValue) . "</li>\n";
                } else {
                    $output .= "<li><strong>{$itemLabel}:</strong> {$itemValue}</li>\n";
                }
            }
            
            $output .= "</ul>\n";
        }
        
        return $output;
    }
}
