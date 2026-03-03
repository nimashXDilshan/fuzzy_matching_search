<?php

namespace LKDomains\Models\Organizations;

use LKDomains\Models\Members\OrganizationMember;
use LKDomains\Models\Registration\Country;
use SilverStripe\ORM\DataObject;

/**
 * Organization model for domain registration
 * 
 * Represents business entities, government entities,
 * educational institutions, and non-profits.
 */
class Organization extends DataObject
{
    private static $table_name = 'Organization';
    
    private static $db = [
        'Name' => 'Text',
        'TradingName' => 'Varchar(256)',
        'RegistrationNumber' => 'Varchar(100)',
        'IsApproved' => 'Boolean',
    ];
    
    private static $has_one = [
        'Country' => Country::class,
    ];
    
    private static $has_many = [
        'Members' => OrganizationMember::class,
    ];
    
    private static $defaults = [
        'IsApproved' => false,
    ];
    
    private static $indexes = [
        'IsApproved' => true,
        'RegistrationNumber' => true,
        'SearchFields' => [
            'type' => 'fulltext',
            'columns' => ['Name', 'TradingName'],
        ],
    ];
    
    private static $summary_fields = [
        'Name' => 'Organization Name',
        'RegistrationNumber' => 'BR Number',
        'Country.Name' => 'Country',
        'IsApproved.Nice' => 'Approved',
    ];
    
    private static $searchable_fields = [
        'Name',
        'RegistrationNumber',
    ];
    
    /**
     * Check if organization is approved
     */
    public function isApproved(): bool
    {
        return (bool) $this->IsApproved;
    }
    
    /**
     * Get country name
     */
    public function getCountryName(): string
    {
        $country = $this->Country();
        return $country && $country->exists() ? $country->Name : '';
    }
    
    /**
     * Get approved members of this organization
     */
    public function getApprovedMembers()
    {
        return $this->Members()->filter('IsApproved', true);
    }
    
    /**
     * Check if a customer is an approved member
     */
    public function hasApprovedMember(int $customerId): bool
    {
        return $this->Members()->filter([
            'CustomerID' => $customerId,
            'IsApproved' => true,
        ])->exists();
    }
}
