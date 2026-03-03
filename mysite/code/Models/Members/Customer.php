<?php

namespace LKDomains\Models\Members;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Customer model for domain registration
 * 
 * Contains personal/contact information for individuals
 * who may register domains or be organization members.
 */
class Customer extends DataObject
{
    private static $table_name = 'Customer';
    
    private static $db = [
        'FirstName' => 'Varchar(100)',
        'Surname' => 'Varchar(100)',
        'Email' => 'Varchar(256)',
        'NIC' => 'Varchar(256)',
        'MobileTelephone' => 'Varchar(50)',
        'Telephone' => 'Varchar(50)',
        'ApprovalStatus' => 'Enum("Pending,Approved,Rejected", "Pending")',
        'AccountDeactivationStatus' => 'Enum("None,Deactivated", "None")',
    ];
    
    private static $has_one = [
        'Member' => Member::class,
    ];
    
    private static $has_many = [
        'OrganizationMemberships' => OrganizationMember::class,
    ];
    
    private static $indexes = [
        'ApprovalStatus' => true,
        'NIC' => true,
        'MobileTelephone' => true,
        'Email' => true,
        'ActiveStatus' => [
            'type' => 'index',
            'columns' => ['AccountDeactivationStatus', 'ApprovalStatus'],
        ],
    ];
    
    private static $summary_fields = [
        'FirstName' => 'First Name',
        'Surname' => 'Surname',
        'Email' => 'Email',
        'ApprovalStatus' => 'Status',
    ];
    
    private static $searchable_fields = [
        'FirstName',
        'Surname',
        'Email',
        'NIC',
    ];
    
    /**
     * Get full name of the customer
     */
    public function getFullName(): string
    {
        return trim($this->FirstName . ' ' . $this->Surname);
    }
    
    /**
     * Check if customer is approved
     */
    public function isApproved(): bool
    {
        return $this->ApprovalStatus === 'Approved';
    }
    
    /**
     * Get organizations this customer is a member of
     */
    public function getApprovedOrganizations()
    {
        return $this->OrganizationMemberships()
            ->filter('IsApproved', true)
            ->relation('Organization')
            ->filter('IsApproved', true);
    }
}
