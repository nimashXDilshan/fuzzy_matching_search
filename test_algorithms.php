<?php
/**
 * Standalone test script for FuzzyMatchEngine field-specific algorithms
 * Run with: php test_algorithms.php
 */

// Simple test framework
function assertEqual($expected, $actual, $message) {
    if ($expected === $actual) {
        echo "✅ PASS: $message\n";
        return true;
    } else {
        echo "❌ FAIL: $message\n";
        echo "   Expected: " . var_export($expected, true) . "\n";
        echo "   Actual: " . var_export($actual, true) . "\n";
        return false;
    }
}

function assertGreaterThan($threshold, $actual, $message) {
    if ($actual > $threshold) {
        echo "✅ PASS: $message (score: " . round($actual, 3) . ")\n";
        return true;
    } else {
        echo "❌ FAIL: $message\n";
        echo "   Expected: > $threshold, Got: $actual\n";
        return false;
    }
}

echo "=== Testing FuzzyMatchEngine Field-Specific Algorithms ===\n\n";

// Include the class (simplified standalone version for testing)
class TestFuzzyMatcher {
    
    /**
     * Jaro-Winkler similarity algorithm
     */
    public function jaroWinkler(string $s1, string $s2): float
    {
        $s1 = strtolower($s1);
        $s2 = strtolower($s2);

        $len1 = strlen($s1);
        $len2 = strlen($s2);

        if ($len1 === 0 && $len2 === 0) return 1.0;
        if ($len1 === 0 || $len2 === 0) return 0.0;

        $matchWindow = max(intval(max($len1, $len2) / 2) - 1, 0);
        
        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);
        
        $matches = 0;
        $transpositions = 0;

        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min($i + $matchWindow + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || $s1[$i] !== $s2[$j]) continue;
                $s1Matches[$i] = true;
                $s2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) return 0.0;

        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$s1Matches[$i]) continue;
            while (!$s2Matches[$k]) $k++;
            if ($s1[$i] !== $s2[$k]) $transpositions++;
            $k++;
        }

        $jaro = (
            ($matches / $len1) +
            ($matches / $len2) +
            (($matches - $transpositions / 2) / $matches)
        ) / 3;

        $prefixLen = 0;
        for ($i = 0; $i < min(4, min($len1, $len2)); $i++) {
            if ($s1[$i] === $s2[$i]) $prefixLen++;
            else break;
        }

        return $jaro + ($prefixLen * 0.1 * (1 - $jaro));
    }

    /**
     * Match ID using Levenshtein
     */
    public function matchID(string $search, string $field): float
    {
        $searchNorm = preg_replace('/[\s\-]/', '', strtolower($search));
        $fieldNorm = preg_replace('/[\s\-]/', '', strtolower($field));

        if ($searchNorm === $fieldNorm) return 1.0;
        if (strpos($fieldNorm, $searchNorm) === 0) return 0.9;
        if (strpos($fieldNorm, $searchNorm) !== false) return 0.7;

        $distance = levenshtein($searchNorm, $fieldNorm);
        $maxLen = max(strlen($searchNorm), strlen($fieldNorm));
        
        if ($distance <= 2 && $maxLen > 0) {
            return 1 - ($distance / $maxLen);
        }

        return 0;
    }

    /**
     * Match phone using suffix matching
     */
    public function matchPhone(string $search, string $field): float
    {
        $searchDigits = preg_replace('/\D/', '', $search);
        $fieldDigits = preg_replace('/\D/', '', $field);

        if (empty($searchDigits) || empty($fieldDigits)) return 0;
        if ($searchDigits === $fieldDigits) return 1.0;

        // Suffix match
        if (strlen($searchDigits) >= 4 && str_ends_with($fieldDigits, $searchDigits)) {
            return 0.9;
        }

        if (strpos($fieldDigits, $searchDigits) !== false) return 0.7;

        return 0;
    }

    /**
     * N-gram similarity
     */
    public function ngramSimilarity(string $s1, string $s2, int $n = 3): float
    {
        $s1 = strtolower($s1);
        $s2 = strtolower($s2);
        
        if (strlen($s1) < $n || strlen($s2) < $n) return 0;

        $ngrams1 = [];
        $ngrams2 = [];
        
        for ($i = 0; $i <= strlen($s1) - $n; $i++) {
            $ngrams1[] = substr($s1, $i, $n);
        }
        for ($i = 0; $i <= strlen($s2) - $n; $i++) {
            $ngrams2[] = substr($s2, $i, $n);
        }

        $intersection = count(array_intersect($ngrams1, $ngrams2));
        $union = count(array_unique(array_merge($ngrams1, $ngrams2)));

        return $union > 0 ? $intersection / $union : 0;
    }
}

$matcher = new TestFuzzyMatcher();
$passed = 0;
$total = 0;

// ============ NAME MATCHING TESTS (Jaro-Winkler) ============
echo "--- Name Matching (Jaro-Winkler) ---\n";

$total++; if (assertGreaterThan(0.90, $matcher->jaroWinkler("john", "john"), "Exact: 'john' == 'john'")) $passed++;
$total++; if (assertGreaterThan(0.85, $matcher->jaroWinkler("jon", "john"), "Similar: 'jon' ≈ 'john'")) $passed++;
$total++; if (assertGreaterThan(0.80, $matcher->jaroWinkler("smith", "smyth"), "Typo: 'smith' ≈ 'smyth'")) $passed++;
$total++; if (assertGreaterThan(0.85, $matcher->jaroWinkler("steven", "stephen"), "Phonetic: 'steven' ≈ 'stephen'")) $passed++;
$total++; if (assertGreaterThan(0.75, $matcher->jaroWinkler("michael", "micheal"), "Typo: 'michael' ≈ 'micheal'")) $passed++;

echo "\n--- NIC/ID Matching (Levenshtein) ---\n";

$total++; if (assertEqual(1.0, $matcher->matchID("123456789V", "123456789V"), "Exact NIC match")) $passed++;
$total++; if (assertEqual(0.9, $matcher->matchID("1234567", "123456789V"), "Prefix NIC match")) $passed++;
$total++; if (assertGreaterThan(0.8, $matcher->matchID("123456789X", "123456789V"), "1 char typo in NIC")) $passed++;
$total++; if (assertEqual(0.7, $matcher->matchID("456789", "123456789V"), "Contains match")) $passed++;

echo "\n--- Phone Matching (Suffix) ---\n";

$total++; if (assertEqual(1.0, $matcher->matchPhone("0771234567", "0771234567"), "Exact phone match")) $passed++;
$total++; if (assertEqual(0.9, $matcher->matchPhone("234567", "0771234567"), "Suffix match (last 6 digits)")) $passed++;
$total++; if (assertEqual(0.7, $matcher->matchPhone("12345", "0771234567"), "Contains match")) $passed++;

echo "\n--- Email/Org Matching (Trigram) ---\n";

$total++; if (assertGreaterThan(0.3, $matcher->ngramSimilarity("gmail", "gmail.com"), "Trigram: 'gmail' in 'gmail.com'")) $passed++;
$total++; if (assertGreaterThan(0.2, $matcher->ngramSimilarity("tech", "technology"), "Trigram: 'tech' in 'technology'")) $passed++;

echo "\n";
echo "=== Results: $passed/$total tests passed ===\n";

if ($passed === $total) {
    echo "✅ All tests passed!\n";
} else {
    echo "⚠️ Some tests failed\n";
}
