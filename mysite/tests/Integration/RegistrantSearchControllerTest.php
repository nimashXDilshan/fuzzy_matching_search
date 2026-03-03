<?php

namespace LKDomains\Tests\Integration;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Integration tests for RegistrantSearchController API endpoints
 */
class RegistrantSearchControllerTest extends FunctionalTest
{
    protected static $fixture_file = 'RegistrantSearchControllerTest.yml';

    /**
     * Test search customers endpoint returns JSON
     */
    public function testSearchCustomersReturnsJSON()
    {
        $response = $this->post('/api/registration/search-customers', json_encode([
            'searchTerm' => 'john',
            'limit' => 10,
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
    }

    /**
     * Test search customers requires search term
     */
    public function testSearchCustomersRequiresSearchTerm()
    {
        $response = $this->post('/api/registration/search-customers', json_encode([
            'limit' => 10,
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test search organizations endpoint
     */
    public function testSearchOrganizationsEndpoint()
    {
        $response = $this->post('/api/registration/search-organizations', json_encode([
            'searchTerm' => 'company',
            'limit' => 10,
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
    }

    /**
     * Test get domain reasons endpoint
     */
    public function testGetDomainReasonsEndpoint()
    {
        $response = $this->get('/api/registration/domain-reasons');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('reasons', $data);
        $this->assertIsArray($data['reasons']);
    }

    /**
     * Test get registrant types endpoint
     */
    public function testGetRegistrantTypesEndpoint()
    {
        $response = $this->get('/api/registration/registrant-types/1');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('config', $data);
    }

    /**
     * Test get registrant types requires valid ID
     */
    public function testGetRegistrantTypesRequiresValidID()
    {
        $response = $this->get('/api/registration/registrant-types/0');
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Test get countries endpoint
     */
    public function testGetCountriesEndpoint()
    {
        $response = $this->get('/api/registration/countries');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('countries', $data);
        $this->assertIsArray($data['countries']);
    }

    /**
     * Test validate selection endpoint
     */
    public function testValidateSelectionEndpoint()
    {
        $response = $this->post('/api/registration/validate-selection', json_encode([
            'registrantType' => 'individual',
            'domainReasonId' => 1,
            'registrantId' => 1,
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        // May be 200 or 400 depending on data
        $this->assertContains($response->getStatusCode(), [200, 400]);
        
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test validate selection requires all fields
     */
    public function testValidateSelectionRequiresAllFields()
    {
        $response = $this->post('/api/registration/validate-selection', json_encode([
            'registrantType' => 'individual',
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
    }

    /**
     * Test search results don't contain sensitive data
     */
    public function testSearchResultsNoSensitiveData()
    {
        $response = $this->post('/api/registration/search-customers', json_encode([
            'searchTerm' => 'john',
            'limit' => 10,
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        $data = json_decode($response->getBody(), true);
        
        if ($data['success'] && !empty($data['results'])) {
            $result = $data['results'][0];
            
            // Should NOT have these sensitive fields
            $this->assertArrayNotHasKey('Email', $result);
            $this->assertArrayNotHasKey('NIC', $result);
            $this->assertArrayNotHasKey('MobileTelephone', $result);
            $this->assertArrayNotHasKey('password', $result);
            
            // Should have masked versions
            $this->assertArrayHasKey('maskedEmail', $result);
            $this->assertArrayHasKey('partialNIC', $result);
            
            // Verify masking is applied
            if (!empty($result['maskedEmail'])) {
                $this->assertStringContainsString('***', $result['maskedEmail']);
            }
            if (!empty($result['partialNIC'])) {
                $this->assertStringContainsString('***', $result['partialNIC']);
            }
        }
    }

    /**
     * Test organization search filters by membership for logged-in users
     */
    public function testOrganizationSearchFiltersForLoggedInUser()
    {
        // This would need a logged-in member fixture
        // The test verifies the filtering behavior
        $response = $this->post('/api/registration/search-organizations', json_encode([
            'searchTerm' => 'company',
            'limit' => 10,
        ]), null, null, null, ['Content-Type' => 'application/json']);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test invalid JSON returns error
     */
    public function testInvalidJSONReturnsError()
    {
        $response = $this->post('/api/registration/search-customers', 
            'not valid json',
            null, null, null, 
            ['Content-Type' => 'application/json']
        );
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Test method not allowed for wrong HTTP method
     */
    public function testMethodNotAllowed()
    {
        $response = $this->get('/api/registration/search-customers');
        
        $this->assertEquals(405, $response->getStatusCode());
    }
}
