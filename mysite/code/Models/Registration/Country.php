<?php

namespace LKDomains\Models\Registration;

use SilverStripe\ORM\DataObject;

/**
 * Country model
 *
 * Lookup table for countries, required for organization registration.
 */
class Country extends DataObject
{
    private static $table_name = 'Country';

    private static $db = [
        'Name' => 'Varchar(100)',
        'Code' => 'Varchar(10)',
        'IsActive' => 'Boolean',
    ];

    private static $defaults = [
        'IsActive' => true,
    ];

    private static $indexes = [
        'Code' => [
            'type' => 'unique',
            'columns' => ['Code'],
        ],
        'IsActive' => true,
    ];

    private static $default_sort = 'Name ASC';

    private static $summary_fields = [
        'Name' => 'Country',
        'Code' => 'Code',
        'IsActive.Nice' => 'Active',
    ];

    /**
     * Get title for dropdowns
     */
    public function getTitle(): string
    {
        return $this->Name;
    }

    /**
     * Require default countries on dev/build
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $countries = [
            ['Name' => 'Sri Lanka', 'Code' => 'LK'],
            ['Name' => 'India', 'Code' => 'IN'],
            ['Name' => 'United States', 'Code' => 'US'],
            ['Name' => 'United Kingdom', 'Code' => 'GB'],
            ['Name' => 'Australia', 'Code' => 'AU'],
            ['Name' => 'Singapore', 'Code' => 'SG'],
            ['Name' => 'Japan', 'Code' => 'JP'],
            ['Name' => 'Germany', 'Code' => 'DE'],
            ['Name' => 'France', 'Code' => 'FR'],
            ['Name' => 'Canada', 'Code' => 'CA'],
        ];

        foreach ($countries as $data) {
            if (!Country::get()->filter('Code', $data['Code'])->exists()) {
                $country = Country::create($data);
                $country->write();
            }
        }
    }
}
