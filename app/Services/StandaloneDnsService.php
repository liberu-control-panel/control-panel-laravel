<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DnsSetting;
use Exception;
use Illuminate\Support\Facades\Log;

class StandaloneDnsService
{
    protected StandaloneServiceHelper $helper;
    protected DeploymentDetectionService $detectionService;

    public function __construct(
        StandaloneServiceHelper $helper,
        DeploymentDetectionService $detectionService
    ) {
        $this->helper = $helper;
        $this->detectionService = $detectionService;
    }

    /**
     * Check if we should use standalone mode
     */
    public function shouldUseStandaloneMode(): bool
    {
        return $this->detectionService->isStandalone();
    }

    /**
     * Create DNS zone for domain
     */
    public function createDnsZone(Domain $domain, array $options = []): array
    {
        try {
            $domainName = $domain->domain_name;
            $zoneFile = $this->generateZoneFile($domain, $options);

            // Save zone file
            $zonePath = "/etc/bind/zones/db.{$domainName}";
            $this->saveZoneFile($zonePath, $zoneFile);

            // Update named.conf.local
            $this->addZoneToNamedConf($domainName, $zonePath);

            // Check BIND configuration
            $checkResult = $this->checkBindConfiguration();
            if (!$checkResult['success']) {
                throw new Exception('BIND configuration check failed: ' . $checkResult['error']);
            }

            // Reload BIND
            $this->reloadBind();

            // Create default DNS records in database
            $this->createDefaultDnsRecords($domain, $options);

            return [
                'success' => true,
                'message' => 'DNS zone created successfully',
                'zone_file' => $zonePath,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create DNS zone: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create DNS zone: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete DNS zone
     */
    public function deleteDnsZone(Domain $domain): array
    {
        try {
            $domainName = $domain->domain_name;
            $zonePath = "/etc/bind/zones/db.{$domainName}";

            // Remove zone from named.conf.local
            $this->removeZoneFromNamedConf($domainName);

            // Delete zone file
            $this->helper->executeCommand(['sudo', 'rm', '-f', $zonePath]);

            // Reload BIND
            $this->reloadBind();

            // Delete DNS records from database
            DnsSetting::where('domain_id', $domain->id)->delete();

            return [
                'success' => true,
                'message' => 'DNS zone deleted successfully',
            ];
        } catch (Exception $e) {
            Log::error('Failed to delete DNS zone: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete DNS zone: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Add DNS record
     */
    public function addDnsRecord(Domain $domain, array $data): ?DnsSetting
    {
        try {
            // Create database record
            $dnsSetting = DnsSetting::create([
                'domain_id' => $domain->id,
                'record_type' => $data['record_type'],
                'name' => $data['name'],
                'value' => $data['value'],
                'ttl' => $data['ttl'] ?? 3600,
                'priority' => $data['priority'] ?? null,
            ]);

            // Regenerate zone file
            $this->regenerateZoneFile($domain);

            // Reload BIND
            $this->reloadBind();

            return $dnsSetting;
        } catch (Exception $e) {
            Log::error('Failed to add DNS record: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update DNS record
     */
    public function updateDnsRecord(DnsSetting $dnsSetting, array $data): bool
    {
        try {
            // Update database record
            $dnsSetting->update($data);

            // Regenerate zone file
            $this->regenerateZoneFile($dnsSetting->domain);

            // Reload BIND
            $this->reloadBind();

            return true;
        } catch (Exception $e) {
            Log::error('Failed to update DNS record: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete DNS record
     */
    public function deleteDnsRecord(DnsSetting $dnsSetting): bool
    {
        try {
            $domain = $dnsSetting->domain;
            
            // Delete database record
            $dnsSetting->delete();

            // Regenerate zone file
            $this->regenerateZoneFile($domain);

            // Reload BIND
            $this->reloadBind();

            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete DNS record: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate BIND zone file
     */
    protected function generateZoneFile(Domain $domain, array $options = []): string
    {
        $domainName = $domain->domain_name;
        
        // Generate RFC 1912 compliant serial number (YYYYMMDDnn format)
        $baseSerial = date('Ymd') . '01'; // Start with 01 for first update of the day
        
        // If we need to update multiple times in a day, we should increment the counter
        // For now, using a simple approach with the base serial
        $serial = $baseSerial;
        
        $adminEmail = str_replace('@', '.', $options['admin_email'] ?? "admin.{$domainName}.");
        $ttl = $options['ttl'] ?? 3600;
        $ipAddress = $options['ip_address'] ?? config('app.server_ip', '127.0.0.1');
        
        $zoneFile = <<<EOT
\$TTL {$ttl}
\$ORIGIN {$domainName}.
@   IN  SOA ns1.{$domainName}. {$adminEmail} (
        {$serial}     ; Serial
        3600          ; Refresh
        1800          ; Retry
        604800        ; Expire
        86400         ; Minimum TTL
)

; Name servers
@   IN  NS  ns1.{$domainName}.
@   IN  NS  ns2.{$domainName}.

; Name server A records
ns1 IN  A   {$ipAddress}
ns2 IN  A   {$ipAddress}

EOT;

        // Add existing DNS records from database
        $dnsRecords = DnsSetting::where('domain_id', $domain->id)->get();
        foreach ($dnsRecords as $record) {
            $zoneFile .= $this->formatDnsRecord($record);
        }

        return $zoneFile;
    }

    /**
     * Format DNS record for zone file
     */
    protected function formatDnsRecord(DnsSetting $record): string
    {
        $name = $record->name === '@' ? '@' : $record->name;
        $ttl = $record->ttl ?? 3600;
        $type = $record->record_type;
        $value = $record->value;

        // Add trailing dot for CNAME, MX, NS records if not already present
        if (in_array($type, ['CNAME', 'MX', 'NS']) && !str_ends_with($value, '.')) {
            $value .= '.';
        }

        if ($type === 'MX') {
            $priority = $record->priority ?? 10;
            return "{$name} {$ttl} IN {$type} {$priority} {$value}\n";
        }

        return "{$name} {$ttl} IN {$type} {$value}\n";
    }

    /**
     * Regenerate zone file from database records
     */
    protected function regenerateZoneFile(Domain $domain): void
    {
        $zoneFile = $this->generateZoneFile($domain);
        $zonePath = "/etc/bind/zones/db.{$domain->domain_name}";
        $this->saveZoneFile($zonePath, $zoneFile);
    }

    /**
     * Save zone file
     */
    protected function saveZoneFile(string $path, string $content): void
    {
        // Create zones directory if it doesn't exist
        $this->helper->executeCommand(['sudo', 'mkdir', '-p', '/etc/bind/zones']);

        // Write to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'bind_zone_');
        file_put_contents($tempFile, $content);

        // Move to destination
        $this->helper->executeCommand(['sudo', 'mv', $tempFile, $path]);

        // Set permissions
        $this->helper->executeCommand(['sudo', 'chown', 'bind:bind', $path]);
        $this->helper->executeCommand(['sudo', 'chmod', '644', $path]);
    }

    /**
     * Add zone to named.conf.local
     */
    protected function addZoneToNamedConf(string $domainName, string $zonePath): void
    {
        $zoneConfig = <<<EOT

zone "{$domainName}" {
    type master;
    file "{$zonePath}";
    allow-transfer { none; };
};

EOT;

        $namedConfLocal = '/etc/bind/named.conf.local';
        
        // Check if zone already exists
        $result = $this->helper->executeCommand([
            'grep', '-q', "zone \"{$domainName}\"", $namedConfLocal
        ]);

        // If zone doesn't exist, add it
        if (!$result['success']) {
            $tempFile = tempnam(sys_get_temp_dir(), 'named_conf_');
            file_put_contents($tempFile, $zoneConfig);
            
            $this->helper->executeCommand([
                'sudo', 'bash', '-c',
                "cat {$tempFile} >> {$namedConfLocal}"
            ]);
            
            unlink($tempFile);
        }
    }

    /**
     * Remove zone from named.conf.local
     */
    protected function removeZoneFromNamedConf(string $domainName): void
    {
        $namedConfLocal = '/etc/bind/named.conf.local';
        
        // Remove zone block using sed
        $this->helper->executeCommand([
            'sudo', 'sed', '-i',
            "/zone \"{$domainName}\"/,/^};/d",
            $namedConfLocal
        ]);
    }

    /**
     * Check BIND configuration
     */
    protected function checkBindConfiguration(): array
    {
        $result = $this->helper->executeCommand(['sudo', 'named-checkconf']);
        
        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    /**
     * Reload BIND service
     */
    protected function reloadBind(): void
    {
        if ($this->helper->isSystemdServiceRunning('named')) {
            $this->helper->reloadSystemdService('named');
        } elseif ($this->helper->isSystemdServiceRunning('bind9')) {
            $this->helper->reloadSystemdService('bind9');
        }
    }

    /**
     * Create default DNS records
     */
    protected function createDefaultDnsRecords(Domain $domain, array $options = []): void
    {
        $ipAddress = $options['ip_address'] ?? config('app.server_ip', '127.0.0.1');

        // Root A record
        DnsSetting::create([
            'domain_id' => $domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => $ipAddress,
            'ttl' => 3600,
        ]);

        // WWW A record
        DnsSetting::create([
            'domain_id' => $domain->id,
            'record_type' => 'A',
            'name' => 'www',
            'value' => $ipAddress,
            'ttl' => 3600,
        ]);

        // Mail MX record
        DnsSetting::create([
            'domain_id' => $domain->id,
            'record_type' => 'MX',
            'name' => '@',
            'value' => "mail.{$domain->domain_name}",
            'ttl' => 3600,
            'priority' => 10,
        ]);

        // Mail A record
        DnsSetting::create([
            'domain_id' => $domain->id,
            'record_type' => 'A',
            'name' => 'mail',
            'value' => $ipAddress,
            'ttl' => 3600,
        ]);
    }

    /**
     * Check if BIND is installed
     */
    public function isBindInstalled(): bool
    {
        return $this->helper->isServiceInstalled('named') 
            || $this->helper->isServiceInstalled('bind9');
    }

    /**
     * Check if BIND service is running
     */
    public function isBindRunning(): bool
    {
        return $this->helper->isSystemdServiceRunning('named')
            || $this->helper->isSystemdServiceRunning('bind9');
    }
}
