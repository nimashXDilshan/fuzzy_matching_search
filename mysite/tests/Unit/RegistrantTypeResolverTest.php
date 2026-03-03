<?php

namespace LKDomains\Tests\Unit;

use LKDomains\Models\Registration\DomainReason;
use LKDomains\Services\Registration\RegistrantTypeResolver;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit tests for RegistrantTypeResolver
 */
class RegistrantTypeResolverTest extends SapphireTest
{
    protected static $fixture_file = 'RegistrantTypeResolverTest.yml';

    /**
     * Test personal reason only allows individual
     */
    public function testPersonalReasonAllowsOnlyIndividual()
    {
        $reason = DomainReason::get()->filter('Code', 'PERSONAL')->first();
        
        if ($reason) {
            $types = RegistrantTypeResolver::getAllowedTypes($reason->ID);
            
            $this->assertContains('individual', $types);
            $this->assertNotContains('organization', $types);
        }
    }

    /**
     * Test business reason only allows organization
     */
    public function testBusinessReasonAllowsOnlyOrganization()
    {
        $reason = DomainReason::get()->filter('Code', 'BUSINESS')->first();
        
        if ($reason) {
            $types = RegistrantTypeResolver::getAllowedTypes($reason->ID);
            
            $this->assertContains('organization', $types);
            $this->assertNotContains('individual', $types);
        }
    }

    /**
     * Test general reason allows both types
     */
    public function testGeneralReasonAllowsBothTypes()
    {
        $reason = DomainReason::get()->filter('Code', 'GENERAL')->first();
        
        if ($reason) {
            $types = RegistrantTypeResolver::getAllowedTypes($reason->ID);
            
            $this->assertContains('individual', $types);
            $this->assertContains('organization', $types);
        }
    }

    /**
     * Test invalid reason ID returns both types as default
     */
    public function testInvalidReasonReturnsDefault()
    {
        $types = RegistrantTypeResolver::getAllowedTypes(999999);
        
        $this->assertContains('individual', $types);
        $this->assertContains('organization', $types);
    }

    /**
     * Test getAllowedTypesByCode
     */
    public function testGetAllowedTypesByCode()
    {
        $types = RegistrantTypeResolver::getAllowedTypesByCode('PERSONAL');
        
        $this->assertContains('individual', $types);
    }

    /**
     * Test allowsIndividual helper
     */
    public function testAllowsIndividualHelper()
    {
        $reason = DomainReason::get()->filter('Code', 'PERSONAL')->first();
        
        if ($reason) {
            $this->assertTrue(RegistrantTypeResolver::allowsIndividual($reason->ID));
        }
    }

    /**
     * Test allowsOrganization helper
     */
    public function testAllowsOrganizationHelper()
    {
        $reason = DomainReason::get()->filter('Code', 'BUSINESS')->first();
        
        if ($reason) {
            $this->assertTrue(RegistrantTypeResolver::allowsOrganization($reason->ID));
        }
    }

    /**
     * Test getDefaultType returns first allowed type
     */
    public function testGetDefaultType()
    {
        $reason = DomainReason::get()->filter('Code', 'PERSONAL')->first();
        
        if ($reason) {
            $default = RegistrantTypeResolver::getDefaultType($reason->ID);
            $this->assertEquals('individual', $default);
        }
    }

    /**
     * Test getUIConfig returns complete configuration
     */
    public function testGetUIConfigReturnsCompleteConfig()
    {
        $reason = DomainReason::get()->filter('Code', 'BUSINESS')->first();
        
        if ($reason) {
            $config = RegistrantTypeResolver::getUIConfig($reason->ID);
            
            $this->assertArrayHasKey('domainReasonId', $config);
            $this->assertArrayHasKey('reasonName', $config);
            $this->assertArrayHasKey('allowedTypes', $config);
            $this->assertArrayHasKey('defaultType', $config);
            $this->assertArrayHasKey('individualEnabled', $config);
            $this->assertArrayHasKey('organizationEnabled', $config);
            $this->assertArrayHasKey('isLocked', $config);
            
            // Business should lock to organization only
            $this->assertTrue($config['isLocked']);
            $this->assertFalse($config['individualEnabled']);
            $this->assertTrue($config['organizationEnabled']);
        }
    }

    /**
     * Test validateType
     */
    public function testValidateType()
    {
        $reason = DomainReason::get()->filter('Code', 'PERSONAL')->first();
        
        if ($reason) {
            $this->assertTrue(RegistrantTypeResolver::validateType($reason->ID, 'individual'));
            $this->assertFalse(RegistrantTypeResolver::validateType($reason->ID, 'organization'));
        }
    }

    /**
     * Test getAllReasons returns list with type info
     */
    public function testGetAllReasons()
    {
        $reasons = RegistrantTypeResolver::getAllReasons(true);
        
        $this->assertIsArray($reasons);
        
        if (!empty($reasons)) {
            $firstReason = $reasons[0];
            
            $this->assertArrayHasKey('id', $firstReason);
            $this->assertArrayHasKey('name', $firstReason);
            $this->assertArrayHasKey('code', $firstReason);
            $this->assertArrayHasKey('allowedTypes', $firstReason);
        }
    }
}
