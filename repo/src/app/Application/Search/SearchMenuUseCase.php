<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Application\Search\Ports\MenuRepositoryInterface;
use App\Domain\Risk\ProfanityFilter;
use App\Domain\Search\AllergenFilter;
use App\Domain\Search\SearchQuery;
use Illuminate\Support\Facades\DB;

class SearchMenuUseCase
{
    public function __construct(
        private readonly MenuRepositoryInterface $menuRepository,
        private readonly ProfanityFilter $profanityFilter,
        private readonly AllergenFilter $allergenFilter,
    ) {}

    /**
     * @return array{items: array, total: int, trending: array, blocked: bool, block_message: string|null, suggestion: string|null}
     */
    public function execute(SearchQuery $query): array
    {
        $locationId = $query->locationId;

        // Validate query
        $errors = $query->validate();
        if (!empty($errors)) {
            return [
                'items' => [],
                'total' => 0,
                'trending' => $this->menuRepository->getTrendingTerms($locationId),
                'blocked' => false,
                'block_message' => null,
                'suggestion' => null,
                'errors' => $errors,
            ];
        }

        // Check profanity
        if ($query->hasKeyword()) {
            $filterResult = $this->profanityFilter->check($query->keyword);
            if ($filterResult['blocked']) {
                // Immutable audit log for every blocked search rule hit
                try {
                    DB::table('rule_hit_logs')->insert([
                        'type' => 'banned_term_blocked',
                        'device_fingerprint_id' => request()->attributes->get('device_fingerprint_id'),
                        'ip_address' => request()->ip(),
                        'details' => json_encode([
                            'keyword' => $query->keyword,
                            'matched_word' => $filterResult['matched_word'] ?? null,
                        ]),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable) {
                    // Don't block search flow for logging failures
                }

                $trending = $this->menuRepository->getTrendingTerms($locationId);
                return [
                    'items' => [],
                    'total' => 0,
                    'trending' => $trending,
                    'blocked' => true,
                    'block_message' => $filterResult['message'],
                    'suggestion' => $this->profanityFilter->getSuggestion($trending),
                ];
            }
        }

        // Build allergen/attribute exclusions
        $allergenExclusions = [];
        if ($query->hasAllergenExclusions()) {
            $allergenExclusions = $this->allergenFilter->buildExclusions($query->allergenExclusions);
        }

        // Add spicy level exclusion if specified
        if ($query->maxSpicyLevel !== null) {
            $allergenExclusions[] = $this->allergenFilter->buildSpicyLevelExclusion($query->maxSpicyLevel);
        }

        // Execute search
        $result = $this->menuRepository->search($query, $allergenExclusions);

        return [
            'items' => $result['items'],
            'total' => $result['total'],
            'trending' => $this->menuRepository->getTrendingTerms($locationId),
            'blocked' => false,
            'block_message' => null,
            'suggestion' => null,
        ];
    }
}
