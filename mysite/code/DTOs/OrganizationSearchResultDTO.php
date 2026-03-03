<?php

namespace LKDomains\DTOs;

/**
 * Organization Search Result DTO
 * 
 * Data Transfer Object for organization search results.
 * Includes access control information and masked data.
 */
class OrganizationSearchResultDTO
{
    /** @var int Organization ID */
    public int $ID;

    /** @var int Match score (0-100) */
    public int $MatchScore = 0;

    /** @var string Organization name (not sensitive) */
    public string $DisplayName = '';

    /** @var string Masked BR number (e.g., "PV***678") */
    public string $MaskedBRNumber = '';

    /** @var string|null Full BR number (only if user is linked) */
    public ?string $FullBRNumber = null;

    /** @var string|null Trading name */
    public ?string $TradingName = null;

    /** @var string Location (city, country only) */
    public string $Location = '';

    /** @var string Organization type/category */
    public string $OrganizationType = 'Unknown';

    /** @var string|null User's role in this organization */
    public ?string $MembershipRole = null;

    /** @var bool Can user act as billing contact */
    public bool $CanActAsBilling = false;

    /** @var bool Can user act as admin */
    public bool $CanActAsAdmin = false;

    /** @var string Match context (name, br_number) */
    public string $MatchContext = 'name';

    /** @var bool Whether organization is verified/approved */
    public bool $IsVerified = false;

    /** @var bool Whether current user is linked to this org */
    public bool $IsLinked = false;

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->ID,
            'matchScore' => $this->MatchScore,
            'displayName' => $this->DisplayName,
            'maskedBRNumber' => $this->MaskedBRNumber,
            'fullBRNumber' => $this->FullBRNumber,
            'tradingName' => $this->TradingName,
            'location' => $this->Location,
            'organizationType' => $this->OrganizationType,
            'membershipRole' => $this->MembershipRole,
            'canActAsBilling' => $this->CanActAsBilling,
            'canActAsAdmin' => $this->CanActAsAdmin,
            'matchContext' => $this->MatchContext,
            'isVerified' => $this->IsVerified,
            'isLinked' => $this->IsLinked,
            'type' => 'organization'
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->ID = $data['id'] ?? 0;
        $dto->MatchScore = $data['matchScore'] ?? 0;
        $dto->DisplayName = $data['displayName'] ?? '';
        $dto->MaskedBRNumber = $data['maskedBRNumber'] ?? '';
        $dto->FullBRNumber = $data['fullBRNumber'] ?? null;
        $dto->TradingName = $data['tradingName'] ?? null;
        $dto->Location = $data['location'] ?? '';
        $dto->OrganizationType = $data['organizationType'] ?? 'Unknown';
        $dto->MembershipRole = $data['membershipRole'] ?? null;
        $dto->CanActAsBilling = $data['canActAsBilling'] ?? false;
        $dto->CanActAsAdmin = $data['canActAsAdmin'] ?? false;
        $dto->MatchContext = $data['matchContext'] ?? 'name';
        $dto->IsVerified = $data['isVerified'] ?? false;
        $dto->IsLinked = $data['isLinked'] ?? false;
        return $dto;
    }
}
