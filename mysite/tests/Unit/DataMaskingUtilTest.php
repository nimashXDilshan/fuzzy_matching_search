<?php

namespace LKDomains\Tests\Unit;

use LKDomains\Utils\DataMaskingUtil;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit tests for DataMaskingUtil
 */
class DataMaskingUtilTest extends SapphireTest
{
    /**
     * Test email masking
     */
    public function testMaskEmail()
    {
        // Standard email
        $this->assertEquals('jo***@gmail.com', DataMaskingUtil::maskEmail('john.doe@gmail.com'));
        
        // Short local part
        $this->assertEquals('j***@example.com', DataMaskingUtil::maskEmail('j@example.com'));
        
        // Empty email
        $this->assertEquals('', DataMaskingUtil::maskEmail(''));
        $this->assertEquals('', DataMaskingUtil::maskEmail(null));
        
        // Invalid email (no @)
        $this->assertEquals('***@***', DataMaskingUtil::maskEmail('notanemail'));
    }

    /**
     * Test NIC masking
     */
    public function testMaskNIC()
    {
        // New NIC (12 digits)
        $this->assertEquals('2000****5678', DataMaskingUtil::maskNIC('200012345678'));
        
        // Old NIC (9 digits + V/X)
        $this->assertEquals('123****89V', DataMaskingUtil::maskNIC('123456789V'));
        
        // Short NIC
        $this->assertEquals('123*', DataMaskingUtil::maskNIC('1234'));
        
        // Empty NIC
        $this->assertEquals('', DataMaskingUtil::maskNIC(''));
        $this->assertEquals('', DataMaskingUtil::maskNIC(null));
    }

    /**
     * Test name masking
     */
    public function testMaskName()
    {
        // Standard name
        $this->assertEquals('D**a', DataMaskingUtil::maskName('Doe'));
        $this->assertEquals('Pe****ra', DataMaskingUtil::maskName('Perera'));
        
        // Empty
        $this->assertEquals('', DataMaskingUtil::maskName(''));
        $this->assertEquals('', DataMaskingUtil::maskName(null));
    }

    /**
     * Test BR number masking
     */
    public function testMaskBRNumber()
    {
        // Standard BR number
        $this->assertEquals('PV******678', DataMaskingUtil::maskBRNumber('PV123455678'));
        
        // Pure numeric BR number
        $this->assertEquals('***678', DataMaskingUtil::maskBRNumber('123455678'));
        
        // Empty BR number
        $this->assertEquals('', DataMaskingUtil::maskBRNumber(''));
        $this->assertEquals('', DataMaskingUtil::maskBRNumber(null));
    }

    /**
     * Test phone masking
     */
    public function testMaskPhone()
    {
        // Standard phone
        $this->assertEquals('****** 4567', DataMaskingUtil::maskPhone('+94771234567'));
        
        // Empty phone
        $this->assertEquals('', DataMaskingUtil::maskPhone(''));
        $this->assertEquals('', DataMaskingUtil::maskPhone(null));
    }
}
