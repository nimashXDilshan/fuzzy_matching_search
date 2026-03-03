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
        // Standard NIC
        $this->assertEquals('***1234V', DataMaskingUtil::maskNIC('1990123451234V'));
        
        // Short NIC
        $this->assertEquals('***1234', DataMaskingUtil::maskNIC('1234'));
        
        // Very short NIC
        $this->assertEquals('***123', DataMaskingUtil::maskNIC('123'));
        
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
        $this->assertEquals('John D***', DataMaskingUtil::maskName('John', 'Doe'));
        
        // Only first name
        $this->assertEquals('John', DataMaskingUtil::maskName('John', ''));
        $this->assertEquals('John', DataMaskingUtil::maskName('John', null));
        
        // Only surname
        $this->assertEquals('D***', DataMaskingUtil::maskName('', 'Doe'));
        $this->assertEquals('D***', DataMaskingUtil::maskName(null, 'Doe'));
        
        // Both empty
        $this->assertEquals('***', DataMaskingUtil::maskName('', ''));
        $this->assertEquals('***', DataMaskingUtil::maskName(null, null));
    }

    /**
     * Test BR number masking
     */
    public function testMaskBRNumber()
    {
        // Standard BR number
        $this->assertEquals('****5678', DataMaskingUtil::maskBRNumber('PV123455678'));
        
        // Short BR number
        $this->assertEquals('****5678', DataMaskingUtil::maskBRNumber('5678'));
        
        // Very short BR number
        $this->assertEquals('****123', DataMaskingUtil::maskBRNumber('123'));
        
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
        $this->assertEquals('******1234', DataMaskingUtil::maskPhone('+94771234567'));
        
        // Phone with formatting
        $this->assertEquals('******4567', DataMaskingUtil::maskPhone('+94 77 123 4567'));
        
        // Short phone
        $this->assertEquals('******1234', DataMaskingUtil::maskPhone('1234'));
        
        // Empty phone
        $this->assertEquals('', DataMaskingUtil::maskPhone(''));
        $this->assertEquals('', DataMaskingUtil::maskPhone(null));
    }
}
