<?php

namespace LKDomains\Services\Search;

use LKDomains\Models\Members\Customer;
use LKDomains\Models\Members\Organization;
use LKDomains\Models\Members\OrganizationMember;
use LKDomains\DTOs\OrganizationSearchResultDTO;
use LKDomains\Utils\DataMaskingUtil;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Organization Search Service
 * 
 * Implements fuzzy search for organizations with:
 * - Multiple field searching (Name, BR Number)
 * - Access control: Only shows organizations linked to user account
 * - Privacy-compliant masked results
 */
class OrganizationSearchService
{
    use Configurable;
    use Injectable;

    /**
     * Fields to search for organizations
     */
    private static $searchable_fields = [
        'Name',
        'RegistrationNumber',  // BR Number
        'TradingName'
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
     * Search for organizations using fuzzy matching
     * 
     * @param string $searchTerm The search query
     * @param int|null $customerId Current customer ID for access filtering
     * @param array $options Search options:
     *   - showAllOrganizations: bool, if true shows all orgs (admin mode)
     *   - onlyApproved: bool, only show approved orgs
     *   - limit: max results
     * @return array Array of OrganizationSearchResultDTO objects
     */
    public function search(string $searchTerm, ?int $customerId = null, array $options = []): array
    {
        $searchTerm = trim($searchTerm);
        
        // Validate minimum search length
        if (strlen($searchTerm) < self::config()->get('min_search_length')) {
            return [];
        }

        // Get base query with access control
        $query = $this->buildBaseQuery($customerId, $options);

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

        // Convert to DTOs with appropriate masking
        return $this->convertToMaskedDTOs($fuzzyResults, $customerId, $searchTerm);
    }

    /**
     * Build the base query with access control
     * 
     * Key security feature: Users can only see organizations they're linked to
     */
    protected function buildBaseQuery(?int $customerId, array $options): \SilverStripe\ORM\DataList
    {
        $query = Organization::get();

        // Only approved organizations by default
        if ($options['onlyApproved'] ?? true) {
            $query = $query->filter('IsApproved', 'Approved');
        }

        // Apply access control - only show organizations linked to this customer
        // UNLESS admin mode is enabled
        if (!($options['showAllOrganizations'] ?? false)) {
            if ($customerId) {
                // Get organization IDs where this customer is a member
                $linkedOrgIds = $this->getLinkedOrganizationIds($customerId);
                
                if (empty($linkedOrgIds)) {
                    // User is not linked to any organizations
                    // Return empty result set
                    $query = $query->filter('ID', 0);
                } else {
                    $query = $query->filter('ID', $linkedOrgIds);
                }
            } else {
                // No customer ID provided - return empty for security
                $query = $query->filter('ID', 0);
            }
        }

        return $query;
    }

    /**
     * Get IDs of organizations linked to a customer
     */
    protected function getLinkedOrganizationIds(int $customerId): array
    {
        return OrganizationMember::get()
            ->filter('CustomerID', $customerId)
            ->column('OrganizationID');
    }

    /**
     * Determine which fields to search based on search term pattern or options
     */
    protected function determineSearchFields(string $searchTerm, array $options = []): array
    {
        // If specific search fields are requested, map them to DB fields
        if (!empty($options['searchFields'])) {
            $mappedFields = [];
            foreach ($options['searchFields'] as $field) {
                switch ($field) {
                    case 'name':
                        $mappedFields[] = 'Name';
                        $mappedFields[] = 'TradingName';
                        break;
                    case 'brNumber':
                        $mappedFields[] = 'RegistrationNumber';
                        break;
                }
            }
            
            if (!empty($mappedFields)) {
                return array_unique($mappedFields);
            }
        }

        // Auto-detection logic (fallback)

        // Check if it looks like a BR number
        if ($this->looksLikeBRNumber($searchTerm)) {
            return ['RegistrationNumber', 'Name'];
        }

        // Default: search all fields with name priority
        return self::config()->get('searchable_fields');
    }

    /**
     * Check if search term looks like a BR number
     * Sri Lankan BR format: typically alphanumeric with specific patterns
     */
    protected function looksLikeBRNumber(string $term): bool
    {
        $term = strtoupper(preg_replace('/[\s\-]/', '', $term));
        
        // Common BR patterns:
        // - PV12345 (Private company)
        // - PB12345 (Public company)
        // - Numeric: 12345678
        return preg_match('/^(PV|PB|GA|CS)?\d{5,10}$/', $term);
    }

    /**
     * Convert fuzzy results to masked DTOs
     * 
     * Privacy handling:
     * - For linked organizations: show more details
     * - For search results: show limited info
     */
    protected function convertToMaskedDTOs(array $fuzzyResults, ?int $customerId, string $searchTerm): array
    {
        $dtos = [];
        $linkedOrgIds = $customerId ? $this->getLinkedOrganizationIds($customerId) : [];

        foreach ($fuzzyResults as $result) {
            $org = $result['record'];
            $score = $result['score'];
            $matchedFields = $result['matchedFields'] ?? [];

            $isLinked = in_array($org->ID, $linkedOrgIds);

            $dto = new OrganizationSearchResultDTO();
            $dto->ID = $org->ID;
            $dto->MatchScore = round($score * 100);

            // Organization name - not sensitive
            $dto->DisplayName = $org->Name;

            // Masked BR number - show partial
            $dto->MaskedBRNumber = DataMaskingUtil::maskBRNumber($org->RegistrationNumber);

            // Full BR shown only if linked
            $dto->FullBRNumber = $isLinked ? $org->RegistrationNumber : null;

            // Trading name
            $dto->TradingName = $org->TradingName ?: null;

            // Location - city/country only
            $dto->Location = $this->formatLocation($org);

            // Organization type/category
            $dto->OrganizationType = $org->OrganizationType ?? 'Unknown';

            // User's membership info (if linked)
            if ($isLinked && $customerId) {
                $membership = OrganizationMember::get()
                    ->filter([
                        'CustomerID' => $customerId,
                        'OrganizationID' => $org->ID
                    ])
                    ->first();
                
                if ($membership) {
                    $dto->MembershipRole = $this->getMembershipRole($membership);
                    $dto->CanActAsBilling = (bool)$membership->IsBilling;
                    $dto->CanActAsAdmin = (bool)$membership->IsAdmin;
                }
            }

            // Match context
            $dto->MatchContext = $this->determineMatchContext($matchedFields);

            // Verification status
            $dto->IsVerified = ($org->IsApproved === 'Approved');

            // Is user linked to this org
            $dto->IsLinked = $isLinked;

            $dtos[] = $dto;
        }

        return $dtos;
    }

    /**
     * Format location string
     */
    protected function formatLocation(Organization $org): string
    {
        $parts = [];
        
        if ($org->Town) {
            $parts[] = $org->Town;
        }
        
        if ($org->CountryID && $org->Country()->exists()) {
            $parts[] = $org->Country()->Name;
        }

        return implode(', ', $parts) ?: 'Unknown';
    }

    /**
     * Get membership role description
     */
    protected function getMembershipRole(OrganizationMember $membership): string
    {
        $roles = [];
        
        if ($membership->IsAdmin) {
            $roles[] = 'Admin';
        }
        if ($membership->IsBilling) {
            $roles[] = 'Billing Contact';
        }
        if ($membership->IsTechnical ?? false) {
            $roles[] = 'Technical Contact';
        }

        return implode(', ', $roles) ?: 'Member';
    }

    /**
     * Determine the match context for UI highlighting
     */
    protected function determineMatchContext(array $matchedFields): string
    {
        if (empty($matchedFields)) {
            return 'name';
        }

        $priorityMap = [
            'Name' => 'name',
            'TradingName' => 'name',
            'RegistrationNumber' => 'br_number',
        ];

        foreach ($matchedFields as $field) {
            if (isset($priorityMap[$field])) {
                return $priorityMap[$field];
            }
        }

        return 'name';
    }

    /**
     * Get organizations where user has billing rights
     */
    public function getOrganizationsForBilling(int $customerId): array
    {
        $memberships = OrganizationMember::get()
            ->filter([
                'CustomerID' => $customerId,
                'IsBilling' => 1
            ]);

        $results = [];
        foreach ($memberships as $membership) {
            $org = $membership->Organization();
            if ($org && $org->exists() && $org->IsApproved === 'Approved') {
                $results[] = [
                    'id' => $org->ID,
                    'name' => $org->Name,
                    'brNumber' => $org->RegistrationNumber,
                    'role' => $this->getMembershipRole($membership)
                ];
            }
        }

        return $results;
    }

    /**
     * Validate that an organization can be selected as registrant
     */
    public function validateSelection(int $organizationId, int $currentUserId): array
    {
        $org = Organization::get()->byID($organizationId);

        if (!$org) {
            return ['valid' => false, 'error' => 'Organization not found'];
        }

        // Check if organization is approved
        if ($org->IsApproved !== 'Approved') {
            return ['valid' => false, 'error' => 'Organization is not approved'];
        }

        // Check if user is linked to this organization
        $membership = OrganizationMember::get()
            ->filter([
                'CustomerID' => $currentUserId,
                'OrganizationID' => $organizationId
            ])
            ->first();

        if (!$membership) {
            return ['valid' => false, 'error' => 'You are not authorized to act on behalf of this organization'];
        }

        return [
            'valid' => true,
            'organizationId' => $org->ID,
            'name' => $org->Name,
            'brNumber' => $org->RegistrationNumber,
            'userRole' => $this->getMembershipRole($membership),
            'canBill' => (bool)$membership->IsBilling
        ];
    }

    /**
     * Quick search for autocomplete
     */
    public function quickSearch(string $searchTerm, int $customerId, int $limit = 5): array
    {
        $results = $this->search($searchTerm, $customerId, [
            'limit' => $limit,
            'onlyApproved' => true
        ]);

        return array_map(function(OrganizationSearchResultDTO $dto) {
            return [
                'id' => $dto->ID,
                'label' => $dto->DisplayName,
                'maskedBR' => $dto->MaskedBRNumber,
                'isLinked' => $dto->IsLinked,
                'matchScore' => $dto->MatchScore
            ];
        }, $results);
    }
}
