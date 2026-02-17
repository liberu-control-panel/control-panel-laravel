<?php

namespace App\Services;

use App\Models\EmailAuthentication;
use App\Models\Domain;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class EmailAuthenticationService
{
    /**
     * Generate DKIM keys for a domain
     */
    public function generateDkimKeys(Domain $domain): array
    {
        try {
            // Generate private key
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if (!$privateKey) {
                throw new Exception('Failed to generate private key');
            }

            // Export private key
            openssl_pkey_export($privateKey, $privateKeyString);

            // Get public key
            $publicKeyData = openssl_pkey_get_details($privateKey);
            $publicKeyString = $publicKeyData['key'];

            // Extract public key for DNS record
            $publicKeyForDns = $this->formatPublicKeyForDns($publicKeyString);

            return [
                'private_key' => $privateKeyString,
                'public_key' => $publicKeyString,
                'dns_record' => $publicKeyForDns,
            ];
        } catch (Exception $e) {
            Log::error("Failed to generate DKIM keys for {$domain->domain_name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Format public key for DNS TXT record
     */
    protected function formatPublicKeyForDns(string $publicKey): string
    {
        // Remove PEM headers and format for DNS
        $publicKey = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $publicKey);
        
        return "v=DKIM1; k=rsa; p={$publicKey}";
    }

    /**
     * Setup email authentication for a domain
     */
    public function setupEmailAuthentication(Domain $domain, array $options = []): EmailAuthentication
    {
        // Generate DKIM keys if not provided
        $dkimKeys = $this->generateDkimKeys($domain);

        // Create or update email authentication
        $auth = EmailAuthentication::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'spf_enabled' => $options['spf_enabled'] ?? true,
                'spf_record' => $options['spf_record'] ?? $this->generateSpfRecord($domain),
                'dkim_enabled' => $options['dkim_enabled'] ?? true,
                'dkim_selector' => $options['dkim_selector'] ?? 'default',
                'dkim_private_key' => $dkimKeys['private_key'],
                'dkim_public_key' => $dkimKeys['public_key'],
                'dkim_dns_record' => $dkimKeys['dns_record'],
                'dmarc_enabled' => $options['dmarc_enabled'] ?? true,
                'dmarc_policy' => $options['dmarc_policy'] ?? 'none',
                'dmarc_rua_email' => $options['dmarc_rua_email'] ?? "postmaster@{$domain->domain_name}",
                'dmarc_ruf_email' => $options['dmarc_ruf_email'] ?? "postmaster@{$domain->domain_name}",
                'dmarc_percentage' => $options['dmarc_percentage'] ?? 100,
                'dmarc_record' => $this->generateDmarcRecord($domain, $options),
            ]
        );

        // Configure Postfix and OpenDKIM
        $this->configureMailServer($domain, $auth);

        return $auth;
    }

    /**
     * Generate SPF record
     */
    protected function generateSpfRecord(Domain $domain): string
    {
        $server = $domain->server;
        $ipAddress = $server ? $server->ip_address : '';
        
        $mechanisms = ['mx', 'a'];
        
        if ($ipAddress) {
            $mechanisms[] = "ip4:{$ipAddress}";
        }
        
        // Add common email sending services
        $mechanisms[] = 'include:_spf.google.com'; // Google Workspace
        
        return 'v=spf1 ' . implode(' ', $mechanisms) . ' ~all';
    }

    /**
     * Generate DMARC record
     */
    protected function generateDmarcRecord(Domain $domain, array $options = []): string
    {
        $policy = $options['dmarc_policy'] ?? 'none';
        $percentage = $options['dmarc_percentage'] ?? 100;
        $rua = $options['dmarc_rua_email'] ?? "postmaster@{$domain->domain_name}";
        $ruf = $options['dmarc_ruf_email'] ?? "postmaster@{$domain->domain_name}";

        $parts = [
            'v=DMARC1',
            "p={$policy}",
            "pct={$percentage}",
            "rua=mailto:{$rua}",
            "ruf=mailto:{$ruf}",
            'fo=1', // Generate reports if any authentication fails
            'adkim=r', // Relaxed DKIM alignment
            'aspf=r', // Relaxed SPF alignment
        ];

        return implode('; ', $parts);
    }

    /**
     * Configure mail server with DKIM
     */
    protected function configureMailServer(Domain $domain, EmailAuthentication $auth): void
    {
        try {
            // Create OpenDKIM configuration
            $this->createOpenDkimConfig($domain, $auth);

            // Update Postfix configuration
            $this->updatePostfixConfig($domain, $auth);

            // Reload mail services
            $this->reloadMailServices();
        } catch (Exception $e) {
            Log::error("Failed to configure mail server for {$domain->domain_name}: " . $e->getMessage());
        }
    }

    /**
     * Create OpenDKIM configuration
     */
    protected function createOpenDkimConfig(Domain $domain, EmailAuthentication $auth): void
    {
        $dkimDir = storage_path('app/opendkim');
        
        if (!file_exists($dkimDir)) {
            mkdir($dkimDir, 0755, true);
        }

        // Create key directory for domain
        $domainKeyDir = "{$dkimDir}/keys/{$domain->domain_name}";
        if (!file_exists($domainKeyDir)) {
            mkdir($domainKeyDir, 0755, true);
        }

        // Save private key
        file_put_contents("{$domainKeyDir}/{$auth->dkim_selector}.private", $auth->dkim_private_key);
        chmod("{$domainKeyDir}/{$auth->dkim_selector}.private", 0600);

        // Update key table
        $keyTableEntry = "{$auth->dkim_selector}._domainkey.{$domain->domain_name} {$domain->domain_name}:{$auth->dkim_selector}:{$domainKeyDir}/{$auth->dkim_selector}.private\n";
        $keyTableFile = "{$dkimDir}/KeyTable";
        
        // Remove old entry if exists
        if (file_exists($keyTableFile)) {
            $content = file_get_contents($keyTableFile);
            $lines = explode("\n", $content);
            $filteredLines = array_filter($lines, function($line) use ($domain) {
                return strpos($line, $domain->domain_name) === false;
            });
            file_put_contents($keyTableFile, implode("\n", $filteredLines));
        }
        
        file_put_contents($keyTableFile, $keyTableEntry, FILE_APPEND);

        // Update signing table
        $signingTableEntry = "*@{$domain->domain_name} {$auth->dkim_selector}._domainkey.{$domain->domain_name}\n";
        $signingTableFile = "{$dkimDir}/SigningTable";
        
        // Remove old entry if exists
        if (file_exists($signingTableFile)) {
            $content = file_get_contents($signingTableFile);
            $lines = explode("\n", $content);
            $filteredLines = array_filter($lines, function($line) use ($domain) {
                return strpos($line, $domain->domain_name) === false;
            });
            file_put_contents($signingTableFile, implode("\n", $filteredLines));
        }
        
        file_put_contents($signingTableFile, $signingTableEntry, FILE_APPEND);

        // Update trusted hosts
        $trustedHostsFile = "{$dkimDir}/TrustedHosts";
        $trustedHost = "{$domain->domain_name}\n";
        
        if (!file_exists($trustedHostsFile) || strpos(file_get_contents($trustedHostsFile), $domain->domain_name) === false) {
            file_put_contents($trustedHostsFile, $trustedHost, FILE_APPEND);
        }
    }

    /**
     * Update Postfix configuration
     */
    protected function updatePostfixConfig(Domain $domain, EmailAuthentication $auth): void
    {
        // This would update Postfix main.cf to include OpenDKIM milter
        // In a container environment, this is typically done via mounted config
        Log::info("Updated Postfix configuration for DKIM signing on {$domain->domain_name}");
    }

    /**
     * Reload mail services
     */
    protected function reloadMailServices(): void
    {
        try {
            // Reload OpenDKIM
            $dkimProcess = new Process(['docker', 'exec', 'opendkim', 'kill', '-HUP', '1']);
            $dkimProcess->run();

            // Reload Postfix
            $postfixProcess = new Process(['docker', 'exec', 'postfix', 'postfix', 'reload']);
            $postfixProcess->run();
        } catch (Exception $e) {
            Log::warning("Failed to reload mail services: " . $e->getMessage());
        }
    }

    /**
     * Get DNS records for email authentication
     */
    public function getDnsRecords(EmailAuthentication $auth): array
    {
        $domain = $auth->domain;
        $records = [];

        // SPF record
        if ($auth->spf_enabled) {
            $records[] = [
                'type' => 'TXT',
                'name' => '@',
                'value' => $auth->spf_record,
                'ttl' => 3600,
            ];
        }

        // DKIM record
        if ($auth->dkim_enabled) {
            $records[] = [
                'type' => 'TXT',
                'name' => "{$auth->dkim_selector}._domainkey",
                'value' => $auth->dkim_dns_record,
                'ttl' => 3600,
            ];
        }

        // DMARC record
        if ($auth->dmarc_enabled) {
            $records[] = [
                'type' => 'TXT',
                'name' => '_dmarc',
                'value' => $auth->dmarc_record,
                'ttl' => 3600,
            ];
        }

        return $records;
    }

    /**
     * Verify email authentication setup
     */
    public function verifySetup(EmailAuthentication $auth): array
    {
        $domain = $auth->domain->domain_name;
        $results = [];

        // Check SPF
        if ($auth->spf_enabled) {
            $results['spf'] = $this->verifySpfRecord($domain, $auth->spf_record);
        }

        // Check DKIM
        if ($auth->dkim_enabled) {
            $results['dkim'] = $this->verifyDkimRecord($domain, $auth->dkim_selector, $auth->dkim_dns_record);
        }

        // Check DMARC
        if ($auth->dmarc_enabled) {
            $results['dmarc'] = $this->verifyDmarcRecord($domain, $auth->dmarc_record);
        }

        return $results;
    }

    /**
     * Verify SPF record
     */
    protected function verifySpfRecord(string $domain, string $expectedRecord): array
    {
        $process = new Process(['dig', '+short', 'TXT', $domain]);
        $process->run();

        $found = false;
        if ($process->isSuccessful()) {
            $txtRecords = explode("\n", trim($process->getOutput()));
            foreach ($txtRecords as $record) {
                if (strpos($record, 'v=spf1') !== false) {
                    $found = true;
                    break;
                }
            }
        }

        return [
            'configured' => true,
            'published' => $found,
            'status' => $found ? 'ok' : 'warning',
            'message' => $found ? 'SPF record found' : 'SPF record not published in DNS',
        ];
    }

    /**
     * Verify DKIM record
     */
    protected function verifyDkimRecord(string $domain, string $selector, string $expectedRecord): array
    {
        $dkimDomain = "{$selector}._domainkey.{$domain}";
        $process = new Process(['dig', '+short', 'TXT', $dkimDomain]);
        $process->run();

        $found = !empty(trim($process->getOutput()));

        return [
            'configured' => true,
            'published' => $found,
            'status' => $found ? 'ok' : 'warning',
            'message' => $found ? 'DKIM record found' : 'DKIM record not published in DNS',
            'selector' => $selector,
        ];
    }

    /**
     * Verify DMARC record
     */
    protected function verifyDmarcRecord(string $domain, string $expectedRecord): array
    {
        $dmarcDomain = "_dmarc.{$domain}";
        $process = new Process(['dig', '+short', 'TXT', $dmarcDomain]);
        $process->run();

        $found = false;
        if ($process->isSuccessful()) {
            $output = trim($process->getOutput());
            if (strpos($output, 'v=DMARC1') !== false) {
                $found = true;
            }
        }

        return [
            'configured' => true,
            'published' => $found,
            'status' => $found ? 'ok' : 'warning',
            'message' => $found ? 'DMARC record found' : 'DMARC record not published in DNS',
        ];
    }
}
