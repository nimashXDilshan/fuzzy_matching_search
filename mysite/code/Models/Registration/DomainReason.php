<?php

namespace LKDomains\Models\Registration;

use SilverStripe\ORM\DataObject;

/**
 * DomainReason model
 *
 * Defines the purpose/reason for domain registration.
 * Used to determine allowed registrant types (individual vs organization).
 */
class DomainReason extends DataObject
{
    private static $table_name = 'DomainReason';

    private static $db = [
        'Name' => 'Varchar(100)',
        'Description' => 'Text',
        'Code' => 'Varchar(50)',
        'AllowIndividual' => 'Boolean',
        'AllowOrganization' => 'Boolean',
        'IsActive' => 'Boolean',
        'SortOrder' => 'Int',
    ];

    private static $defaults = [
        'AllowIndividual' => true,
        'AllowOrganization' => true,
        'IsActive' => true,
        'SortOrder' => 0,
    ];

    private static $indexes = [
        'Code' => [
            'type' => 'unique',
            'columns' => ['Code'],
        ],
        'IsActive' => true,
    ];

    private static $default_sort = 'SortOrder ASC, Name ASC';

    private static $summary_fields = [
        'Name' => 'Reason',
        'Code' => 'Code',
        'AllowIndividual.Nice' => 'Individual',
        'AllowOrganization.Nice' => 'Organization',
        'IsActive.Nice' => 'Active',
    ];

    /**
     * Get allowed registrant types for this reason
     *
     * @return array List of allowed types: 'individual', 'organization', or both
     */
    public function getAllowedTypes(): array
    {
        $types = [];

        if ($this->AllowIndividual) {
            $types[] = 'individual';
        }

        if ($this->AllowOrganization) {
            $types[] = 'organization';
        }

        return $types;
    }

    /**
     * Check if individual registrant is allowed
     */
    public function allowsIndividual(): bool
    {
        return (bool) $this->AllowIndividual;
    }

    /**
     * Check if organization registrant is allowed
     */
    public function allowsOrganization(): bool
    {
        return (bool) $this->AllowOrganization;
    }

    /**
     * Require default domain reasons on dev/build
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $defaults = [
            [
                'Name' => 'Personal/Individual',
                'Code' => 'PERSONAL',
                'AllowIndividual' => true,
                'AllowOrganization' => false,
                'Description' => 'Personal use by an individual',
            ],
            [
                'Name' => 'Business/Commercial',
                'Code' => 'BUSINESS',
                'AllowIndividual' => false,
                'AllowOrganization' => true,
                'Description' => 'Commercial business purposes',
            ],
            [
                'Name' => 'Government Entity',
                'Code' => 'GOVERNMENT',
                'AllowIndividual' => false,
                'AllowOrganization' => true,
                'Description' => 'Government department or agency',
            ],
            [
                'Name' => 'Educational Institution',
                'Code' => 'EDUCATION',
                'AllowIndividual' => false,
                'AllowOrganization' => true,
                'Description' => 'School, university, or educational organization',
            ],
            [
                'Name' => 'Non-Profit Organization',
                'Code' => 'NONPROFIT',
                'AllowIndividual' => false,
                'AllowOrganization' => true,
                'Description' => 'Charitable or non-profit organization',
            ],
            [
                'Name' => 'General Purpose',
                'Code' => 'GENERAL',
                'AllowIndividual' => true,
                'AllowOrganization' => true,
                'Description' => 'General purpose registration',
            ],
        ];

        foreach ($defaults as $index => $data) {
            if (!DomainReason::get()->filter('Code', $data['Code'])->exists()) {
                $reason = DomainReason::create($data);
                $reason->SortOrder = $index;
                $reason->write();
            }
        }
    }
}
