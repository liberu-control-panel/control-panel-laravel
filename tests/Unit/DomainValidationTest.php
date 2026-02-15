<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Rules\ValidDomainName;
use App\Rules\ValidDnsRecord;

class DomainValidationTest extends TestCase
{
    /**
     * Test valid domain names
     */
    public function test_valid_domain_names()
    {
        $rule = new ValidDomainName();
        
        $validDomains = [
            'example.com',
            'subdomain.example.com',
            'test-domain.co.uk',
            'my-site.example.org',
            'test123.example.net',
        ];
        
        foreach ($validDomains as $domain) {
            $this->assertTrue(
                $rule->passes('domain', $domain),
                "Domain '{$domain}' should be valid"
            );
        }
    }
    
    /**
     * Test invalid domain names
     */
    public function test_invalid_domain_names()
    {
        $rule = new ValidDomainName();
        
        $invalidDomains = [
            'invalid',
            '-example.com',
            'example-.com',
            'example..com',
            'http://example.com',
            'example.com/',
            'example',
            'example.c',
            str_repeat('a', 64) . '.com', // Label too long
        ];
        
        foreach ($invalidDomains as $domain) {
            $this->assertFalse(
                $rule->passes('domain', $domain),
                "Domain '{$domain}' should be invalid"
            );
        }
    }
    
    /**
     * Test valid A records
     */
    public function test_valid_a_records()
    {
        $rule = new ValidDnsRecord('A');
        
        $validIps = [
            '192.0.2.1',
            '10.0.0.1',
            '172.16.0.1',
            '8.8.8.8',
            '255.255.255.255',
        ];
        
        foreach ($validIps as $ip) {
            $this->assertTrue(
                $rule->passes('value', $ip),
                "IPv4 '{$ip}' should be valid for A record"
            );
        }
    }
    
    /**
     * Test invalid A records
     */
    public function test_invalid_a_records()
    {
        $rule = new ValidDnsRecord('A');
        
        $invalidIps = [
            '256.0.0.1',
            '192.168.1',
            'not-an-ip',
            '2001:0db8::1', // IPv6
            '',
        ];
        
        foreach ($invalidIps as $ip) {
            $this->assertFalse(
                $rule->passes('value', $ip),
                "'{$ip}' should be invalid for A record"
            );
        }
    }
    
    /**
     * Test valid AAAA records
     */
    public function test_valid_aaaa_records()
    {
        $rule = new ValidDnsRecord('AAAA');
        
        $validIps = [
            '2001:0db8::1',
            '::1',
            '2001:0db8:85a3::8a2e:0370:7334',
            'fe80::1',
        ];
        
        foreach ($validIps as $ip) {
            $this->assertTrue(
                $rule->passes('value', $ip),
                "IPv6 '{$ip}' should be valid for AAAA record"
            );
        }
    }
    
    /**
     * Test invalid AAAA records
     */
    public function test_invalid_aaaa_records()
    {
        $rule = new ValidDnsRecord('AAAA');
        
        $invalidIps = [
            '192.0.2.1', // IPv4
            'not-an-ip',
            'gggg::1',
            '',
        ];
        
        foreach ($invalidIps as $ip) {
            $this->assertFalse(
                $rule->passes('value', $ip),
                "'{$ip}' should be invalid for AAAA record"
            );
        }
    }
    
    /**
     * Test valid CNAME records
     */
    public function test_valid_cname_records()
    {
        $rule = new ValidDnsRecord('CNAME');
        
        $validHostnames = [
            'example.com',
            'subdomain.example.com',
            'test-host.example.org',
            'host123.example.net',
        ];
        
        foreach ($validHostnames as $hostname) {
            $this->assertTrue(
                $rule->passes('value', $hostname),
                "Hostname '{$hostname}' should be valid for CNAME record"
            );
        }
    }
    
    /**
     * Test invalid CNAME records
     */
    public function test_invalid_cname_records()
    {
        $rule = new ValidDnsRecord('CNAME');
        
        $invalidHostnames = [
            '-invalid.com',
            'invalid-.com',
            'invalid..com',
            'invalid_host.com',
            '',
        ];
        
        foreach ($invalidHostnames as $hostname) {
            $this->assertFalse(
                $rule->passes('value', $hostname),
                "'{$hostname}' should be invalid for CNAME record"
            );
        }
    }
    
    /**
     * Test valid TXT records
     */
    public function test_valid_txt_records()
    {
        $rule = new ValidDnsRecord('TXT');
        
        $validTexts = [
            'v=spf1 include:_spf.example.com ~all',
            'verification-code-12345',
            'simple text',
            'v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQ',
        ];
        
        foreach ($validTexts as $text) {
            $this->assertTrue(
                $rule->passes('value', $text),
                "Text '{$text}' should be valid for TXT record"
            );
        }
    }
    
    /**
     * Test invalid TXT records
     */
    public function test_invalid_txt_records()
    {
        $rule = new ValidDnsRecord('TXT');
        
        // Text too long (> 512 characters)
        $longText = str_repeat('a', 513);
        
        $this->assertFalse(
            $rule->passes('value', $longText),
            "Text longer than 512 characters should be invalid"
        );
    }
    
    /**
     * Test valid MX records
     */
    public function test_valid_mx_records()
    {
        $rule = new ValidDnsRecord('MX');
        
        $validHostnames = [
            'mail.example.com',
            'mx1.example.org',
            'smtp.example.net',
        ];
        
        foreach ($validHostnames as $hostname) {
            $this->assertTrue(
                $rule->passes('value', $hostname),
                "Hostname '{$hostname}' should be valid for MX record"
            );
        }
    }
}
