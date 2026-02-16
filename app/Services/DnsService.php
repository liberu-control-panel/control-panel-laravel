<?php

namespace App\Services;

use Exception;
use App\Models\Domain;
use App\Models\DnsSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class DnsService
{
    protected $containerManager;
    protected $standaloneDnsService;
    protected $detectionService;

    public function __construct(
        ContainerManagerService $containerManager,
        StandaloneDnsService $standaloneDnsService,
        DeploymentDetectionService $detectionService
    ) {
        $this->containerManager = $containerManager;
        $this->standaloneDnsService = $standaloneDnsService;
        $this->detectionService = $detectionService;
    }

    /**
     * Create DNS zone for domain
     */
    public function createDnsZone(Domain $domain, array $options = []): bool
    {
        // Use standalone service if in standalone mode
        if ($this->detectionService->isStandalone()) {
            $result = $this->standaloneDnsService->createDnsZone($domain, $options);
            return $result['success'];
        }

        // Use container-based service
        try {
            $domainName = $domain->domain_name;
            $zoneFile = $this->generateZoneFile($domain, $options);

            // Save zone file
            $zonePath = "bind/zones/db.{$domainName}";
            Storage::disk('local')->put($zonePath, $zoneFile);

            // Update named.conf
            $this->updateNamedConf($domainName);

            // Reload BIND
            $this->reloadBind();

            // Create default DNS records
            $this->createDefaultDnsRecords($domain);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to create DNS zone for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate BIND zone file
     */
    protected function generateZoneFile(Domain $domain, array $options = []): string
    {
        $domainName = $domain->domain_name;
        $serial = date('Ymd') . '01'; // YYYYMMDDNN format
        $adminEmail = str_replace('@', '.', $options['admin_email'] ?? 'admin@' . $domainName);
        $ttl = $options['ttl'] ?? 86400;
        $ipAddress = $options['ip_address'] ?? '127.0.0.1';
        $ns1Ip = $options['ns1_ip'] ?? $ipAddress;
        $ns2Ip = $options['ns2_ip'] ?? $ipAddress;
        $mailIp = $options['mail_ip'] ?? $ipAddress;
        $ftpIp = $options['ftp_ip'] ?? $ipAddress;

        $zoneFile = <<<EOT
\$TTL {$ttl}
@   IN  SOA {$domainName}. {$adminEmail}. (
        {$serial}     ; Serial
        3600          ; Refresh
        1800          ; Retry
        604800        ; Expire
        86400         ; Minimum TTL
)

; Name servers
@   IN  NS  ns1.{$domainName}.
@   IN  NS  ns2.{$domainName}.

; A records
@   IN  A   {$ipAddress}
www IN  A   {$ipAddress}

; Name server A records
ns1 IN  A   {$ns1Ip}
ns2 IN  A   {$ns2Ip}

; Mail server records
@   IN  MX  10  mail.{$domainName}.
mail IN  A   {$mailIp}

; FTP server
ftp IN  A   {$ftpIp}

EOT;

        return $zoneFile;
    }

    /**
     * Update named.conf with new zone
     */
    protected function updateNamedConf(string $domainName): void
    {
        $namedConfPath = "bind/named.conf.local";
        $zoneEntry = <<<EOT

zone "{$domainName}" {
    type master;
    file "/etc/bind/zones/db.{$domainName}";
    allow-transfer { any; };
};

EOT;

        // Read existing content
        $content = Storage::disk('local')->exists($namedConfPath) 
            ? Storage::disk('local')->get($namedConfPath) 
            : '';

        // Add zone entry if not exists
        if (strpos($content, "zone \"{$domainName}\"") === false) {
            $content .= $zoneEntry;
            Storage::disk('local')->put($namedConfPath, $content);
        }
    }

    /**
     * Create default DNS records
     */
    protected function createDefaultDnsRecords(Domain $domain): void
    {
        $defaultRecords = [
            ['type' => 'A', 'name' => '@', 'value' => '127.0.0.1', 'ttl' => 3600],
            ['type' => 'A', 'name' => 'www', 'value' => '127.0.0.1', 'ttl' => 3600],
            ['type' => 'MX', 'name' => '@', 'value' => 'mail.' . $domain->domain_name, 'priority' => 10, 'ttl' => 3600],
            ['type' => 'CNAME', 'name' => 'mail', 'value' => '@', 'ttl' => 3600],
            ['type' => 'CNAME', 'name' => 'ftp', 'value' => '@', 'ttl' => 3600],
            ['type' => 'TXT', 'name' => '@', 'value' => 'v=spf1 a mx ~all', 'ttl' => 3600]
        ];

        foreach ($defaultRecords as $record) {
            DnsSetting::create([
                'domain_id' => $domain->id,
                'record_type' => $record['type'],
                'name' => $record['name'],
                'value' => $record['value'],
                'priority' => $record['priority'] ?? null,
                'ttl' => $record['ttl']
            ]);
        }
    }

    /**
     * Add DNS record
     */
    public function addDnsRecord(Domain $domain, array $recordData): ?DnsSetting
    {
        // Use standalone service if in standalone mode
        if ($this->detectionService->isStandalone()) {
            return $this->standaloneDnsService->addDnsRecord($domain, $recordData);
        }

        // Use container-based service
        try {
            $dnsRecord = DnsSetting::create([
                'domain_id' => $domain->id,
                'record_type' => $recordData['record_type'] ?? $recordData['type'],
                'name' => $recordData['name'],
                'value' => $recordData['value'],
                'priority' => $recordData['priority'] ?? null,
                'ttl' => $recordData['ttl'] ?? 3600
            ]);

            // Update zone file
            $this->updateZoneFile($domain);

            // Reload BIND
            $this->reloadBind();

            return $dnsRecord;
        } catch (Exception $e) {
            Log::error("Failed to add DNS record for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update DNS record
     */
    public function updateDnsRecord(DnsSetting $dnsRecord, array $data): bool
    {
        // Use standalone service if in standalone mode
        if ($this->detectionService->isStandalone()) {
            return $this->standaloneDnsService->updateDnsRecord($dnsRecord, $data);
        }

        // Use container-based service
        try {
            $dnsRecord->update($data);

            // Update zone file
            $this->updateZoneFile($dnsRecord->domain);

            // Reload BIND
            $this->reloadBind();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to update DNS record {$dnsRecord->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete DNS record
     */
    public function deleteDnsRecord(DnsSetting $dnsRecord): bool
    {
        // Use standalone service if in standalone mode
        if ($this->detectionService->isStandalone()) {
            return $this->standaloneDnsService->deleteDnsRecord($dnsRecord);
        }

        // Use container-based service
        try {
            $domain = $dnsRecord->domain;
            $dnsRecord->delete();

            // Update zone file
            $this->updateZoneFile($domain);

            // Reload BIND
            $this->reloadBind();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete DNS record {$dnsRecord->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update zone file with current DNS records
     */
    protected function updateZoneFile(Domain $domain): void
    {
        $domainName = $domain->domain_name;
        $dnsRecords = $domain->dnsSettings;

        // Generate zone file header
        $serial = date('Ymd') . str_pad(date('H'), 2, '0', STR_PAD_LEFT);
        $zoneFile = <<<EOT
\$TTL 86400
@   IN  SOA {$domainName}. admin.{$domainName}. (
        {$serial}     ; Serial
        3600          ; Refresh
        1800          ; Retry
        604800        ; Expire
        86400         ; Minimum TTL
)

; Name servers
@   IN  NS  ns1.{$domainName}.
@   IN  NS  ns2.{$domainName}.

EOT;

        // Add DNS records
        foreach ($dnsRecords as $record) {
            $name = $record->name === '@' ? '@' : $record->name;
            $ttl = $record->ttl ?: 3600;

            if ($record->record_type === 'MX') {
                $zoneFile .= sprintf("%-20s %d IN  %-5s %d %s\n", 
                    $name, $ttl, $record->record_type, $record->priority, $record->value);
            } else {
                $value = $record->record_type === 'TXT' ? "\"{$record->value}\"" : $record->value;
                $zoneFile .= sprintf("%-20s %d IN  %-5s %s\n", 
                    $name, $ttl, $record->record_type, $value);
            }
        }

        // Save updated zone file
        $zonePath = "bind/zones/db.{$domainName}";
        Storage::disk('local')->put($zonePath, $zoneFile);
    }

    /**
     * Reload BIND DNS server
     */
    protected function reloadBind(): bool
    {
        try {
            $process = new Process(['docker', 'exec', 'bind9', 'rndc', 'reload']);
            $process->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            Log::error("Failed to reload BIND: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test DNS resolution
     */
    public function testDnsResolution(Domain $domain, string $recordType = 'A'): array
    {
        try {
            $domainName = $domain->domain_name;

            // Test using dig command
            $process = new Process(['dig', '+short', $recordType, $domainName]);
            $process->run();

            $result = [
                'domain' => $domainName,
                'type' => $recordType,
                'success' => $process->isSuccessful(),
                'response' => trim($process->getOutput()),
                'error' => $process->getErrorOutput()
            ];

            // Additional tests for common record types
            if ($recordType === 'A') {
                $result['tests'] = [
                    'A' => $this->testSingleRecord($domainName, 'A'),
                    'AAAA' => $this->testSingleRecord($domainName, 'AAAA'),
                    'MX' => $this->testSingleRecord($domainName, 'MX'),
                    'TXT' => $this->testSingleRecord($domainName, 'TXT'),
                    'NS' => $this->testSingleRecord($domainName, 'NS')
                ];
            }

            return $result;
        } catch (Exception $e) {
            return [
                'domain' => $domain->domain_name,
                'type' => $recordType,
                'success' => false,
                'response' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test single DNS record
     */
    protected function testSingleRecord(string $domain, string $type): array
    {
        try {
            $process = new Process(['dig', '+short', $type, $domain]);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'response' => trim($process->getOutput()),
                'error' => $process->getErrorOutput()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'response' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate DNS record
     */
    public function validateDnsRecord(array $recordData): array
    {
        $errors = [];

        // Validate record type
        $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'PTR', 'SRV'];
        if (!in_array($recordData['type'], $validTypes)) {
            $errors[] = 'Invalid record type';
        }

        // Validate name
        if (empty($recordData['name'])) {
            $errors[] = 'Record name is required';
        }

        // Validate value based on type
        switch ($recordData['type']) {
            case 'A':
                if (!filter_var($recordData['value'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $errors[] = 'Invalid IPv4 address';
                }
                break;

            case 'AAAA':
                if (!filter_var($recordData['value'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $errors[] = 'Invalid IPv6 address';
                }
                break;

            case 'MX':
                if (empty($recordData['priority']) || !is_numeric($recordData['priority'])) {
                    $errors[] = 'MX record requires numeric priority';
                }
                break;

            case 'CNAME':
                if (!preg_match('/^[a-zA-Z0-9.-]+$/', $recordData['value'])) {
                    $errors[] = 'Invalid CNAME value';
                }
                break;
        }

        // Validate TTL
        if (isset($recordData['ttl']) && (!is_numeric($recordData['ttl']) || $recordData['ttl'] < 60)) {
            $errors[] = 'TTL must be numeric and at least 60 seconds';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get DNS propagation status
     */
    public function getDnsPropagationStatus(Domain $domain): array
    {
        try {
            $domainName = $domain->domain_name;
            $nameservers = [
                '8.8.8.8',      // Google
                '1.1.1.1',      // Cloudflare
                '208.67.222.222', // OpenDNS
                '9.9.9.9'       // Quad9
            ];

            $results = [];

            foreach ($nameservers as $ns) {
                $process = new Process(['dig', '@' . $ns, '+short', 'A', $domainName]);
                $process->run();

                $results[$ns] = [
                    'nameserver' => $ns,
                    'success' => $process->isSuccessful(),
                    'response' => trim($process->getOutput()),
                    'propagated' => $process->isSuccessful() && !empty(trim($process->getOutput()))
                ];
            }

            $propagatedCount = count(array_filter($results, function($result) {
                return $result['propagated'];
            }));

            return [
                'domain' => $domainName,
                'total_nameservers' => count($nameservers),
                'propagated_count' => $propagatedCount,
                'propagation_percentage' => round(($propagatedCount / count($nameservers)) * 100, 2),
                'fully_propagated' => $propagatedCount === count($nameservers),
                'results' => $results
            ];
        } catch (Exception $e) {
            Log::error("Failed to check DNS propagation for {$domain->domain_name}: " . $e->getMessage());
            return [
                'domain' => $domain->domain_name,
                'total_nameservers' => 0,
                'propagated_count' => 0,
                'propagation_percentage' => 0,
                'fully_propagated' => false,
                'results' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create reverse DNS zone
     */
    public function createReverseDnsZone(string $ipAddress, string $domain): bool
    {
        try {
            // Convert IP to reverse format (e.g., 192.168.1.1 -> 1.1.168.192.in-addr.arpa)
            $ipParts = explode('.', $ipAddress);
            $reverseIp = implode('.', array_reverse($ipParts));
            $reverseZone = $reverseIp . '.in-addr.arpa';

            $zoneFile = <<<EOT
\$TTL 86400
@   IN  SOA {$domain}. admin.{$domain}. (
        {$this->generateSerial()}     ; Serial
        3600          ; Refresh
        1800          ; Retry
        604800        ; Expire
        86400         ; Minimum TTL
)

@   IN  NS  ns1.{$domain}.
@   IN  NS  ns2.{$domain}.

1   IN  PTR {$domain}.

EOT;

            // Save reverse zone file
            $zonePath = "bind/zones/db.{$reverseZone}";
            Storage::disk('local')->put($zonePath, $zoneFile);

            // Update named.conf
            $this->updateNamedConfReverse($reverseZone);

            // Reload BIND
            $this->reloadBind();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to create reverse DNS zone for {$ipAddress}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update named.conf with reverse zone
     */
    protected function updateNamedConfReverse(string $reverseZone): void
    {
        $namedConfPath = "bind/named.conf.local";
        $zoneEntry = <<<EOT

zone "{$reverseZone}" {
    type master;
    file "/etc/bind/zones/db.{$reverseZone}";
    allow-transfer { any; };
};

EOT;

        // Read existing content
        $content = Storage::disk('local')->exists($namedConfPath) 
            ? Storage::disk('local')->get($namedConfPath) 
            : '';

        // Add zone entry if not exists
        if (strpos($content, "zone \"{$reverseZone}\"") === false) {
            $content .= $zoneEntry;
            Storage::disk('local')->put($namedConfPath, $content);
        }
    }

    /**
     * Generate serial number for zone files
     */
    protected function generateSerial(): string
    {
        return date('Ymd') . str_pad(date('H'), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Delete DNS zone
     */
    public function deleteDnsZone(Domain $domain): bool
    {
        try {
            $domainName = $domain->domain_name;

            // Remove zone file
            $zonePath = "bind/zones/db.{$domainName}";
            Storage::disk('local')->delete($zonePath);

            // Remove from named.conf
            $this->removeFromNamedConf($domainName);

            // Delete DNS records
            $domain->dnsSettings()->delete();

            // Reload BIND
            $this->reloadBind();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete DNS zone for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove zone from named.conf
     */
    protected function removeFromNamedConf(string $domainName): void
    {
        $namedConfPath = "bind/named.conf.local";

        if (Storage::disk('local')->exists($namedConfPath)) {
            $content = Storage::disk('local')->get($namedConfPath);

            // Remove zone block
            $pattern = '/zone\s+"' . preg_quote($domainName, '/') . '"\s*\{[^}]*\};\s*/';
            $content = preg_replace($pattern, '', $content);

            Storage::disk('local')->put($namedConfPath, $content);
        }
    }
}