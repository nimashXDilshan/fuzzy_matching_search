<?php

namespace LKDomains\Services\Search;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Config\Configurable;

/**
 * Enhanced Fuzzy Match Engine
 *
 * Implements multiple fuzzy matching algorithms:
 * - Levenshtein distance (edit distance)
 * - Soundex phonetic matching
 * - Metaphone phonetic matching
 * - N-gram similarity
 * - Partial/substring matching
 *
 * Key Features:
 * - "Smit" will find "Smith"
 * - "Jon Doe" will find "John Doe"
 * - Handles typos and misspellings
 */
class FuzzyMatchEngine
{
    use Configurable;

    /**
     * Maximum Levenshtein distance to consider a match
     */
    private static $max_levenshtein_distance = 3;

    /**
     * Minimum similarity score (0-1) to include in results
     */
    private static $min_similarity_threshold = 0.4;

    /**
     * Weight for different matching algorithms
     */
    private static $algorithm_weights = [
        'exact' => 1.0,
        'starts_with' => 0.9,
        'contains' => 0.7,
        'soundex' => 0.6,
        'metaphone' => 0.65,
        'levenshtein' => 0.5,
        'ngram' => 0.4,
        'jaro_winkler' => 0.85,
    ];

    /**
     * Field-specific thresholds for optimal matching
     */
    private static $field_thresholds = [
        'name' => 0.85,       // Names - high threshold (Jaro-Winkler + Metaphone)
        'nic' => 0.90,        // NIC - strict matching (Levenshtein ≤2)
        'phone' => 0.80,      // Phone - suffix matching
        'email' => 0.70,      // Email - trigram
        'orgName' => 0.60,    // Org names - trigram
        'brNumber' => 0.90,   // BR Number - strict (Levenshtein ≤2)
    ];

    /**
     * Map database field names to field types
     */
    private static $field_type_map = [
        'FirstName' => 'name',
        'Surname' => 'name',
        'FullName' => 'name',
        'NIC' => 'nic',
        'MobileTelephone' => 'phone',
        'Phone' => 'phone',
        'Email' => 'email',
        'Name' => 'orgName',
        'TradingName' => 'orgName',
        'RegistrationNumber' => 'brNumber',
    ];

    /**
     * Perform fuzzy search on a DataList
     *
     * @param DataList $list The data list to search
     * @param string $searchTerm The search term
     * @param array $fields Fields to search in
     * @param array $options Search options
     * @return array Array of [record, score] pairs sorted by score
     */
    public function fuzzySearch(DataList $list, string $searchTerm, array $fields, array $options = []): array
    {
        $searchTerm = $this->sanitizeSearchTerm($searchTerm);

        if (strlen($searchTerm) < 2) {
            return [];
        }

        $results = [];
        $searchWords = $this->tokenize($searchTerm);

        // First, get candidates using SQL for performance
        $candidates = $this->getCandidates($list, $searchTerm, $fields, $options);

        // Then score each candidate using fuzzy algorithms
        foreach ($candidates as $record) {
            $totalScore = 0;
            $matchedFields = [];

            foreach ($fields as $field) {
                $fieldValue = $this->getFieldValue($record, $field);
                if (empty($fieldValue)) {
                    continue;
                }

                $fieldScore = $this->calculateFieldScore($searchTerm, $searchWords, $fieldValue, $field);

                if ($fieldScore > 0) {
                    $totalScore = max($totalScore, $fieldScore);
                    $matchedFields[] = $field;
                }
            }

            if ($totalScore >= self::config()->get('min_similarity_threshold')) {
                $results[] = [
                    'record' => $record,
                    'score' => $totalScore,
                    'matchedFields' => $matchedFields
                ];
            }
        }

        // Sort by score descending
        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    /**
     * Get initial candidates using SQL queries for performance
     */
    protected function getCandidates(DataList $list, string $searchTerm, array $fields, array $options): DataList
    {
        $searchTerm = trim($searchTerm);
        $words = $this->tokenize($searchTerm);

        // Build filter conditions
        $filters = [];

        foreach ($fields as $field) {
            // Exact match
            $filters["{$field}:PartialMatch"] = $searchTerm;

            // Each word partial match
            foreach ($words as $word) {
                if (strlen($word) >= 2) {
                    $filters["{$field}:PartialMatch"][] = $word;
                }
            }
        }

        // Try full-text search first if available
        $fullTextResults = $this->tryFullTextSearch($list, $searchTerm, $fields);
        if ($fullTextResults !== null && $fullTextResults->count() > 0) {
            return $fullTextResults;
        }

        // Fall back to LIKE queries with phonetic expansion
        $phoneticTerms = $this->generatePhoneticVariants($searchTerm);

        $filterGroups = [];
        foreach ($fields as $field) {
            // Original term
            $filterGroups[] = ["{$field}:PartialMatch" => $searchTerm];

            // Each word
            foreach ($words as $word) {
                if (strlen($word) >= 2) {
                    $filterGroups[] = ["{$field}:PartialMatch" => $word];
                }
            }

            // Common typo corrections
            $typoVariants = $this->generateTypoVariants($searchTerm);
            foreach ($typoVariants as $variant) {
                $filterGroups[] = ["{$field}:PartialMatch" => $variant];
            }
        }

        // Apply filterAny for OR conditions
        if (!empty($filterGroups)) {
            $anyFilters = [];
            foreach ($filterGroups as $fg) {
                $key = array_key_first($fg);
                $anyFilters[$key] = $fg[$key];
            }
            $list = $list->filterAny($anyFilters);
        }

        // Apply limit for performance
        $limit = $options['candidateLimit'] ?? 200;
        return $list->limit($limit);
    }

    /**
     * Try to use MySQL full-text search
     */
    protected function tryFullTextSearch(DataList $list, string $searchTerm, array $fields): ?DataList
    {
        try {
            $tableName = $list->dataClass()::config()->get('table_name');
            if (!$tableName) {
                $tableName = str_replace('\\', '_', $list->dataClass());
            }

            // Check if full-text index exists
            $indexCheck = DB::query("SHOW INDEX FROM \"{$tableName}\" WHERE Index_type = 'FULLTEXT'");
            if ($indexCheck->numRecords() === 0) {
                return null;
            }

            // Build full-text search query
            $ftFields = implode(', ', array_map(fn($f) => "\"{$f}\"", $fields));
            $escapedTerm = addslashes($searchTerm);

            // Use boolean mode with wildcards for partial matching
            $booleanTerm = implode(' ', array_map(
                fn($w) => strlen($w) >= 2 ? "+{$w}*" : $w,
                $this->tokenize($searchTerm)
            ));

            $sql = "MATCH({$ftFields}) AGAINST('{$booleanTerm}' IN BOOLEAN MODE)";

            return $list->where($sql);
        } catch (\Exception $e) {
            // Full-text not available, return null to use fallback
            return null;
        }
    }

    /**
     * Calculate match score for a field value using field-specific algorithms
     *
     * Routes to specialized matching functions based on field type:
     * - Names: Jaro-Winkler + Metaphone
     * - NIC/BR: Levenshtein (strict)
     * - Phone: Suffix matching
     * - Email/Org: Trigram similarity
     */
    protected function calculateFieldScore(
        string $searchTerm,
        array $searchWords,
        string $fieldValue,
        string $fieldName
    ): float {
        $fieldValue = strtolower(trim($fieldValue));
        $searchTerm = strtolower(trim($searchTerm));

        // PRIORITY 1: Exact match - always check first (score 100%)
        if ($fieldValue === $searchTerm) {
            return 1.0;
        }

        // PRIORITY 2: Starts with - important for partial typing (score 95%)
        if (strpos($fieldValue, $searchTerm) === 0) {
            return 0.95;
        }

        // Get field type for specialized matching
        $fieldTypeMap = self::config()->get('field_type_map');
        $fieldType = $fieldTypeMap[$fieldName] ?? 'generic';

        // Route to field-specific matching
        switch ($fieldType) {
            case 'name':
                return $this->matchName($searchTerm, $searchWords, $fieldValue);

            case 'nic':
            case 'brNumber':
                return $this->matchID($searchTerm, $fieldValue);

            case 'phone':
                return $this->matchPhone($searchTerm, $fieldValue);

            case 'email':
                return $this->matchEmail($searchTerm, $fieldValue);

            case 'orgName':
                return $this->matchOrgName($searchTerm, $fieldValue);

            default:
                // Fallback to generic word-level matching
                return $this->matchGeneric($searchTerm, $searchWords, $fieldValue);
        }
    }

    /**
     * Match personal names using Jaro-Winkler + Metaphone
     * Best for: FirstName, Surname
     *
     * Priority order (IDENTICAL to JavaScript demo):
     * 1. Exact full match (100%)
     * 2. Exact match to one of the search words (99%)
     * 3. Starts with search word (95%)
     * 4. Fuzzy match with Jaro-Winkler + Metaphone bonus (≤89%)
     *
     * NOTE: PHP searches each field (FirstName, Surname) separately.
     * For multi-word searches like "John Doe", if FirstName="John" gets 99%
     * and Surname="Doe" also gets 99%, the result will rank highly.
     */
    protected function matchName(string $searchTerm, array $searchWords, string $fieldValue): float
    {
        $fieldValue = trim($fieldValue);
        if (empty($fieldValue)) {
            return 0;
        }

        $fieldValueLower = strtolower($fieldValue);
        $searchTermLower = strtolower(trim($searchTerm));

        // ========================================
        // PRIORITY 1: Exact full match (100%)
        // Search term exactly matches field value
        // ========================================
        if ($fieldValueLower === $searchTermLower) {
            return 1.0;
        }

        // ========================================
        // PRIORITY 2: Exact match to one of the search words (99%)
        // For multi-word searches like "John Doe", check if field matches "John" or "Doe"
        // ========================================
        foreach ($searchWords as $searchWord) {
            $searchWordLower = strtolower($searchWord);
            if ($fieldValueLower === $searchWordLower) {
                return 0.99;
            }
        }

        // ========================================
        // PRIORITY 3: Field starts with search word (95%)
        // ========================================
        foreach ($searchWords as $searchWord) {
            $searchWordLower = strtolower($searchWord);
            if (str_starts_with($fieldValueLower, $searchWordLower)) {
                return 0.95;
            }
        }

        // ========================================
        // PRIORITY 4: FUZZY MATCHING (Capped at 89%)
        // Uses Jaro-Winkler + Metaphone phonetic bonus
        // ========================================
        $maxScore = 0;

        foreach ($searchWords as $searchWord) {
            // Jaro-Winkler similarity (best for names)
            $jaroScore = $this->jaroWinkler($searchWord, $fieldValue);

            // Metaphone phonetic match (+0.10 bonus, same as JavaScript)
            if (metaphone($searchWord) === metaphone($fieldValue)) {
                $jaroScore = $jaroScore + 0.10;
            }

            // Cap fuzzy at 89% to stay below exact matches
            if ($jaroScore >= 0.70) {
                $maxScore = max($maxScore, min(0.89, $jaroScore));
            }
        }

        return $maxScore;
    }

    /**
     * Match ID numbers (NIC, BR Number) using strict matching
     * Best for: NIC, RegistrationNumber
     *
     * Priority order (matching JavaScript):
     * 1. Exact match (100%)
     * 2. Ends with / suffix match (95%)
     * 3. Starts with / prefix match (90%)
     * 4. Contains (85%)
     * 5. Levenshtein k=1 typo tolerance (75%)
     */
    protected function matchID(string $searchTerm, string $fieldValue): float
    {
        // Normalize: remove spaces and hyphens, uppercase
        $searchNorm = strtoupper(preg_replace('/[\s\-]/', '', $searchTerm));
        $fieldNorm = strtoupper(preg_replace('/[\s\-]/', '', $fieldValue));

        if (strlen($searchNorm) < 2) {
            return 0;
        }

        // PRIORITY 1: Exact match (score 100%)
        if ($searchNorm === $fieldNorm) {
            return 1.0;
        }

        // PRIORITY 2: Ends with / suffix match (score 95%)
        if (str_ends_with($fieldNorm, $searchNorm)) {
            return 0.95;
        }

        // PRIORITY 3: Starts with / prefix match (score 90%)
        if (str_starts_with($fieldNorm, $searchNorm)) {
            return 0.90;
        }

        // PRIORITY 4: Contains anywhere (score 85%)
        if (strpos($fieldNorm, $searchNorm) !== false) {
            return 0.85;
        }

        // PRIORITY 5: Levenshtein k=1 typo tolerance (score 75%)
        if (strlen($searchNorm) >= 4) {
            $suffixLen = strlen($searchNorm);
            $fieldSuffix = substr($fieldNorm, -$suffixLen);
            $distance = levenshtein($fieldSuffix, $searchNorm);
            if ($distance <= 1) {
                return 0.75;
            }
        }

        return 0;
    }

    /**
     * Match phone numbers using suffix matching
     * Best for: MobileTelephone, Phone
     *
     * Priority order (matching JavaScript):
     * 1. Exact match (100%)
     * 2. Ends with / suffix match (90-95%)
     * 3. Starts with / prefix match (85%)
     * 4. Contains (75%)
     * 5. Levenshtein k=1 typo tolerance (70%)
     */
    protected function matchPhone(string $searchTerm, string $fieldValue): float
    {
        // Normalize: remove all non-digits
        $searchDigits = preg_replace('/\D/', '', $searchTerm);
        $fieldDigits = preg_replace('/\D/', '', $fieldValue);

        if (strlen($searchDigits) < 3 || empty($fieldDigits)) {
            return 0;
        }

        // ========================================
        // PRIORITY 1: Exact match (100%)
        // ========================================
        if ($searchDigits === $fieldDigits) {
            return 1.0;
        }

        // ========================================
        // PRIORITY 2: Suffix match - last N digits (95%)
        // ========================================
        if (str_ends_with($fieldDigits, $searchDigits)) {
            return 0.95;
        }

        // ========================================
        // PRIORITY 3: Prefix match - area code (90%)
        // ========================================
        if (str_starts_with($fieldDigits, $searchDigits)) {
            return 0.90;
        }

        // ========================================
        // PRIORITY 4: Contains anywhere (85%)
        // ========================================
        if (strpos($fieldDigits, $searchDigits) !== false) {
            return 0.85;
        }

        // ========================================
        // PRIORITY 5: Levenshtein typo tolerance (≤80%)
        // ========================================
        if (strlen($searchDigits) >= 4) {
            $suffixLen = strlen($searchDigits);
            $fieldSuffix = substr($fieldDigits, -$suffixLen);
            $distance = levenshtein($fieldSuffix, $searchDigits);
            $maxLen = max(strlen($fieldSuffix), strlen($searchDigits));

            if ($distance <= 2 && $maxLen > 0) {
                // Score based on similarity, capped at 80%
                $similarity = 1 - ($distance / $maxLen);
                return min(0.80, $similarity * 0.80);
            }
        }

        return 0;
    }

    /**
     * Match email addresses using trigram similarity
     * Best for: Email
     *
     * Priority order (matching JavaScript):
     * 1. Exact match (100%)
     * 2. Contains / partial match (85%)
     * 3. Trigram similarity (threshold 0.70)
     */
    protected function matchEmail(string $searchTerm, string $fieldValue): float
    {
        $searchNorm = strtolower(trim($searchTerm));
        $fieldNorm = strtolower(trim($fieldValue));
        $threshold = 0.70;

        if (strlen($searchNorm) < 3) {
            return 0;
        }

        // PRIORITY 1: Exact match (score 100%)
        if ($fieldNorm === $searchNorm) {
            return 1.0;
        }

        // PRIORITY 2: Contains / partial match (score 85%)
        if (strpos($fieldNorm, $searchNorm) !== false || strpos($searchNorm, $fieldNorm) !== false) {
            return 0.85;
        }

        // PRIORITY 3: Trigram similarity for fuzzy matching
        $trigram = $this->ngramSimilarity($searchNorm, $fieldNorm, 3);

        if ($trigram >= $threshold) {
            return $trigram;
        }

        return 0;
    }

    /**
     * Match organization names using trigram similarity
     * Best for: Name (Organization), TradingName
     *
     * Priority order (matching JavaScript):
     * 1. Exact match (100%)
     * 2. Contains (90%)
     * 3. Starts with (85%)
     * 4. Trigram similarity (threshold 0.70)
     */
    protected function matchOrgName(string $searchTerm, string $fieldValue): float
    {
        $searchNorm = strtolower(trim($searchTerm));
        $fieldNorm = strtolower(trim($fieldValue));
        $threshold = 0.70;

        if (strlen($searchNorm) < 2) {
            return 0;
        }

        // PRIORITY 1: Exact match (score 100%)
        if ($fieldNorm === $searchNorm) {
            return 1.0;
        }

        // PRIORITY 2: Contains (score 90%)
        if (strpos($fieldNorm, $searchNorm) !== false) {
            return 0.90;
        }

        // PRIORITY 3: Starts with (score 85%)
        if (str_starts_with($fieldNorm, $searchNorm)) {
            return 0.85;
        }

        // PRIORITY 4: Trigram similarity for partial matches
        $trigram = $this->ngramSimilarity($searchNorm, $fieldNorm, 3);

        if ($trigram >= $threshold) {
            return $trigram;
        }

        return 0;
    }

    /**
     * Generic fallback matching using original word similarity
     */
    protected function matchGeneric(string $searchTerm, array $searchWords, string $fieldValue): float
    {
        $fieldWords = $this->tokenize($fieldValue);
        $maxWordScore = 0;

        foreach ($searchWords as $searchWord) {
            foreach ($fieldWords as $fieldWord) {
                $wordScore = $this->calculateWordSimilarity($searchWord, $fieldWord);
                $maxWordScore = max($maxWordScore, $wordScore);
            }
        }

        return $maxWordScore;
    }

    /**
     * Jaro-Winkler similarity algorithm
     * Best for comparing short strings like names
     * Gives extra weight to matching prefixes
     *
     * @return float Similarity score between 0 and 1
     */
    protected function jaroWinkler(string $s1, string $s2): float
    {
        $s1 = strtolower($s1);
        $s2 = strtolower($s2);

        $len1 = strlen($s1);
        $len2 = strlen($s2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        // Calculate Jaro distance first
        $matchWindow = max(intval(max($len1, $len2) / 2) - 1, 0);

        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);

        $matches = 0;
        $transpositions = 0;

        // Find matching characters
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min($i + $matchWindow + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || $s1[$i] !== $s2[$j]) {
                    continue;
                }
                $s1Matches[$i] = true;
                $s2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        // Count transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$s1Matches[$i]) {
                continue;
            }
            while (!$s2Matches[$k]) {
                $k++;
            }
            if ($s1[$i] !== $s2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        // Calculate Jaro similarity
        $jaro = (
            ($matches / $len1) +
            ($matches / $len2) +
            (($matches - $transpositions / 2) / $matches)
        ) / 3;

        // Calculate common prefix (max 4 characters)
        $prefixLen = 0;
        for ($i = 0; $i < min(4, min($len1, $len2)); $i++) {
            if ($s1[$i] === $s2[$i]) {
                $prefixLen++;
            } else {
                break;
            }
        }

        // Jaro-Winkler: add prefix bonus
        $scalingFactor = 0.1;
        return $jaro + ($prefixLen * $scalingFactor * (1 - $jaro));
    }

    /**
     * Calculate similarity between two words using multiple algorithms
     */
    protected function calculateWordSimilarity(string $word1, string $word2): float
    {
        $word1 = strtolower($word1);
        $word2 = strtolower($word2);
        $weights = self::config()->get('algorithm_weights');

        // Exact match
        if ($word1 === $word2) {
            return $weights['exact'];
        }

        // Starts with (important for partial typing)
        if (strpos($word2, $word1) === 0 || strpos($word1, $word2) === 0) {
            return $weights['starts_with'];
        }

        // Contains
        if (strpos($word2, $word1) !== false || strpos($word1, $word2) !== false) {
            return $weights['contains'];
        }

        $scores = [];

        // Soundex matching (phonetic)
        if (soundex($word1) === soundex($word2)) {
            $scores[] = $weights['soundex'];
        }

        // Metaphone matching (better phonetic)
        if (metaphone($word1) === metaphone($word2)) {
            $scores[] = $weights['metaphone'];
        }

        // Levenshtein distance
        $maxLen = max(strlen($word1), strlen($word2));
        if ($maxLen > 0) {
            $distance = levenshtein($word1, $word2);
            $maxDistance = self::config()->get('max_levenshtein_distance');

            if ($distance <= $maxDistance) {
                // Convert distance to similarity score
                $similarity = 1 - ($distance / $maxLen);
                $scores[] = $similarity * $weights['levenshtein'];
            }
        }

        // N-gram similarity (for partial matches)
        $ngramScore = $this->ngramSimilarity($word1, $word2, 2);
        if ($ngramScore > 0.3) {
            $scores[] = $ngramScore * $weights['ngram'];
        }

        return empty($scores) ? 0 : max($scores);
    }

    /**
     * Calculate N-gram similarity between two strings
     */
    protected function ngramSimilarity(string $s1, string $s2, int $n = 2): float
    {
        $ngrams1 = $this->getNgrams($s1, $n);
        $ngrams2 = $this->getNgrams($s2, $n);

        if (empty($ngrams1) || empty($ngrams2)) {
            return 0;
        }

        $intersection = array_intersect($ngrams1, $ngrams2);
        $union = array_unique(array_merge($ngrams1, $ngrams2));

        return count($intersection) / count($union);
    }

    /**
     * Get N-grams from a string
     */
    protected function getNgrams(string $str, int $n): array
    {
        $str = strtolower($str);
        $ngrams = [];
        $len = strlen($str);

        for ($i = 0; $i <= $len - $n; $i++) {
            $ngrams[] = substr($str, $i, $n);
        }

        return $ngrams;
    }

    /**
     * Generate phonetic variants of a search term
     */
    protected function generatePhoneticVariants(string $term): array
    {
        $variants = [$term];
        $words = $this->tokenize($term);

        foreach ($words as $word) {
            // Common phonetic substitutions
            $substitutions = [
                'ph' => 'f',
                'ck' => 'k',
                'ee' => 'i',
                'oo' => 'u',
                'ou' => 'o',
                'ie' => 'y',
                'gh' => '',
                'kn' => 'n',
                'wr' => 'r',
                'wh' => 'w',
            ];

            foreach ($substitutions as $from => $to) {
                if (stripos($word, $from) !== false) {
                    $variants[] = str_ireplace($from, $to, $word);
                }
            }
        }

        return array_unique($variants);
    }

    /**
     * Generate common typo variants
     */
    protected function generateTypoVariants(string $term): array
    {
        $variants = [];
        $term = strtolower($term);

        // Double letter reduction: "Smitth" -> "Smith"
        $variants[] = preg_replace('/(.)\1+/', '$1', $term);

        // Vowel variations
        $vowels = ['a', 'e', 'i', 'o', 'u'];
        foreach ($vowels as $v1) {
            foreach ($vowels as $v2) {
                if ($v1 !== $v2) {
                    $variants[] = str_replace($v1, $v2, $term);
                }
            }
        }

        // Common keyboard adjacency typos
        $adjacencies = [
            'a' => 'sq', 's' => 'awd', 'd' => 'sef', 'f' => 'drg',
            'q' => 'wa', 'w' => 'qeas', 'e' => 'wrd', 'r' => 'etf',
        ];

        return array_unique(array_filter($variants));
    }

    /**
     * Tokenize a string into words
     */
    protected function tokenize(string $str): array
    {
        // Remove special characters and split
        $str = preg_replace('/[^\w\s]/', ' ', $str);
        $words = preg_split('/\s+/', trim($str));

        return array_filter($words, fn($w) => strlen($w) >= 1);
    }

    /**
     * Sanitize search term
     */
    protected function sanitizeSearchTerm(string $term): string
    {
        // Remove SQL injection attempts
        $term = preg_replace('/[\'";]/', '', $term);

        // Remove multiple spaces
        $term = preg_replace('/\s+/', ' ', $term);

        return trim($term);
    }

    /**
     * Get field value from record, supporting dot notation
     */
    protected function getFieldValue($record, string $field): ?string
    {
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $record;
            foreach ($parts as $part) {
                if (is_object($value) && method_exists($value, $part)) {
                    $value = $value->$part();
                } elseif (is_object($value) && isset($value->$part)) {
                    $value = $value->$part;
                } else {
                    return null;
                }
            }
            return is_string($value) ? $value : null;
        }

        return $record->$field ?? null;
    }

    /**
     * Static helper for simple fuzzy match check
     * Returns true if term1 is a fuzzy match for term2
     */
    public static function isFuzzyMatch(string $term1, string $term2, float $threshold = 0.5): bool
    {
        $engine = new self();
        $score = $engine->calculateWordSimilarity($term1, $term2);
        return $score >= $threshold;
    }

    /**
     * Get match explanation for debugging/display
     */
    public function explainMatch(string $searchTerm, string $fieldValue): array
    {
        $searchTerm = strtolower(trim($searchTerm));
        $fieldValue = strtolower(trim($fieldValue));

        $explanation = [
            'searchTerm' => $searchTerm,
            'fieldValue' => $fieldValue,
            'matches' => []
        ];

        // Check each algorithm
        if ($fieldValue === $searchTerm) {
            $explanation['matches'][] = ['type' => 'exact', 'score' => 1.0];
        }

        if (strpos($fieldValue, $searchTerm) === 0) {
            $explanation['matches'][] = ['type' => 'starts_with', 'score' => 0.9];
        }

        if (strpos($fieldValue, $searchTerm) !== false) {
            $explanation['matches'][] = ['type' => 'contains', 'score' => 0.7];
        }

        if (soundex($searchTerm) === soundex($fieldValue)) {
            $explanation['matches'][] = ['type' => 'soundex', 'score' => 0.6,
                'detail' => soundex($searchTerm)];
        }

        if (metaphone($searchTerm) === metaphone($fieldValue)) {
            $explanation['matches'][] = ['type' => 'metaphone', 'score' => 0.65,
                'detail' => metaphone($searchTerm)];
        }

        $distance = levenshtein($searchTerm, $fieldValue);
        $maxLen = max(strlen($searchTerm), strlen($fieldValue));
        $explanation['matches'][] = [
            'type' => 'levenshtein',
            'distance' => $distance,
            'similarity' => $maxLen > 0 ? round(1 - ($distance / $maxLen), 2) : 0
        ];

        $ngramScore = $this->ngramSimilarity($searchTerm, $fieldValue, 2);
        $explanation['matches'][] = [
            'type' => 'ngram',
            'score' => round($ngramScore, 2)
        ];

        $explanation['finalScore'] = $this->calculateWordSimilarity($searchTerm, $fieldValue);

        return $explanation;
    }
}
