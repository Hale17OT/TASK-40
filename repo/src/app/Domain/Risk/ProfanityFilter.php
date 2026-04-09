<?php

declare(strict_types=1);

namespace App\Domain\Risk;

class ProfanityFilter
{
    private array $bannedWords;

    /**
     * @param array $bannedWords List of banned words (lowercase)
     */
    public function __construct(array $bannedWords = [])
    {
        $this->bannedWords = array_map('strtolower', $bannedWords);
    }

    /**
     * Check if a query contains banned words.
     *
     * @return array{blocked: bool, matched_word: string|null, message: string|null}
     */
    public function check(string $query): array
    {
        $queryLower = strtolower(trim($query));

        if (empty($queryLower)) {
            return ['blocked' => false, 'matched_word' => null, 'message' => null];
        }

        $queryWords = preg_split('/\s+/', $queryLower);

        foreach ($this->bannedWords as $banned) {
            // Check exact word match
            if (in_array($banned, $queryWords, true)) {
                return [
                    'blocked' => true,
                    'matched_word' => $banned,
                    'message' => 'This search term is not allowed. Please try a different search.',
                ];
            }

            // Check if query contains banned word as substring
            if (str_contains($queryLower, $banned)) {
                return [
                    'blocked' => true,
                    'matched_word' => $banned,
                    'message' => 'This search term is not allowed. Please try a different search.',
                ];
            }
        }

        return ['blocked' => false, 'matched_word' => null, 'message' => null];
    }

    /**
     * Get suggestion text with trending terms.
     */
    public function getSuggestion(array $trendingTerms): string
    {
        if (empty($trendingTerms)) {
            return 'Try browsing our menu categories instead.';
        }

        $terms = array_slice($trendingTerms, 0, 5);
        return 'Try: ' . implode(', ', $terms);
    }
}
