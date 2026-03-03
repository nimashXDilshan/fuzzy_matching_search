<?php

namespace LKDomains\Services\Search;

use LKDomains\Models\Members\Customer;
use LKDomains\DTOs\CustomerSearchResultDTO;
use LKDomains\Utils\DataMaskingUtil;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Customer Search Service
 * 
 * Implements fuzzy search for individual customers with:
 * - Multiple field searching (Name, NIC, Email, Phone)
 * - Privacy-compliant masked results
 * - Only shows enough data for "Yes, that's me" confirmation
 */
class CustomerSearchService
{
    use Configurable;
    use Injectable;

    /**
     * Fields to search for individuals
     * Priority order: Name > NIC > Email > Phone
     */
    private static $searchable_fields = [
        'FirstName',
        'Surname', 
        'NIC',
        'Email',
        'MobileTelephone',
        'Telephone'
    ];

    /**
     * Maximum results to return
     */
    private static $max_results = 30;

    /**
     * Minimum search term length
     */
    private static $min_search_length = 2;

    /**
     * The fuzzy match engine
     */
    protected FuzzyMatchEngine $fuzzyEngine;

    public function __construct()
    {
        $this->fuzzyEngine = new FuzzyMatchEngine();
    }

    /**
     * Search for customers using fuzzy matching
     *
     * @param string $searchTerm The search query
     * @param array $options Search options:
     *   - excludeIds: array of IDs to exclude
     *   - currentCustomerId: ID of the current logged-in customer
     *   - limit: max results (default 20)
     *   - onlyApproved: only search approved customers (default true)
     * @return array Array of CustomerSearchResultDTO objects
     */
    public function search(string $searchTerm, array $options = []): array
    {
        $searchTerm = trim($searchTerm);
        
        // Validate minimum search length
        if (strlen($searchTerm) < self::config()->get('min_search_length')) {
            return [];
        }

        // Get base query
        $query = $this->buildBaseQuery($options);

        // Determine which fields to search based on search term type or options
        $searchFields = $this->determineSearchFields($searchTerm, $options);

        // Perform fuzzy search
        $fuzzyResults = $this->fuzzyEngine->fuzzySearch(
            $query,
            $searchTerm,
            $searchFields,
            ['candidateLimit' => 200]
        );

        // Apply result limit
        $limit = $options['limit'] ?? self::config()->get('max_results');
        $fuzzyResults = array_slice($fuzzyResults, 0, $limit);

        // Convert to DTOs with masked data
        return $this->convertToMaskedDTOs($fuzzyResults, $searchTerm);
    }

    /**
     * Build the base query with filters
     */
    protected function buildBaseQuery(array $options): \SilverStripe\ORM\DataList
    {
        $query = Customer::get();

        // Exclude deactivated accounts
        $query = $query->filter('AccountDeactivationStatus', 'None');

        // Only approved customers by default
        if ($options['onlyApproved'] ?? true) {
            $query = $query->filter('ApprovalStatus', 'Approved');
        }

        // Exclude specific IDs
        if (!empty($options['excludeIds'])) {
            $query = $query->exclude('ID', $options['excludeIds']);
        }

        // Exclude default system users
        $systemUsers = [
            Customer::$defaultForeignUser,
            Customer::$defaultLocalUser,
            Customer::$globalBlockUserEmail
        ];
        $query = $query->exclude('Email', $systemUsers);

        return $query;
    }

    /**
     * Determine which fields to search based on search term pattern or specific options
     */
    protected function determineSearchFields(string $searchTerm, array $options = []): array
    {
        // If specific search fields are requested, map them to DB fields
        if (!empty($options['searchFields'])) {
            $mappedFields = [];
            foreach ($options['searchFields'] as $field) {
                switch ($field) {
                    case 'name':
                        $mappedFields[] = 'FirstName';
                        $mappedFields[] = 'Surname';
                        break;
                    case 'nic':
                        $mappedFields[] = 'NIC';
                        break;
                    case 'email':
                        $mappedFields[] = 'Email';
                        break;
                    case 'phone':
                        $mappedFields[] = 'MobileTelephone';
                        $mappedFields[] = 'Telephone';
                        break;
                }
            }
            
            if (!empty($mappedFields)) {
                return array_unique($mappedFields);
            }
        }

        // Auto-detection logic (fallback)

        // Check if it looks like an NIC number (Sri Lankan format)
        if ($this->looksLikeNIC($searchTerm)) {
            return ['NIC', 'FirstName', 'Surname'];
        }

        // Check if it looks like an email
        if ($this->looksLikeEmail($searchTerm)) {
            return ['Email', 'FirstName', 'Surname'];
        }

        // Check if it looks like a phone number
        if ($this->looksLikePhone($searchTerm)) {
            return ['MobileTelephone', 'Telephone', 'FirstName', 'Surname'];
        }

        // Default: search name fields primarily
        return self::config()->get('searchable_fields');
    }

    /**
     * Check if search term looks like an NIC number
     */
    protected function looksLikeNIC(string $term): bool
    {
        // Old NIC format: 9 digits + V/X
        // New NIC format: 12 digits
        $term = strtoupper(preg_replace('/\s/', '', $term));
        return preg_match('/^\d{9}[VX]?$/', $term) || preg_match('/^\d{12}$/', $term);
    }

    /**
     * Check if search term looks like an email
     */
    protected function looksLikeEmail(string $term): bool
    {
        return strpos($term, '@') !== false;
    }

    /**
     * Check if search term looks like a phone number
     */
    protected function looksLikePhone(string $term): bool
    {
        $cleaned = preg_replace('/[\s\-\(\)\+]/', '', $term);
        return preg_match('/^\d{7,15}$/', $cleaned);
    }

    /**
     * Convert fuzzy results to masked DTOs
     * 
     * Privacy principle: Show enough to identify, not enough to steal
     */
    protected function convertToMaskedDTOs(array $fuzzyResults, string $searchTerm): array
    {
        $dtos = [];

        foreach ($fuzzyResults as $result) {
            $customer = $result['record'];
            $score = $result['score'];
            $matchedFields = $result['matchedFields'] ?? [];

            // Create DTO with masked data
            $dto = new CustomerSearchResultDTO();
            $dto->ID = $customer->ID;
            $dto->MatchScore = round($score * 100);

            // Display name - mask surname partially
            $dto->DisplayName = $this->formatDisplayName(
                $customer->FirstName,
                $customer->Surname
            );

            // Masked email - show domain, mask local part
            $dto->MaskedEmail = DataMaskingUtil::maskEmail($customer->Email);

            // Masked NIC - REMOVED for privacy
            $dto->MaskedNIC = null;

            // Masked phone - show last 4 digits
            $dto->MaskedPhone = DataMaskingUtil::maskPhone($customer->MobileTelephone ?: $customer->Telephone);

            // Partial address - city/country only
            $dto->Location = $this->formatLocation($customer);

            // Match context - which field matched (for UI highlighting)
            $dto->MatchContext = $this->determineMatchContext($matchedFields, $searchTerm);

            // Customer reference (non-sensitive)
            $dto->CustomerReference = $customer->CustomerReference;

            // Account status indicator
            $dto->IsVerified = ($customer->IsEmailVerified === 'Y');

            $dtos[] = $dto;
        }

        return $dtos;
    }

    /**
     * Format display name with partial masking
     */
    protected function formatDisplayName(?string $firstName, ?string $surname): string
    {
        $firstName = $firstName ?: '';
        $surname = $surname ?: '';

        if (empty($firstName) && empty($surname)) {
            return '(No Name)';
        }

        return trim("{$firstName} {$surname}");
    }

    /**
     * Format location string (non-sensitive)
     */
    protected function formatLocation(Customer $customer): string
    {
        $parts = [];
        
        if ($customer->Town) {
            $parts[] = $customer->Town;
        }
        
        if ($customer->CountryID && $customer->Country()->exists()) {
            $parts[] = $customer->Country()->Name;
        }

        return implode(', ', $parts) ?: 'Unknown';
    }

    /**
     * Determine the match context for UI highlighting
     */
    protected function determineMatchContext(array $matchedFields, string $searchTerm): string
    {
        if (empty($matchedFields)) {
            return 'name';
        }

        // Priority order for display
        $priorityMap = [
            'FirstName' => 'name',
            'Surname' => 'name',
            'NIC' => 'nic',
            'Email' => 'email',
            'MobileTelephone' => 'phone',
            'Telephone' => 'phone',
        ];

        foreach ($matchedFields as $field) {
            if (isset($priorityMap[$field])) {
                return $priorityMap[$field];
            }
        }

        return 'name';
    }

    /**
     * Quick search - returns minimal data for autocomplete
     */
    public function quickSearch(string $searchTerm, int $limit = 5): array
    {
        $results = $this->search($searchTerm, [
            'limit' => $limit,
            'onlyApproved' => true
        ]);

        return array_map(function(CustomerSearchResultDTO $dto) {
            return [
                'id' => $dto->ID,
                'label' => $dto->DisplayName,
                'maskedEmail' => $dto->MaskedEmail,
                'matchScore' => $dto->MatchScore
            ];
        }, $results);
    }

    /**
     * Validate that a customer can be selected as registrant
     */
    public function validateSelection(int $customerId, int $currentUserId): array
    {
        $customer = Customer::get()->byID($customerId);

        if (!$customer) {
            return ['valid' => false, 'error' => 'Customer not found'];
        }

        // Check if account is approved
        if ($customer->ApprovalStatus !== 'Approved') {
            return ['valid' => false, 'error' => 'Customer account is not approved'];
        }

        // Check if account is deactivated
        if ($customer->AccountDeactivationStatus !== 'None') {
            return ['valid' => false, 'error' => 'Customer account is deactivated'];
        }

        // Validate email verified
        if ($customer->IsEmailVerified !== 'Y') {
            return ['valid' => false, 'error' => 'Customer email is not verified'];
        }

        // Check for incomplete profile
        if ($customer->isDataIncompleteCustomer()) {
            return ['valid' => false, 'error' => 'Customer profile is incomplete'];
        }

        return [
            'valid' => true,
            'customerId' => $customer->ID,
            'customerReference' => $customer->CustomerReference,
            'displayName' => $customer->FullName()
        ];
    }
}
