<?php

declare(strict_types=1);

namespace App\Domain\Search;

class AllergenFilter
{
    /**
     * Build JSONB exclusion conditions for the given attribute filters.
     *
     * Returns an array of exclusion rules used to filter out items
     * based on their JSONB `attributes` column.
     *
     * @param array $filters e.g. ['nuts', 'gluten', 'dairy', 'shellfish', 'spicy']
     * @return array Array of exclusion rules
     */
    public function buildExclusions(array $filters): array
    {
        $exclusions = [];

        foreach ($filters as $filter) {
            $filter = strtolower(trim($filter));

            switch ($filter) {
                case 'nuts':
                    // Exclude items where contains_nuts = true
                    $exclusions[] = ['key' => 'contains_nuts', 'exclude_when' => true];
                    break;

                case 'gluten':
                    // Exclude items where gluten_free = false (contains gluten)
                    $exclusions[] = ['key' => 'gluten_free', 'exclude_when' => false];
                    break;

                case 'dairy':
                    // Exclude items where contains_dairy = true
                    $exclusions[] = ['key' => 'contains_dairy', 'exclude_when' => true];
                    break;

                case 'shellfish':
                    // Exclude items where contains_shellfish = true
                    $exclusions[] = ['key' => 'contains_shellfish', 'exclude_when' => true];
                    break;

                case 'vegan':
                    // Show only vegan: exclude where is_vegan = false
                    $exclusions[] = ['key' => 'is_vegan', 'exclude_when' => false];
                    break;

                case 'spicy':
                    // Exclude spicy items: exclude where spicy_level > 0
                    $exclusions[] = ['key' => 'spicy_level', 'exclude_when_gt' => 0];
                    break;
            }
        }

        return $exclusions;
    }

    /**
     * Build a spicy-level exclusion for a maximum spicy level threshold.
     * e.g. maxLevel=1 means exclude items with spicy_level > 1
     */
    public function buildSpicyLevelExclusion(int $maxLevel): array
    {
        return ['key' => 'spicy_level', 'exclude_when_gt' => $maxLevel];
    }

    /**
     * Get available filter options for the UI.
     */
    public function availableFilters(): array
    {
        return [
            'nuts' => 'Contains Nuts',
            'gluten' => 'Contains Gluten',
            'dairy' => 'Contains Dairy',
            'shellfish' => 'Contains Shellfish',
            'vegan' => 'Vegan Only',
            'spicy' => 'Not Spicy',
        ];
    }

    /**
     * Spicy level options for granular UI control.
     * 0 = Not Spicy, 1 = Mild, 2 = Medium, 3 = Hot
     */
    public function spicyLevelOptions(): array
    {
        return [
            0 => 'Not Spicy',
            1 => 'Mild',
            2 => 'Medium',
            3 => 'Hot',
        ];
    }
}
