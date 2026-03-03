<?php

namespace LKDomains\Utils;

/**
 * Data Masking Utility
 * 
 * Privacy-compliant masking functions for sensitive data.
 * 
 * Key Principle: Show enough to identify ("Yes, that's me") 
 * but not enough for identity theft.
 */
class DataMaskingUtil
{
    /**
     * Mask an email address
     * 
     * Examples:
     * - "john.doe@gmail.com" -> "j****@gmail.com"
     * - "test@example.org" -> "t***@example.org"
     * - "a@b.com" -> "a@b.com" (too short to mask)
     */
    public static function maskEmail(?string $email): string
    {
        if (empty($email)) {
            return '';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Mask local part
        if (strlen($local) <= 2) {
            $maskedLocal = $local;
        } elseif (strlen($local) <= 4) {
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', strlen($local) - 1);
        } else {
            $maskedLocal = substr($local, 0, 2) . str_repeat('*', strlen($local) - 2);
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask a NIC number (Sri Lankan ID)
     * 
     * Examples:
     * - "123456789V" -> "123****89V"
     * - "200012345678" -> "2000****5678"
     */
    public static function maskNIC(?string $nic): string
    {
        if (empty($nic)) {
            return '';
        }

        $nic = strtoupper(preg_replace('/\s/', '', $nic));
        $len = strlen($nic);

        if ($len <= 4) {
            return $nic; // Too short to meaningfully mask
        }

        // Old format: 9 digits + V/X
        if (preg_match('/^\d{9}[VX]$/', $nic)) {
            return substr($nic, 0, 3) . '****' . substr($nic, 7);
        }

        // New format: 12 digits
        if (preg_match('/^\d{12}$/', $nic)) {
            return substr($nic, 0, 4) . '****' . substr($nic, 8);
        }

        // Generic masking for other formats
        $showFirst = min(3, (int)($len * 0.3));
        $showLast = min(3, (int)($len * 0.3));
        $maskLen = $len - $showFirst - $showLast;

        return substr($nic, 0, $showFirst) . str_repeat('*', $maskLen) . substr($nic, -$showLast);
    }

    /**
     * Mask a phone number
     * 
     * Examples:
     * - "0771234567" -> "****** 4567"
     * - "+94771234567" -> "****** 4567"
     */
    public static function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-digits
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);

        if ($len <= 4) {
            return $phone; // Too short to mask
        }

        // Show last 4 digits
        $lastFour = substr($digits, -4);
        return '****** ' . $lastFour;
    }

    /**
     * Mask a name (typically surname)
     * 
     * Examples:
     * - "Perera" -> "P***ra"
     * - "De Silva" -> "De S***a"
     * - "Wu" -> "W*"
     */
    public static function maskName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $name = trim($name);
        $len = strlen($name);

        if ($len <= 2) {
            return substr($name, 0, 1) . '*';
        }

        if ($len <= 4) {
            return substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
        }

        // For longer names, show first 2 and last 2 characters
        return substr($name, 0, 2) . str_repeat('*', $len - 4) . substr($name, -2);
    }

    /**
     * Mask a Business Registration number
     * 
     * Examples:
     * - "PV12345678" -> "PV***678"
     * - "123456" -> "***456"
     */
    public static function maskBRNumber(?string $brNumber): string
    {
        if (empty($brNumber)) {
            return '';
        }

        $brNumber = strtoupper(preg_replace('/\s/', '', $brNumber));
        $len = strlen($brNumber);

        if ($len <= 4) {
            return $brNumber;
        }

        // Check for prefix (PV, PB, GA, etc.)
        if (preg_match('/^([A-Z]{2})(\d+)$/', $brNumber, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $numLen = strlen($number);
            
            if ($numLen <= 3) {
                return $prefix . '***';
            }
            
            return $prefix . str_repeat('*', $numLen - 3) . substr($number, -3);
        }

        // Generic masking for pure numeric
        return str_repeat('*', $len - 3) . substr($brNumber, -3);
    }

    /**
     * Mask an address (show only city/region, not full address)
     */
    public static function maskAddress(?string $address): string
    {
        if (empty($address)) {
            return '';
        }

        // Replace with just indication that address exists
        return '[Address on file]';
    }

    /**
     * Mask a passport number
     * 
     * Examples:
     * - "N1234567" -> "N****567"
     */
    public static function maskPassport(?string $passport): string
    {
        if (empty($passport)) {
            return '';
        }

        $passport = strtoupper(preg_replace('/\s/', '', $passport));
        $len = strlen($passport);

        if ($len <= 4) {
            return str_repeat('*', $len - 1) . substr($passport, -1);
        }

        // Show first character and last 3
        return substr($passport, 0, 1) . str_repeat('*', $len - 4) . substr($passport, -3);
    }

    /**
     * Mask a date of birth
     * Shows only year
     */
    public static function maskDOB(?string $dob): string
    {
        if (empty($dob)) {
            return '';
        }

        // Try to extract year
        if (preg_match('/(\d{4})/', $dob, $matches)) {
            return 'Born ' . $matches[1];
        }

        return '****';
    }

    /**
     * Get a masked preview of any field based on field type
     */
    public static function maskField(string $fieldName, ?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $maskFunctions = [
            'Email' => 'maskEmail',
            'NIC' => 'maskNIC',
            'Passport' => 'maskPassport',
            'MobileTelephone' => 'maskPhone',
            'Telephone' => 'maskPhone',
            'Phone' => 'maskPhone',
            'Surname' => 'maskName',
            'LastName' => 'maskName',
            'Address1' => 'maskAddress',
            'Address2' => 'maskAddress',
            'Address3' => 'maskAddress',
            'Address' => 'maskAddress',
            'RegistrationNumber' => 'maskBRNumber',
            'BRNumber' => 'maskBRNumber',
            'DOB' => 'maskDOB',
            'DateOfBirth' => 'maskDOB',
        ];

        $method = $maskFunctions[$fieldName] ?? null;
        
        if ($method && method_exists(self::class, $method)) {
            return self::$method($value);
        }

        // Default: show first and last character with asterisks
        $len = strlen($value);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }
        
        return substr($value, 0, 1) . str_repeat('*', $len - 2) . substr($value, -1);
    }

    /**
     * Determine if a value should be masked based on field sensitivity
     */
    public static function isSensitiveField(string $fieldName): bool
    {
        $sensitiveFields = [
            'NIC', 'Passport', 'Password', 'Salt', 'AutoLoginHash',
            'Email', 'MobileTelephone', 'Telephone', 'Fax',
            'Address1', 'Address2', 'Address3',
            'RegistrationNumber', 'TaxNumber',
            'DOB', 'DateOfBirth',
            'BankAccount', 'CreditCard'
        ];

        return in_array($fieldName, $sensitiveFields, true);
    }
}
