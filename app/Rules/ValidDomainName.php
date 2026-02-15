<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidDomainName implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Remove protocol if present
        $value = preg_replace('#^https?://#', '', $value);
        
        // Remove trailing slash
        $value = rtrim($value, '/');
        
        // Check if the domain is valid
        // Domain name rules:
        // - Must start and end with alphanumeric character
        // - Can contain hyphens but not at start or end
        // - Labels separated by dots
        // - Each label 1-63 characters
        // - TLD must be at least 2 characters
        // - Total length max 253 characters
        
        if (strlen($value) > 253) {
            return false;
        }
        
        // Split domain into labels
        $labels = explode('.', $value);
        
        // Must have at least 2 labels (domain.tld)
        if (count($labels) < 2) {
            return false;
        }
        
        foreach ($labels as $label) {
            // Each label must be 1-63 characters
            if (strlen($label) < 1 || strlen($label) > 63) {
                return false;
            }
            
            // Label must start and end with alphanumeric
            if (!preg_match('/^[a-zA-Z0-9]/', $label) || !preg_match('/[a-zA-Z0-9]$/', $label)) {
                return false;
            }
            
            // Label can only contain alphanumeric and hyphens
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $label)) {
                return false;
            }
        }
        
        // TLD (last label) must be at least 2 characters and only letters
        $tld = end($labels);
        if (strlen($tld) < 2 || !preg_match('/^[a-zA-Z]+$/', $tld)) {
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
        return 'The :attribute must be a valid domain name (e.g., example.com).';
    }
}
