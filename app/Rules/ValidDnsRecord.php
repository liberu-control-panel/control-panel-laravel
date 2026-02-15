<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidDnsRecord implements Rule
{
    protected $recordType;
    protected $errorMessage;

    /**
     * Create a new rule instance.
     *
     * @param  string  $recordType
     * @return void
     */
    public function __construct($recordType)
    {
        $this->recordType = $recordType;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        switch ($this->recordType) {
            case 'A':
                return $this->validateARecord($value);
            
            case 'AAAA':
                return $this->validateAAAARecord($value);
            
            case 'CNAME':
            case 'NS':
            case 'MX':
                return $this->validateHostname($value);
            
            case 'TXT':
                return $this->validateTxtRecord($value);
            
            case 'PTR':
                return $this->validateHostname($value);
            
            case 'SRV':
                return $this->validateHostname($value);
            
            default:
                $this->errorMessage = 'Unknown DNS record type.';
                return false;
        }
    }

    /**
     * Validate A record (IPv4 address)
     */
    protected function validateARecord($value)
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }
        
        $this->errorMessage = 'The value must be a valid IPv4 address (e.g., 192.0.2.1).';
        return false;
    }

    /**
     * Validate AAAA record (IPv6 address)
     */
    protected function validateAAAARecord($value)
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        }
        
        $this->errorMessage = 'The value must be a valid IPv6 address (e.g., 2001:0db8::1).';
        return false;
    }

    /**
     * Validate hostname (for CNAME, NS, MX, PTR, SRV)
     */
    protected function validateHostname($value)
    {
        // Remove trailing dot if present (FQDN format)
        $value = rtrim($value, '.');
        
        // Hostname can be composed of labels separated by dots
        $labels = explode('.', $value);
        
        foreach ($labels as $label) {
            // Each label must be 1-63 characters
            if (strlen($label) < 1 || strlen($label) > 63) {
                $this->errorMessage = 'Each label in the hostname must be 1-63 characters.';
                return false;
            }
            
            // Label must start and end with alphanumeric
            if (!preg_match('/^[a-zA-Z0-9]/', $label) || !preg_match('/[a-zA-Z0-9]$/', $label)) {
                $this->errorMessage = 'Each label must start and end with an alphanumeric character.';
                return false;
            }
            
            // Label can only contain alphanumeric and hyphens
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $label)) {
                $this->errorMessage = 'Each label can only contain alphanumeric characters and hyphens.';
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate TXT record
     */
    protected function validateTxtRecord($value)
    {
        // TXT records can contain any text, but should be reasonable length
        if (strlen($value) > 512) {
            $this->errorMessage = 'TXT record value is too long (maximum 512 characters).';
            return false;
        }
        
        // TXT records should typically be printable ASCII
        if (!mb_check_encoding($value, 'ASCII')) {
            $this->errorMessage = 'TXT record should contain only ASCII characters.';
            return false;
        }
        
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->errorMessage ?? 'The :attribute is not valid for this DNS record type.';
    }
}
