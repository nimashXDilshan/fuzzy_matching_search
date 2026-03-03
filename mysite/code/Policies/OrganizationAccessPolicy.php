<?php

namespace LKDomains\Policies;

use LKDomains\Models\Members\OrganizationMember;
use LKDomains\Models\Organizations\Organization;

/**
 * OrganizationAccessPolicy
 *
 * Validates organization access for users during registration.
 * Ensures users can only select organizations they are members of.
 */
class OrganizationAccessPolicy
{
    /**
     * Get all organizations accessible by a customer
     *
     * @param int $customerId Customer ID
     * @return \SilverStripe\ORM\DataList List of accessible organizations
     */
    public static function getAccessibleOrganizations(int $customerId)
    {
        $membershipIds = OrganizationMember::get()
            ->filter([
                'CustomerID' => $customerId,
                'IsApproved' => true,
            ])
            ->column('OrganizationID');

        if (empty($membershipIds)) {
            return Organization::get()->filter('ID', 0); // Empty result
        }

        return Organization::get()
            ->filter([
                'ID' => $membershipIds,
                'IsApproved' => true,
            ]);
    }

    /**
     * Check if a customer can access a specific organization
     *
     * @param int $customerId Customer ID
     * @param int $organizationId Organization ID
     * @return bool True if customer can access the organization
     */
    public static function canAccessOrganization(int $customerId, int $organizationId): bool
    {
        // Check if organization exists and is approved
        $org = Organization::get()->byID($organizationId);

        if (!$org || !$org->exists() || !$org->isApproved()) {
            return false;
        }

        // Check if customer has approved membership
        return OrganizationMember::get()->filter([
            'CustomerID' => $customerId,
            'OrganizationID' => $organizationId,
            'IsApproved' => true,
        ])->exists();
    }

    /**
     * Get the membership record for a customer-organization relationship
     *
     * @param int $customerId Customer ID
     * @param int $organizationId Organization ID
     * @return OrganizationMember|null Membership record or null
     */
    public static function getMembership(int $customerId, int $organizationId): ?OrganizationMember
    {
        return OrganizationMember::get()->filter([
            'CustomerID' => $customerId,
            'OrganizationID' => $organizationId,
        ])->first();
    }

    /**
     * Check if customer has any approved organization memberships
     *
     * @param int $customerId Customer ID
     * @return bool True if customer has at least one approved membership
     */
    public static function hasAnyMembership(int $customerId): bool
    {
        return OrganizationMember::get()->filter([
            'CustomerID' => $customerId,
            'IsApproved' => true,
        ])->exists();
    }

    /**
     * Get count of accessible organizations for a customer
     *
     * @param int $customerId Customer ID
     * @return int Number of accessible organizations
     */
    public static function getAccessibleCount(int $customerId): int
    {
        return self::getAccessibleOrganizations($customerId)->count();
    }

    /**
     * Validate organization selection for registration
     *
     * @param int $customerId Customer ID
     * @param int $organizationId Organization ID
     * @param int|null $requiredCountryId Required country ID (optional)
     * @return array Validation result with 'valid' and 'error' keys
     */
    public static function validateSelection(
        int $customerId,
        int $organizationId,
        ?int $requiredCountryId = null
    ): array {
        // Check organization exists
        $org = Organization::get()->byID($organizationId);

        if (!$org || !$org->exists()) {
            return [
                'valid' => false,
                'error' => 'Organization not found',
            ];
        }

        // Check organization is approved
        if (!$org->isApproved()) {
            return [
                'valid' => false,
                'error' => 'Organization is not approved',
            ];
        }

        // Check customer has access
        if (!self::canAccessOrganization($customerId, $organizationId)) {
            return [
                'valid' => false,
                'error' => 'You do not have access to this organization',
            ];
        }

        // Check country if required
        if ($requiredCountryId !== null && $org->CountryID !== $requiredCountryId) {
            return [
                'valid' => false,
                'error' => 'Organization country does not match required country',
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }
}
