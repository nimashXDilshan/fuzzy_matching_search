<?php

namespace LKDomains\Tests\Unit;

use LKDomains\Services\Search\CustomerSearchService;
use LKDomains\Services\Search\FuzzyMatchEngine;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit tests for CustomerSearchService
 */
class CustomerSearchServiceTest extends SapphireTest
{
    protected static $fixture_file = 'CustomerSearchServiceTest.yml';

    /**
     * @var CustomerSearchService
     */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerSearchService();
    }

    /**
     * Test search returns empty for short terms
     */
    public function testSearchReturnsEmptyForShortTerms()
    {
        $result = $this->service->search('ab');
        
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['totalCount']);
        $this->assertStringContainsString('at least', $result['message']);
    }

    /**
     * Test search returns results for valid term
     */
    public function testSearchReturnsResultsForValidTerm()
    {
        $result = $this->service->search('john');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertArrayHasKey('hasMore', $result);
    }

    /**
     * Test search results are properly masked
     */
    public function testSearchResultsAreMasked()
    {
        $result = $this->service->search('john');
        
        if (!empty($result['results'])) {
            $firstResult = $result['results'][0];
            
            $this->assertArrayHasKey('id', $firstResult);
            $this->assertArrayHasKey('displayName', $firstResult);
            $this->assertArrayHasKey('maskedEmail', $firstResult);
            $this->assertArrayHasKey('partialNIC', $firstResult);
            $this->assertArrayHasKey('matchScore', $firstResult);
            
            // Verify masking patterns
            $this->assertStringContainsString('***', $firstResult['displayName']);
            $this->assertStringContainsString('***', $firstResult['maskedEmail']);
            $this->assertStringContainsString('***', $firstResult['partialNIC']);
        }
    }

    /**
     * Test search with specific fields
     */
    public function testSearchWithSpecificFields()
    {
        $result = $this->service->search('john', [
            'searchFields' => ['name']
        ]);
        
        $this->assertTrue($result['success']);
    }

    /**
     * Test search with pagination
     */
    public function testSearchWithPagination()
    {
        $result = $this->service->search('john', [
            'limit' => 5,
            'offset' => 0
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertLessThanOrEqual(5, count($result['results']));
    }

    /**
     * Test empty search term
     */
    public function testEmptySearchTerm()
    {
        $result = $this->service->search('');
        
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['results']);
    }

    /**
     * Test search sanitizes input
     */
    public function testSearchSanitizesInput()
    {
        // Should not throw exception for special characters
        $result = $this->service->search("john'; DROP TABLE Customer;--");
        
        $this->assertTrue($result['success']);
    }
}
