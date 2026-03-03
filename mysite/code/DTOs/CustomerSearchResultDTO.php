<?php

namespace LKDomains\DTOs;

/**
 * Customer Search Result DTO
 *
 * Data Transfer Object for customer search results.
 * Contains only masked/safe-to-display data for privacy compliance.
 */
class CustomerSearchResultDTO
{
    /** @var int Customer ID */
    public int $ID;

    /** @var int Match score (0-100) */
    public int $MatchScore = 0;

    /** @var string Display name (first name + masked surname) */
    public string $DisplayName = '';

    /** @var string Masked email (e.g., "j***@gmail.com") */
    public string $MaskedEmail = '';

    /** @var string Masked NIC (e.g., "123****89V") */
    public string $MaskedNIC = '';

    /** @var string Masked phone (e.g., "****** 4567") */
    public string $MaskedPhone = '';

    /** @var string Location (city, country only) */
    public string $Location = '';

    /** @var string Match context (name, nic, email, phone) */
    public string $MatchContext = 'name';

    /** @var string|null Customer reference number */
    public ?string $CustomerReference = null;

    /** @var bool Whether email is verified */
    public bool $IsVerified = false;

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->ID,
            'matchScore' => $this->MatchScore,
            'displayName' => $this->DisplayName,
            'maskedEmail' => $this->MaskedEmail,
            'maskedNIC' => $this->MaskedNIC,
            'maskedPhone' => $this->MaskedPhone,
            'location' => $this->Location,
            'matchContext' => $this->MatchContext,
            'customerReference' => $this->CustomerReference,
            'isVerified' => $this->IsVerified,
            'type' => 'individual'
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
        $dto->MaskedEmail = $data['maskedEmail'] ?? '';
        $dto->MaskedNIC = $data['maskedNIC'] ?? '';
        $dto->MaskedPhone = $data['maskedPhone'] ?? '';
        $dto->Location = $data['location'] ?? '';
        $dto->MatchContext = $data['matchContext'] ?? 'name';
        $dto->CustomerReference = $data['customerReference'] ?? null;
        $dto->IsVerified = $data['isVerified'] ?? false;
        return $dto;
    }
}
