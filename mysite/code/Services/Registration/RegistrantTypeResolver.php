<?php

namespace LKDomains\Services\Registration;

use LKDomains\Models\Registration\DomainReason;

/**
 * RegistrantTypeResolver
 * 
 * Determines allowed registrant types based on domain reason selection.
 * Maps domain reasons to 'individual', 'organization', or both.
 */
class RegistrantTypeResolver
{
    /**
     * Domain reasons that only allow organization registrants
     */
    private static array $organizationOnlyReasons = [
        'BUSINESS',
        'GOVERNMENT',
        'EDUCATION',
        'NONPROFIT',
    ];
    
    /**
     * Domain reasons that only allow individual registrants
     */
    private static array $individualOnlyReasons = [
        'PERSONAL',
    ];
    
    /**
     * Get allowed registrant types for a domain reason
     * 
     * @param int $domainReasonId Domain reason ID
     * @return array List of allowed types: ['individual'], ['organization'], or ['individual', 'organization']
     */
    public static function getAllowedTypes(int $domainReasonId): array
    {
        $reason = DomainReason::get()->byID($domainReasonId);
        
        if (!$reason || !$reason->exists()) {
            // Default to both if reason not found
            return ['individual', 'organization'];
        }
        
        return $reason->getAllowedTypes();
    }
    
    /**
     * Get allowed registrant types by domain reason code
     * 
     * @param string $reasonCode Domain reason code (e.g., 'BUSINESS')
     * @return array List of allowed types
     */
    public static function getAllowedTypesByCode(string $reasonCode): array
    {
        $reason = DomainReason::get()->filter('Code', $reasonCode)->first();
        
        if (!$reason || !$reason->exists()) {
            return ['individual', 'organization'];
        }
        
        return $reason->getAllowedTypes();
    }
    
    /**
     * Check if individual registrant is allowed for a domain reason
     * 
     * @param int $domainReasonId Domain reason ID
     * @return bool True if individual is allowed
     */
    public static function allowsIndividual(int $domainReasonId): bool
    {
        $allowedTypes = self::getAllowedTypes($domainReasonId);
        return in_array('individual', $allowedTypes);
    }
    
    /**
     * Check if organization registrant is allowed for a domain reason
     * 
     * @param int $domainReasonId Domain reason ID
     * @return bool True if organization is allowed
     */
    public static function allowsOrganization(int $domainReasonId): bool
    {
        $allowedTypes = self::getAllowedTypes($domainReasonId);
        return in_array('organization', $allowedTypes);
    }
    
    /**
     * Get the default registrant type for a domain reason
     * Returns the first allowed type
     * 
     * @param int $domainReasonId Domain reason ID
     * @return string 'individual' or 'organization'
     */
    public static function getDefaultType(int $domainReasonId): string
    {
        $allowedTypes = self::getAllowedTypes($domainReasonId);
        return $allowedTypes[0] ?? 'individual';
    }
    
    /**
     * Get UI configuration for registrant type selector
     * 
     * @param int $domainReasonId Domain reason ID
     * @return array UI configuration
     */
    public static function getUIConfig(int $domainReasonId): array
    {
        $allowedTypes = self::getAllowedTypes($domainReasonId);
        $reason = DomainReason::get()->byID($domainReasonId);
        
        return [
            'domainReasonId' => $domainReasonId,
            'reasonName' => $reason ? $reason->Name : 'Unknown',
            'allowedTypes' => $allowedTypes,
            'defaultType' => $allowedTypes[0] ?? 'individual',
            'individualEnabled' => in_array('individual', $allowedTypes),
            'organizationEnabled' => in_array('organization', $allowedTypes),
            'isLocked' => count($allowedTypes) === 1,
        ];
    }
    
    /**
     * Validate that a registrant type is allowed for a domain reason
     * 
     * @param int $domainReasonId Domain reason ID
     * @param string $registrantType 'individual' or 'organization'
     * @return bool True if valid
     */
    public static function validateType(int $domainReasonId, string $registrantType): bool
    {
        $allowedTypes = self::getAllowedTypes($domainReasonId);
        return in_array($registrantType, $allowedTypes);
    }
    
    /**
     * Get all domain reasons with their allowed types
     * 
     * @param bool $activeOnly Only return active reasons
     * @return array List of reasons with type configurations
     */
    public static function getAllReasons(bool $activeOnly = true): array
    {
        $query = DomainReason::get();
        
        if ($activeOnly) {
            $query = $query->filter('IsActive', true);
        }
        
        $reasons = [];
        
        foreach ($query as $reason) {
            $reasons[] = [
                'id' => $reason->ID,
                'name' => $reason->Name,
                'code' => $reason->Code,
                'description' => $reason->Description,
                'allowedTypes' => $reason->getAllowedTypes(),
            ];
        }
        
        return $reasons;
    }
}
