<?php

namespace LKDomains\Models\Members;

use LKDomains\Models\Organizations\Organization;
use SilverStripe\ORM\DataObject;

/**
 * OrganizationMember model
 * 
 * Links customers to organizations with approval status.
 * Used to control which organizations a customer can access
 * during domain registration.
 */
class OrganizationMember extends DataObject
{
    private static $table_name = 'OrganizationMember';
    
    private static $db = [
        'Role' => 'Varchar(100)',
        'IsApproved' => 'Boolean',
        'JoinedDate' => 'Date',
    ];
    
    private static $has_one = [
        'Customer' => Customer::class,
        'Organization' => Organization::class,
    ];
    
    private static $defaults = [
        'IsApproved' => false,
    ];
    
    private static $indexes = [
        'CustomerOrganization' => [
            'type' => 'unique',
            'columns' => ['CustomerID', 'OrganizationID'],
        ],
        'IsApproved' => true,
    ];
    
    private static $summary_fields = [
        'Customer.FullName' => 'Customer',
        'Organization.Name' => 'Organization',
        'Role' => 'Role',
        'IsApproved.Nice' => 'Approved',
    ];
    
    /**
     * Get display title for admin
     */
    public function getTitle(): string
    {
        $customer = $this->Customer();
        $org = $this->Organization();
        
        $customerName = $customer && $customer->exists() ? $customer->getFullName() : 'Unknown';
        $orgName = $org && $org->exists() ? $org->Name : 'Unknown';
        
        return "{$customerName} @ {$orgName}";
    }
    
    /**
     * Check if membership is approved
     */
    public function isApproved(): bool
    {
        return (bool) $this->IsApproved;
    }
}
