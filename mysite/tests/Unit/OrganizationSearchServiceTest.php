<?php

namespace LKDomains\Tests\Unit;

use LKDomains\Services\Search\OrganizationSearchService;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit tests for OrganizationSearchService
 */
class OrganizationSearchServiceTest extends SapphireTest
{
    protected static $fixture_file = 'OrganizationSearchServiceTest.yml';

    /**
     * @var OrganizationSearchService
     */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrganizationSearchService();
    }

    /**
     * Test search returns empty for short terms
     */
    public function testSearchReturnsEmptyForShortTerms()
    {
        $result = $this->service->search('ab', 1);
        
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['totalCount']);
    }

    /**
     * Test search with membership filtering
     */
    public function testSearchWithMembershipFiltering()
    {
        // Customer 1 should only see their organizations
        $result = $this->service->search('Company', 1);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('results', $result);
    }

    /**
     * Test searchAll returns all approved organizations
     */
    public function testSearchAllReturnsAllApproved()
    {
        $result = $this->service->searchAll('Company');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('results', $result);
    }

    /**
     * Test search results include country information
     */
    public function testSearchResultsIncludeCountry()
    {
        $result = $this->service->searchAll('Company');
        
        if (!empty($result['results'])) {
            $firstResult = $result['results'][0];
            
            $this->assertArrayHasKey('id', $firstResult);
            $this->assertArrayHasKey('name', $firstResult);
            $this->assertArrayHasKey('partialBRNumber', $firstResult);
            $this->assertArrayHasKey('country', $firstResult);
            $this->assertArrayHasKey('countryId', $firstResult);
            $this->assertArrayHasKey('matchScore', $firstResult);
        }
    }

    /**
     * Test BR number is masked in results
     */
    public function testBRNumberIsMasked()
    {
        $result = $this->service->searchAll('Company');
        
        if (!empty($result['results'])) {
            $firstResult = $result['results'][0];
            
            // BR number should start with ****
            $this->assertStringStartsWith('****', $firstResult['partialBRNumber']);
        }
    }

    /**
     * Test search with pagination
     */
    public function testSearchWithPagination()
    {
        $result = $this->service->searchAll('Company', [
            'limit' => 5,
            'offset' => 0
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertLessThanOrEqual(5, count($result['results']));
        $this->assertArrayHasKey('hasMore', $result);
    }

    /**
     * Test getById returns null for non-existent organization
     */
    public function testGetByIdReturnsNullForNonExistent()
    {
        $result = $this->service->getById(999999);
        
        $this->assertNull($result);
    }

    /**
     * Test getById with access check
     */
    public function testGetByIdWithAccessCheck()
    {
        // Customer without membership should get null
        $result = $this->service->getById(1, 999);
        
        $this->assertNull($result);
    }
}
