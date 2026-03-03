/**
 * Large Scale Mock Data Generator for Fuzzy Search Demo
 * 
 * Generates:
 * - 10,000 Customers with diverse Sri Lankan and international names
 * - 1,000 Organizations with varied business types
 * 
 * Includes fuzzy matching algorithms for client-side search.
 */

// ============================================================================
// FUZZY MATCHING ENGINE
// ============================================================================

const FuzzyMatcher = {
    config: {
        maxLevenshteinDistance: 4,
        minSimilarityThreshold: 0.2,
        weights: { exact: 1.0, startsWith: 0.9, contains: 0.7, soundex: 0.6, metaphone: 0.65, levenshtein: 0.7, ngram: 0.5 }
    },

    levenshtein(s1, s2) {
        s1 = s1.toLowerCase(); s2 = s2.toLowerCase();
        const m = s1.length, n = s2.length;
        const dp = Array(m + 1).fill(null).map(() => Array(n + 1).fill(0));
        for (let i = 0; i <= m; i++) dp[i][0] = i;
        for (let j = 0; j <= n; j++) dp[0][j] = j;
        for (let i = 1; i <= m; i++) {
            for (let j = 1; j <= n; j++) {
                dp[i][j] = s1[i - 1] === s2[j - 1] ? dp[i - 1][j - 1] : 1 + Math.min(dp[i - 1][j], dp[i][j - 1], dp[i - 1][j - 1]);
            }
        }
        return dp[m][n];
    },

    soundex(str) {
        str = str.toUpperCase().replace(/[^A-Z]/g, '');
        if (!str) return '';
        const codes = { B: 1, F: 1, P: 1, V: 1, C: 2, G: 2, J: 2, K: 2, Q: 2, S: 2, X: 2, Z: 2, D: 3, T: 3, L: 4, M: 5, N: 5, R: 6 };
        let result = str[0], prev = codes[str[0]] || 0;
        for (let i = 1; i < str.length && result.length < 4; i++) {
            const code = codes[str[i]] || 0;
            if (code && code !== prev) result += code;
            prev = code;
        }
        return (result + '000').slice(0, 4);
    },

    metaphone(str) {
        str = str.toUpperCase().replace(/[^A-Z]/g, '');
        return str.replace(/^KN|^GN|^PN|^AE|^WR/, '').replace(/MB$/, 'M').replace(/X/, 'KS')
            .replace(/PH/g, 'F').replace(/[AEIOU]/g, '').replace(/(.)\1+/g, '$1').slice(0, 4);
    },

    ngramSimilarity(s1, s2, n = 2) {
        s1 = s1.toLowerCase(); s2 = s2.toLowerCase();
        const getNgrams = s => { const ng = []; for (let i = 0; i <= s.length - n; i++) ng.push(s.slice(i, i + n)); return ng; };
        const ng1 = getNgrams(s1), ng2 = getNgrams(s2);
        if (!ng1.length || !ng2.length) return 0;
        const set1 = new Set(ng1), set2 = new Set(ng2);
        const inter = [...set1].filter(x => set2.has(x)), union = new Set([...set1, ...set2]);
        return inter.length / union.size;
    },

    // ============================================================================
    // FIELD-SPECIFIC ALGORITHMS
    // ============================================================================

    /**
     * Field-specific thresholds and algorithm assignments
     */
    fieldConfig: {
        name: { threshold: 0.70 },      // Names - more fuzzy
        nic: { threshold: 0.90, maxDistance: 2 },  // IDs - strict
        email: { threshold: 0.70 },     // Emails - moderate
        phone: { threshold: 0.90 },     // Phones - strict
        orgName: { threshold: 0.70 },   // Org names - moderate
        brNumber: { threshold: 0.85 }   // BR numbers - strict
    },

    /**
     * Normalize input based on field type
     */
    normalize(input, fieldType = 'default') {
        if (!input) return '';
        input = String(input).trim().toLowerCase();

        switch (fieldType) {
            case 'email':
                // Preserve @ symbol for emails
                return input.replace(/[^a-z0-9@._-]/g, '');
            case 'phone':
                // Keep only digits
                return input.replace(/\D/g, '');
            case 'nic':
            case 'brNumber':
                // Alphanumeric only, uppercase for IDs
                return input.toUpperCase().replace(/[^A-Z0-9]/g, '');
            default:
                // Names - remove special chars but keep spaces
                return input.replace(/[^a-z\s'-]/g, '');
        }
    },

    /**
     * Jaro-Winkler Distance - Best for names
     * Prioritizes matches at the beginning of strings
     * Returns similarity score 0-1
     */
    jaroWinkler(s1, s2) {
        s1 = s1.toLowerCase(); s2 = s2.toLowerCase();
        if (s1 === s2) return 1;
        if (!s1.length || !s2.length) return 0;

        const matchWindow = Math.floor(Math.max(s1.length, s2.length) / 2) - 1;
        const s1Matches = new Array(s1.length).fill(false);
        const s2Matches = new Array(s2.length).fill(false);
        let matches = 0, transpositions = 0;

        // Find matches
        for (let i = 0; i < s1.length; i++) {
            const start = Math.max(0, i - matchWindow);
            const end = Math.min(i + matchWindow + 1, s2.length);
            for (let j = start; j < end; j++) {
                if (s2Matches[j] || s1[i] !== s2[j]) continue;
                s1Matches[i] = s2Matches[j] = true;
                matches++;
                break;
            }
        }

        if (!matches) return 0;

        // Count transpositions
        let k = 0;
        for (let i = 0; i < s1.length; i++) {
            if (!s1Matches[i]) continue;
            while (!s2Matches[k]) k++;
            if (s1[i] !== s2[k]) transpositions++;
            k++;
        }

        // Jaro similarity
        const jaro = (matches / s1.length + matches / s2.length + (matches - transpositions / 2) / matches) / 3;

        // Winkler modification - boost for common prefix (up to 4 chars)
        let prefix = 0;
        for (let i = 0; i < Math.min(4, s1.length, s2.length); i++) {
            if (s1[i] === s2[i]) prefix++;
            else break;
        }

        return jaro + prefix * 0.1 * (1 - jaro);
    },

    /**
     * Double Metaphone - Enhanced phonetic encoding
     * Handles pronunciation variations (Smyth/Smith, Jon/John)
     */
    doubleMetaphone(str) {
        str = str.toUpperCase().replace(/[^A-Z]/g, '');
        if (!str) return ['', ''];

        // Simplified double metaphone - returns [primary, secondary] codes
        let primary = '', secondary = '';

        // Common phonetic transformations
        const transforms = [
            [/^GN|^KN|^PN|^WR|^PS/, ''],
            [/^X/, 'S'],
            [/^WH/, 'W'],
            [/MB$/, 'M'],
            [/GH/, ''],
            [/PH/, 'F'],
            [/SH/, 'X'],
            [/TH/, '0'],
            [/CH/, 'X'],
            [/CK/, 'K'],
            [/TCH/, 'X'],
            [/C([IEY])/, 'S$1'],
            [/C/, 'K'],
            [/DG/, 'J'],
            [/D/, 'T'],
            [/GN/, 'N'],
            [/G([IEY])/, 'J$1'],
            [/G/, 'K'],
            [/Q/, 'K'],
            [/[SZ]/, 'S'],
            [/V/, 'F'],
            [/W(?=[AEIOU])/, 'W'],
            [/W/, ''],
            [/X/, 'KS'],
            [/Y(?=[AEIOU])/, 'Y'],
            [/Y/, ''],
            [/[AEIOU]/, 'A']
        ];

        let working = str;
        for (const [pattern, replacement] of transforms) {
            working = working.replace(pattern, replacement);
        }

        primary = working.replace(/[^A-Z0]/g, '').slice(0, 4);

        // Secondary code handles alternate pronunciations
        secondary = str.replace(/PH/g, 'F').replace(/[AEIOU]/g, '').slice(0, 4);

        return [primary, secondary];
    },

    /**
     * Hamming Distance - For same-length strings (phones)
     * Very fast for comparing normalized phone numbers
     */
    hammingDistance(s1, s2) {
        // Normalize to same length by padding shorter with spaces
        const maxLen = Math.max(s1.length, s2.length);
        s1 = s1.padEnd(maxLen);
        s2 = s2.padEnd(maxLen);

        let distance = 0;
        for (let i = 0; i < maxLen; i++) {
            if (s1[i] !== s2[i]) distance++;
        }
        return distance;
    },

    /**
     * Trigram Similarity - Best for organization names
     * Handles word order variations ("Apple Inc" ≈ "Inc Apple")
     */
    trigramSimilarity(s1, s2) {
        return this.ngramSimilarity(s1, s2, 3);
    },

    wordSimilarity(w1, w2) {
        w1 = w1.toLowerCase(); w2 = w2.toLowerCase();
        const weights = this.config.weights;
        if (w1 === w2) return weights.exact;
        if (w2.startsWith(w1) || w1.startsWith(w2)) return weights.startsWith;
        if (w2.includes(w1) || w1.includes(w2)) return weights.contains;
        const scores = [];
        if (this.soundex(w1) === this.soundex(w2)) scores.push(weights.soundex);
        if (this.metaphone(w1) === this.metaphone(w2)) scores.push(weights.metaphone);
        const dist = this.levenshtein(w1, w2), maxLen = Math.max(w1.length, w2.length);
        if (dist <= this.config.maxLevenshteinDistance) scores.push((1 - dist / maxLen) * weights.levenshtein);
        const ng = this.ngramSimilarity(w1, w2);
        if (ng > 0.3) scores.push(ng * weights.ngram);
        return scores.length ? Math.max(...scores) : 0;
    },

    /**
     * Get fuzzy score combining Jaro-Winkler + Double Metaphone
     * Used for individual first/last name comparison
     * @returns {number} Score from 0 to 1
     */
    getFuzzyScore(searchWord, recordWord) {
        if (!searchWord || !recordWord) return 0;

        // Exact match
        if (searchWord === recordWord) return 1.0;

        // Jaro-Winkler base score
        let score = this.jaroWinkler(searchWord, recordWord);

        // Double Metaphone bonus (+0.10 for phonetic match)
        const searchMeta = this.doubleMetaphone(searchWord);
        const recordMeta = this.doubleMetaphone(recordWord);
        if (searchMeta[0] === recordMeta[0] || searchMeta[1] === recordMeta[1]) {
            score = Math.min(1.0, score + 0.10);
        }

        return score;
    },

    /**
     * Enhanced search with multi-word fuzzy matching
     * Handles: "Jon Doe" → "John Doe", partial names, and typos
     */
    search(records, searchTerm, fields) {
        searchTerm = searchTerm.toLowerCase().trim();
        if (searchTerm.length < 2) return [];

        const searchWords = searchTerm.split(/\s+/).filter(w => w.length >= 1);
        const results = [];

        for (const record of records) {
            let maxScore = 0, matchedField = '';

            for (const field of fields) {
                const value = record[field];
                if (!value) continue;

                const valueLower = value.toLowerCase();
                const valueWords = valueLower.split(/\s+/);
                let fieldScore = 0;

                // Exact match
                if (valueLower === searchTerm) {
                    fieldScore = this.config.weights.exact;
                }
                // Starts with
                else if (valueLower.startsWith(searchTerm)) {
                    fieldScore = this.config.weights.startsWith;
                }
                // Contains
                else if (valueLower.includes(searchTerm)) {
                    fieldScore = this.config.weights.contains;
                }
                // Multi-word fuzzy matching (for queries like "Jon Doe" → "John Doe")
                else if (searchWords.length > 1) {
                    // Calculate combined score for multi-word queries
                    let totalWordScore = 0;
                    let matchedWords = 0;

                    for (const sw of searchWords) {
                        let bestWordMatch = 0;
                        for (const vw of valueWords) {
                            const similarity = this.wordSimilarity(sw, vw);
                            if (similarity > bestWordMatch) {
                                bestWordMatch = similarity;
                            }
                        }
                        if (bestWordMatch >= 0.3) {
                            totalWordScore += bestWordMatch;
                            matchedWords++;
                        }
                    }

                    // Score based on how many search words matched
                    if (matchedWords > 0) {
                        fieldScore = (totalWordScore / searchWords.length) * (matchedWords / searchWords.length);
                        // Bonus for matching all words
                        if (matchedWords === searchWords.length) {
                            fieldScore = Math.min(1, fieldScore * 1.2);
                        }
                    }
                }
                // Single word fuzzy matching
                else {
                    for (const vw of valueWords) {
                        const similarity = this.wordSimilarity(searchWords[0], vw);
                        fieldScore = Math.max(fieldScore, similarity);
                    }
                }

                if (fieldScore > maxScore) {
                    maxScore = fieldScore;
                    matchedField = field;
                }
            }

            if (maxScore >= this.config.minSimilarityThreshold) {
                results.push({
                    ...record,
                    _matchScore: Math.round(maxScore * 100),
                    _matchedField: matchedField
                });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    /**
     * Specialized NIC/Passport matching - substring anywhere
     * Matches if entered digits appear ANYWHERE in the NIC
     */
    matchNIC(records, searchTerm) {
        // Normalize: remove spaces and make uppercase
        searchTerm = searchTerm.toUpperCase().replace(/\s/g, '');
        if (searchTerm.length < 2) return [];

        const results = [];
        for (const record of records) {
            const nic = record.nic;
            if (!nic) continue;

            const normalized = nic.toUpperCase().replace(/\s/g, '');
            let score = 0;

            // Exact match
            if (normalized === searchTerm) {
                score = 100;
            }
            // Ends with (last digits match - most common use case)
            else if (normalized.endsWith(searchTerm)) {
                score = 95;
            }
            // Starts with
            else if (normalized.startsWith(searchTerm)) {
                score = 90;
            }
            // Contains anywhere
            else if (normalized.includes(searchTerm)) {
                score = 85;
            }
            // Allow 1 character difference (typo tolerance)
            else if (searchTerm.length >= 4) {
                const dist = this.levenshtein(normalized.slice(-searchTerm.length), searchTerm);
                if (dist <= 1) {
                    score = 75;
                }
            }

            if (score > 0) {
                results.push({ ...record, _matchScore: score, _matchedField: 'nic' });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    /**
     * Specialized Phone matching - suffix match (last 4-6 digits)
     * Most useful for partial phone entry
     */
    matchPhone(records, searchTerm) {
        // Normalize: extract only digits
        const searchDigits = searchTerm.replace(/\D/g, '');
        if (searchDigits.length < 3) return [];

        const results = [];
        for (const record of records) {
            const phone = record.phone;
            if (!phone) continue;

            const phoneDigits = phone.replace(/\D/g, '');
            let score = 0;
            let matchType = 'none';

            // ========================================
            // PRIORITY 1: Exact match (100%)
            // ========================================
            if (phoneDigits === searchDigits) {
                score = 100;
                matchType = 'exact';
            }
            // ========================================
            // PRIORITY 2: Suffix match - last N digits (95%)
            // ========================================
            else if (phoneDigits.endsWith(searchDigits)) {
                score = 95;
                matchType = 'suffix';
            }
            // ========================================
            // PRIORITY 3: Prefix match - area code (90%)
            // ========================================
            else if (phoneDigits.startsWith(searchDigits)) {
                score = 90;
                matchType = 'prefix';
            }
            // ========================================
            // PRIORITY 4: Contains anywhere (85%)
            // ========================================
            else if (phoneDigits.includes(searchDigits)) {
                score = 85;
                matchType = 'contains';
            }
            // ========================================
            // PRIORITY 5: Levenshtein typo tolerance (≤80%)
            // ========================================
            else if (searchDigits.length >= 4) {
                // Compare against suffix of same length
                const suffixLen = searchDigits.length;
                const phoneSuffix = phoneDigits.slice(-suffixLen);
                const distance = this.levenshtein(phoneSuffix, searchDigits);
                const maxLen = Math.max(phoneSuffix.length, searchDigits.length);

                if (distance <= 2 && maxLen > 0) {
                    // Score based on similarity, capped at 80%
                    const similarity = 1 - (distance / maxLen);
                    score = Math.min(80, Math.round(similarity * 80));
                    matchType = 'levenshtein';
                }
            }

            if (score > 0) {
                results.push({
                    ...record,
                    _matchScore: score,
                    _matchType: matchType,
                    _matchedField: 'phone',
                    _algorithm: matchType === 'levenshtein' ? 'levenshtein' : 'exact'
                });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    /**
     * Partial field search for NIC/Phone/Email
     * Allows searching with last N digits
     */
    searchPartial(records, searchTerm, field) {
        searchTerm = searchTerm.toLowerCase().replace(/\s/g, '');
        if (searchTerm.length < 3) return [];

        const results = [];
        for (const record of records) {
            const value = record[field];
            if (!value) continue;

            const normalized = value.toLowerCase().replace(/\s/g, '');

            // Check if ends with (last digits match)
            if (normalized.endsWith(searchTerm)) {
                results.push({ ...record, _matchScore: 95, _matchedField: field });
            }
            // Check if contains
            else if (normalized.includes(searchTerm)) {
                results.push({ ...record, _matchScore: 80, _matchedField: field });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    // ============================================================================
    // FIELD-SPECIFIC MATCHERS (Using appropriate algorithms)
    // ============================================================================

    /**
     * Match Names using Jaro-Winkler + Double Metaphone
     * Threshold: 0.70 (more fuzzy for names)
     */
    matchName(records, searchTerm) {
        const normalized = this.normalize(searchTerm, 'name');
        if (normalized.length < 2) return [];

        const threshold = this.fieldConfig.name.threshold;
        const searchWords = normalized.split(/\s+/).filter(w => w.length >= 2);

        // For multi-word search (e.g., "Jon Doe"), separate into search first/last
        const searchFirstWord = searchWords[0] || '';
        const searchLastWord = searchWords.length > 1 ? searchWords[searchWords.length - 1] : '';

        const results = [];
        for (const record of records) {
            const firstName = record.firstName || '';
            const lastName = record.lastName || '';
            if (!firstName && !lastName) continue;

            // Normalize record's first and last names
            const firstNameNorm = this.normalize(firstName, 'name');
            const lastNameNorm = this.normalize(lastName, 'name');
            const fullNameNorm = `${firstNameNorm} ${lastNameNorm}`.trim();

            let score = 0;
            let matchType = 'none';

            // ========================================
            // PRIORITY ORDER FOR MULTI-WORD SEARCHES (e.g., "Jon Doe")
            // ========================================
            if (searchWords.length >= 2) {
                // Check first name match (exact vs fuzzy)
                const firstExact = firstNameNorm === searchFirstWord;
                const firstFuzzy = this.getFuzzyScore(searchFirstWord, firstNameNorm);

                // Check last name match (exact vs fuzzy)
                const lastExact = lastNameNorm === searchLastWord;
                const lastFuzzy = this.getFuzzyScore(searchLastWord, lastNameNorm);

                // PRIORITY 1: Both first name AND last name EXACTLY match (100%)
                if (firstExact && lastExact) {
                    score = 1.0;
                    matchType = 'exact-both';
                }
                // PRIORITY 2: First name EXACT + Last name FUZZY (95%)
                else if (firstExact && lastFuzzy >= 0.70) {
                    score = 0.95 + (lastFuzzy * 0.04); // 95-99% based on fuzzy quality
                    matchType = 'exact-first-fuzzy-last';
                }
                // PRIORITY 3: Last name EXACT + First name FUZZY (90%)
                else if (lastExact && firstFuzzy >= 0.70) {
                    score = 0.90 + (firstFuzzy * 0.04); // 90-94% based on fuzzy quality
                    matchType = 'fuzzy-first-exact-last';
                }
                // PRIORITY 4: Both FUZZY match (capped at 85%)
                else if (firstFuzzy >= 0.70 && lastFuzzy >= 0.70) {
                    const avgFuzzy = (firstFuzzy + lastFuzzy) / 2;
                    score = Math.min(0.85, avgFuzzy);
                    matchType = 'fuzzy-both';
                }
                // Single word match for multi-word search
                else if (firstFuzzy >= 0.70 || lastFuzzy >= 0.70) {
                    score = Math.max(firstFuzzy, lastFuzzy) * 0.80; // Penalize partial match
                    matchType = 'partial-fuzzy';
                }
            }
            // ========================================
            // SINGLE-WORD SEARCHES (e.g., "Jon")
            // ========================================
            else {
                const singleWord = searchWords[0] || normalized;

                // Check if single word matches first or last name exactly
                if (firstNameNorm === singleWord) {
                    score = 0.99; // Exact first name match
                    matchType = 'exact-firstname';
                }
                else if (lastNameNorm === singleWord) {
                    score = 0.98; // Exact last name match
                    matchType = 'exact-lastname';
                }
                // Fuzzy match against first or last name
                else {
                    const firstFuzzy = this.getFuzzyScore(singleWord, firstNameNorm);
                    const lastFuzzy = this.getFuzzyScore(singleWord, lastNameNorm);
                    const maxFuzzy = Math.max(firstFuzzy, lastFuzzy);

                    if (maxFuzzy >= 0.70) {
                        score = Math.min(0.89, maxFuzzy); // Cap fuzzy at 89%
                        matchType = firstFuzzy > lastFuzzy ? 'fuzzy-firstname' : 'fuzzy-lastname';
                    }
                }
            }

            // Only include results above threshold
            if (score >= threshold) {
                results.push({
                    ...record,
                    _matchScore: Math.round(score * 100),
                    _matchType: matchType,
                    _matchedField: 'name',
                    _algorithm: matchType.includes('exact') ? 'exact' : 'jaro-winkler+metaphone'
                });
            }
        }

        // Sort by score (highest first) - exact matches will always be on top
        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    /**
     * Match Email using N-Gram (Trigram) similarity
     * Catches domain typos like "gmial.com"
     * Threshold: 0.70
     */
    matchEmail(records, searchTerm) {
        const normalized = this.normalize(searchTerm, 'email');
        if (normalized.length < 3) return [];

        const threshold = this.fieldConfig.email.threshold;
        const results = [];

        for (const record of records) {
            const email = record.email;
            if (!email) continue;

            const emailNorm = this.normalize(email, 'email');
            let score = 0;

            // Exact match
            if (emailNorm === normalized) {
                score = 1.0;
            }
            // Contains (partial match)
            else if (emailNorm.includes(normalized) || normalized.includes(emailNorm)) {
                score = 0.85;
            }
            // Trigram similarity (catches typos like gmial.com)
            else {
                score = this.ngramSimilarity(normalized, emailNorm, 3);
            }

            if (score >= threshold) {
                results.push({
                    ...record,
                    _matchScore: Math.round(score * 100),
                    _matchedField: 'email',
                    _algorithm: 'ngram-trigram'
                });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    /**
     * Match Organization Name using Trigram similarity
     * Handles word order variations ("Apple Inc" ≈ "Inc Apple")
     * Threshold: 0.70
     */
    matchOrgName(records, searchTerm) {
        const normalized = this.normalize(searchTerm, 'name');
        if (normalized.length < 2) return [];

        const threshold = this.fieldConfig.orgName.threshold;
        const results = [];

        for (const record of records) {
            const orgName = record.name || record.orgName || record.tradingName;
            if (!orgName) continue;

            const orgNorm = orgName.toLowerCase();
            let score = 0;

            // Exact match
            if (orgNorm === normalized) {
                score = 1.0;
            }
            // Contains
            else if (orgNorm.includes(normalized)) {
                score = 0.90;
            }
            // Starts with
            else if (orgNorm.startsWith(normalized)) {
                score = 0.85;
            }
            // Trigram similarity (word order independent)
            else {
                score = this.trigramSimilarity(normalized, orgNorm);
            }

            if (score >= threshold) {
                results.push({
                    ...record,
                    _matchScore: Math.round(score * 100),
                    _matchedField: 'orgName',
                    _algorithm: 'trigram'
                });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    },

    /**
     * Match BR Number using Levenshtein with strict threshold
     * Threshold: 0.85 (very strict for business registration numbers)
     */
    matchBRNumber(records, searchTerm) {
        const normalized = this.normalize(searchTerm, 'brNumber');
        if (normalized.length < 3) return [];

        const threshold = this.fieldConfig.brNumber.threshold;
        const results = [];

        for (const record of records) {
            const brNum = record.brNumber;
            if (!brNum) continue;

            const brNorm = this.normalize(brNum, 'brNumber');
            let score = 0;

            // Exact match
            if (brNorm === normalized) {
                score = 1.0;
            }
            // Ends with (last digits match)
            else if (brNorm.endsWith(normalized)) {
                score = 0.95;
            }
            // Starts with
            else if (brNorm.startsWith(normalized)) {
                score = 0.90;
            }
            // Contains
            else if (brNorm.includes(normalized)) {
                score = 0.85;
            }
            // Levenshtein distance (strict k=2)
            else {
                const dist = this.levenshtein(normalized, brNorm);
                if (dist <= 2) {
                    const maxLen = Math.max(normalized.length, brNorm.length);
                    score = 1 - (dist / maxLen);
                }
            }

            if (score >= threshold) {
                results.push({
                    ...record,
                    _matchScore: Math.round(score * 100),
                    _matchedField: 'brNumber',
                    _algorithm: 'levenshtein'
                });
            }
        }

        return results.sort((a, b) => b._matchScore - a._matchScore);
    }
};

// ============================================================================
// DATA MASKING
// ============================================================================

const DataMasking = {
    maskEmail(email) {
        if (!email) return '';
        const [local, domain] = email.split('@');
        if (!domain) return '***@***.***';
        const m = local.length <= 2 ? local : local.length <= 4 ? local[0] + '*'.repeat(local.length - 1) : local.slice(0, 2) + '*'.repeat(local.length - 2);
        return m + '@' + domain;
    },
    maskNIC(nic) {
        if (!nic) return '';
        nic = nic.toUpperCase().replace(/\s/g, '');
        if (/^\d{9}[VX]$/.test(nic)) return nic.slice(0, 3) + '****' + nic.slice(7);
        if (/^\d{12}$/.test(nic)) return nic.slice(0, 4) + '****' + nic.slice(8);
        return nic.slice(0, 3) + '****' + nic.slice(-3);
    },
    maskPhone(phone) {
        if (!phone) return '';
        const digits = phone.replace(/\D/g, '');
        return digits.length <= 4 ? phone : '****** ' + digits.slice(-4);
    },
    maskName(name) {
        if (!name) return '';
        name = name.trim();
        if (name.length <= 2) return name[0] + '*';
        if (name.length <= 4) return name[0] + '*'.repeat(name.length - 2) + name.slice(-1);
        return name.slice(0, 2) + '*'.repeat(name.length - 4) + name.slice(-2);
    },
    maskBRNumber(br) {
        if (!br) return '';
        br = br.toUpperCase().replace(/\s/g, '');
        if (/^([A-Z]{2})(\d+)$/.test(br)) {
            const m = br.match(/^([A-Z]{2})(\d+)$/);
            return m[1] + '*'.repeat(Math.max(0, m[2].length - 3)) + m[2].slice(-3);
        }
        return '*'.repeat(Math.max(0, br.length - 3)) + br.slice(-3);
    }
};

// ============================================================================
// NAME DATA - Extensive lists for diversity
// ============================================================================

const sinhalaFirstNames = [
    'Amal', 'Nimal', 'Kamal', 'Sunil', 'Saman', 'Chaminda', 'Ruwan', 'Thilak', 'Nuwan', 'Dilshan',
    'Kasun', 'Lasith', 'Mahela', 'Kumar', 'Sanath', 'Arjuna', 'Prasanna', 'Dinesh', 'Chathura', 'Asanka',
    'Buddhika', 'Chandana', 'Dhananjaya', 'Eranga', 'Gayan', 'Hasitha', 'Isuru', 'Janaka', 'Kelum', 'Lahiru',
    'Malinda', 'Nalin', 'Upul', 'Pradeep', 'Roshan', 'Sampath', 'Tharindu', 'Udara', 'Vimukthi', 'Wasantha',
    'Sachini', 'Nimali', 'Kumari', 'Sanduni', 'Hashini', 'Malini', 'Nilmini', 'Chamari', 'Ishani', 'Nadeesha',
    'Gayani', 'Ruwanthi', 'Krishani', 'Tharushi', 'Nethmi', 'Hiruni', 'Sewwandi', 'Madhavi', 'Dilini', 'Anusha',
    'Bhagya', 'Chanudi', 'Damayanthi', 'Erandathi', 'Fathima', 'Gimhani', 'Harini', 'Iresha', 'Jayani', 'Kavindi'
];

const tamilFirstNames = [
    'Arun', 'Bala', 'Chandran', 'Deva', 'Elan', 'Ganesh', 'Hari', 'Ilango', 'Jegan', 'Karthik',
    'Lakshman', 'Mohan', 'Naren', 'Prakash', 'Rajan', 'Selvam', 'Thiru', 'Varun', 'Vijay', 'Yogan',
    'Anitha', 'Bhavani', 'Chithra', 'Deepa', 'Eswari', 'Geetha', 'Hema', 'Indira', 'Janani', 'Kavitha',
    'Lakshmi', 'Meena', 'Nirmala', 'Padma', 'Radha', 'Saroja', 'Thangam', 'Uma', 'Vasuki', 'Yamuna'
];

const muslimFirstNames = [
    'Abdul', 'Ahmed', 'Ali', 'Faiz', 'Hameed', 'Ibrahim', 'Jamal', 'Karim', 'Mohamed', 'Nazar',
    'Omar', 'Rasheed', 'Saleem', 'Tariq', 'Yusuf', 'Zahir', 'Aisha', 'Fathima', 'Hafsa', 'Khadija',
    'Mariam', 'Nadia', 'Ruqaiya', 'Safiya', 'Zainab', 'Amina', 'Bilqis', 'Halima', 'Jamila', 'Layla'
];

const internationalFirstNames = [
    'John', 'Michael', 'David', 'James', 'Robert', 'William', 'Richard', 'Thomas', 'Charles', 'Daniel',
    'Matthew', 'Anthony', 'Mark', 'Steven', 'Paul', 'Andrew', 'Joshua', 'Kenneth', 'Kevin', 'Brian',
    'Sarah', 'Emily', 'Jessica', 'Jennifer', 'Michelle', 'Amanda', 'Stephanie', 'Nicole', 'Elizabeth', 'Rebecca',
    'Laura', 'Rachel', 'Hannah', 'Ashley', 'Katherine', 'Megan', 'Samantha', 'Alexandra', 'Victoria', 'Natalie'
];

const sinhalaLastNames = [
    'Perera', 'Fernando', 'Silva', 'De Silva', 'Jayawardena', 'Bandara', 'Wijesinghe', 'Dissanayake',
    'Gunasekara', 'Ranasinghe', 'Wickramasinghe', 'Senanayake', 'Rajapaksa', 'Karunaratne', 'Samaraweera',
    'Herath', 'Mendis', 'Pathirana', 'Ekanayake', 'Gunawardena', 'Jayasuriya', 'Kulatunga', 'Weerasinghe',
    'Fonseka', 'Amarasinghe', 'Balasuriya', 'Cooray', 'Daluwatta', 'Edirisinghe', 'Gamage', 'Gunatilake',
    'Hapuarachchi', 'Ileperuma', 'Jayakody', 'Kodagoda', 'Liyanage', 'Mudalige', 'Nanayakkara', 'Obeysekere',
    'Peiris', 'Ratnayake', 'Senaratne', 'Tennakoon', 'Udugama', 'Vithanage', 'Weerakoon', 'Yapa', 'Zoysa'
];

const tamilLastNames = [
    'Krishnan', 'Raman', 'Subramaniam', 'Pillai', 'Nair', 'Iyer', 'Sharma', 'Patel', 'Murugan', 'Selvakumar',
    'Rajagopal', 'Venkatesh', 'Natarajan', 'Sundaram', 'Govindan', 'Balakrishnan', 'Thiruchelvam', 'Kandiah',
    'Sivakumar', 'Chandrasekaran', 'Arumugam', 'Balachandran', 'Chelvanayagam', 'Devanathan', 'Easwaran'
];

const muslimLastNames = [
    'Hassim', 'Ismail', 'Khan', 'Mohideen', 'Nazeer', 'Rahman', 'Salih', 'Thaha', 'Wahab', 'Yoosuf',
    'Akbar', 'Bawa', 'Cassim', 'Deen', 'Farook', 'Ghouse', 'Hanifa', 'Jameel', 'Kaleel', 'Lebbe'
];

const internationalLastNames = [
    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Anderson', 'Wilson', 'Taylor',
    'Thomas', 'Moore', 'Jackson', 'Martin', 'Lee', 'Thompson', 'White', 'Harris', 'Clark', 'Lewis',
    'Robinson', 'Walker', 'Hall', 'Young', 'King', 'Wright', 'Scott', 'Green', 'Baker', 'Adams'
];

// Combine all names
const allFirstNames = [...sinhalaFirstNames, ...tamilFirstNames, ...muslimFirstNames, ...internationalFirstNames];
const allLastNames = [...sinhalaLastNames, ...tamilLastNames, ...muslimLastNames, ...internationalLastNames];

// ============================================================================
// ORGANIZATION DATA
// ============================================================================

const orgPrefixes = [
    'Lanka', 'Ceylon', 'Colombo', 'Island', 'Sri', 'Kandy', 'Galle', 'Pearl', 'Tropical', 'Serendib',
    'Blue Ocean', 'Green Valley', 'Mountain Peak', 'Sunny Side', 'Golden Star', 'Silver Moon', 'Crystal',
    'Diamond', 'Platinum', 'Metro', 'Urban', 'City', 'Global', 'Premier', 'Elite', 'Royal', 'Prime',
    'National', 'Central', 'Pacific', 'Atlantic', 'Northern', 'Southern', 'Eastern', 'Western', 'United',
    'Allied', 'Alpha', 'Beta', 'Delta', 'Omega', 'Apex', 'Summit', 'Horizon', 'Infinity', 'Quantum'
];

const orgMiddle = [
    'Tech', 'Digital', 'Software', 'Solutions', 'Systems', 'Data', 'Computing', 'Web', 'IT', 'Cloud',
    'Mobile', 'Smart', 'Cyber', 'Net', 'Info', 'Logic', 'Code', 'Dev', 'App', 'Innovation',
    'Trade', 'Commerce', 'Business', 'Enterprise', 'Corporate', 'Finance', 'Capital', 'Invest', 'Wealth',
    'Construction', 'Engineering', 'Manufacturing', 'Industrial', 'Agri', 'Food', 'Pharma', 'Medical', 'Health',
    'Education', 'Learning', 'Training', 'Consulting', 'Advisory', 'Marketing', 'Media', 'Creative', 'Design'
];

const orgSuffixes = ['(Pvt) Ltd', 'PLC', 'Holdings', 'Group', 'Enterprises', 'Corporation', 'Co.', 'Inc.', 'Ltd', 'Limited'];
const orgTypes = ['Private Limited', 'Public Limited', 'Partnership', 'Sole Proprietor', 'Government', 'NGO', 'Cooperative'];
const locations = [
    'Colombo', 'Kandy', 'Galle', 'Jaffna', 'Negombo', 'Matara', 'Kurunegala', 'Anuradhapura', 'Ratnapura',
    'Badulla', 'Trincomalee', 'Batticaloa', 'Vavuniya', 'Kilinochchi', 'Mannar', 'Mullaitivu', 'Ampara',
    'Polonnaruwa', 'Monaragala', 'Hambantota', 'Kegalle', 'Kalutara', 'Gampaha', 'Puttalam', 'Nuwara Eliya'
];

const emailDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'email.lk', 'slt.lk', 'dialog.lk', 'work.lk', 'company.lk', 'corp.com'];

// ============================================================================
// DATA GENERATION FUNCTIONS
// ============================================================================

function randomElement(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
function randomInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

function generateNIC() {
    const isNew = Math.random() > 0.4;
    const year = randomInt(1950, 2005);
    const days = randomInt(1, 365);
    const serial = randomInt(1000, 9999);
    if (isNew) return `${year}${String(days).padStart(3, '0')}${serial}${randomInt(0, 9)}`;
    return `${String(year).slice(2)}${String(days).padStart(3, '0')}${serial}${Math.random() > 0.5 ? 'V' : 'X'}`;
}

function generatePhone() {
    const prefixes = ['071', '072', '076', '077', '078', '070', '074', '075'];
    return randomElement(prefixes) + String(randomInt(1000000, 9999999));
}

function generateBRNumber() {
    const prefixes = ['PV', 'PB', 'GA', 'CS', ''];
    return randomElement(prefixes) + randomInt(10000000, 99999999);
}

function generateEmail(firstName, lastName, index) {
    const formats = [
        `${firstName.toLowerCase()}.${lastName.toLowerCase().replace(/\s/g, '')}${index}`,
        `${firstName.toLowerCase()}${lastName[0].toLowerCase()}${index}`,
        `${firstName[0].toLowerCase()}${lastName.toLowerCase().replace(/\s/g, '')}${index}`,
        `${firstName.toLowerCase()}${randomInt(1, 999)}`,
        `${lastName.toLowerCase().replace(/\s/g, '')}.${firstName.toLowerCase()}`
    ];
    return randomElement(formats) + '@' + randomElement(emailDomains);
}

function generateCustomerRef(index) {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const suffix = letters[randomInt(0, 25)] + letters[randomInt(0, 25)] + letters[randomInt(0, 25)];
    return `LKC-${100000 + index}-${suffix}`;
}

// ============================================================================
// GENERATE 10,000 CUSTOMERS
// ============================================================================

console.time('Generating customers');
const mockCustomers = [];

for (let i = 1; i <= 10000; i++) {
    const firstName = randomElement(allFirstNames);
    const lastName = randomElement(allLastNames);

    mockCustomers.push({
        id: i,
        firstName: firstName,
        lastName: lastName,
        fullName: `${firstName} ${lastName}`,
        email: generateEmail(firstName, lastName, i),
        nic: generateNIC(),
        phone: generatePhone(),
        location: randomElement(locations) + ', Sri Lanka',
        isVerified: Math.random() > 0.15,
        customerReference: generateCustomerRef(i)
    });
}

// Add specific fuzzy test cases
const fuzzyTestCases = [
    { firstName: 'Jhon', lastName: 'Smith' },
    { firstName: 'John', lastName: 'Smyth' },
    { firstName: 'Jon', lastName: 'Smithe' },
    { firstName: 'Nimasha', lastName: 'Perera' },
    { firstName: 'Nimesha', lastName: 'Peiris' },
    { firstName: 'Chaminda', lastName: 'Wickremasinghe' },
    { firstName: 'Chaminda', lastName: 'Wikremasingha' },
    { firstName: 'Ranjith', lastName: 'Frenando' },
    { firstName: 'Ranjit', lastName: 'Fernando' },
    { firstName: 'Suresh', lastName: 'Silwa' },
    { firstName: 'Mahesh', lastName: 'Silvaa' },
    { firstName: 'Amila', lastName: 'Jayawardhana' },
    { firstName: 'Amitha', lastName: 'Jayawardena' },
    // Additional Perera variations for fuzzy testing
    { firstName: 'Kasun', lastName: 'Perera' },
    { firstName: 'Dilshan', lastName: 'Perara' },  // Typo version
    { firstName: 'Nuwan', lastName: 'Pererra' },   // Extra 'r' typo
    { firstName: 'Tharindu', lastName: 'Pereira' }, // Different spelling
    // Additional John variations
    { firstName: 'John', lastName: 'Doe' },
    { firstName: 'Jon', lastName: 'Doe' },
    { firstName: 'Jhon', lastName: 'Silva' },
    { firstName: 'Johan', lastName: 'Fernando' }
];

fuzzyTestCases.forEach((tc, idx) => {
    const i = 10001 + idx;
    mockCustomers.push({
        id: i,
        firstName: tc.firstName,
        lastName: tc.lastName,
        fullName: `${tc.firstName} ${tc.lastName}`,
        email: generateEmail(tc.firstName, tc.lastName, i),
        nic: generateNIC(),
        phone: generatePhone(),
        location: randomElement(locations) + ', Sri Lanka',
        isVerified: true,
        customerReference: `LKC-${100000 + i}-TST`
    });
});

console.timeEnd('Generating customers');
console.log(`✅ Generated ${mockCustomers.length} customers`);

// ============================================================================
// GENERATE 1,000 ORGANIZATIONS
// ============================================================================

console.time('Generating organizations');
const mockOrganizations = [];

for (let i = 1; i <= 1000; i++) {
    const prefix = randomElement(orgPrefixes);
    const middle = randomElement(orgMiddle);
    const suffix = randomElement(orgSuffixes);
    const name = `${prefix} ${middle} ${suffix}`;

    mockOrganizations.push({
        id: i,
        name: name,
        tradingName: Math.random() > 0.6 ? `${prefix} ${middle}` : null,
        brNumber: generateBRNumber(),
        organizationType: randomElement(orgTypes),
        location: randomElement(locations) + ', Sri Lanka',
        isVerified: Math.random() > 0.1,
        isLinked: i <= 50 || Math.random() > 0.7  // First 50 always linked, then random
    });
}

console.timeEnd('Generating organizations');
console.log(`✅ Generated ${mockOrganizations.length} organizations`);

// ============================================================================
// SEARCH API
// ============================================================================

function searchCustomers(searchTerm, limit = 30) {
    const results = FuzzyMatcher.search(mockCustomers, searchTerm, ['firstName', 'lastName', 'fullName', 'email', 'nic', 'phone']);
    return results.slice(0, limit).map(c => ({
        id: c.id,
        matchScore: c._matchScore,
        displayName: c.fullName,
        maskedEmail: DataMasking.maskEmail(c.email),
        maskedNIC: null, // REMOVED for privacy
        maskedPhone: DataMasking.maskPhone(c.phone),
        location: c.location,
        matchContext: c._matchedField === 'nic' ? 'nic' : c._matchedField === 'email' ? 'email' : c._matchedField === 'phone' ? 'phone' : 'name',
        customerReference: c.customerReference,
        isVerified: c.isVerified,
        type: 'individual'
    }));
}

function searchOrganizations(searchTerm, customerId = 1, limit = 30) {
    const linkedOrgs = mockOrganizations.filter(o => o.isLinked);
    const results = FuzzyMatcher.search(linkedOrgs, searchTerm, ['name', 'tradingName', 'brNumber']);
    return results.slice(0, limit).map(o => ({
        id: o.id,
        matchScore: o._matchScore,
        displayName: o.name,
        maskedBRNumber: DataMasking.maskBRNumber(o.brNumber),
        fullBRNumber: o.isLinked ? o.brNumber : null,
        tradingName: o.tradingName,
        location: o.location,
        organizationType: o.organizationType,
        membershipRole: 'Admin, Billing Contact',
        canActAsBilling: true,
        canActAsAdmin: true,
        matchContext: o._matchedField === 'brNumber' ? 'br_number' : 'name',
        isVerified: o.isVerified,
        isLinked: o.isLinked,
        type: 'organization'
    }));
}

// ============================================================================
// EXPORT
// ============================================================================

window.MockAPI = {
    searchCustomers,
    searchOrganizations,
    FuzzyMatcher,
    DataMasking,
    mockCustomers,
    mockOrganizations
};

console.log('✅ Enhanced Fuzzy Search Mock API loaded');
console.log(`   📊 ${mockCustomers.length} customers (including ${fuzzyTestCases.length} fuzzy test cases)`);
console.log(`   🏢 ${mockOrganizations.length} organizations (${mockOrganizations.filter(o => o.isLinked).length} linked)`);
console.log('   🔬 Algorithms: Levenshtein, Soundex, Metaphone, N-gram');
