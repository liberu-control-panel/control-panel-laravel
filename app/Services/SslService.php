<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SslService
{
    protected $containerManager;
    protected $webServerService;

    public function __construct(ContainerManagerService $containerManager, WebServerService $webServerService)
    {
        $this->containerManager = $containerManager;
        $this->webServerService = $webServerService;
    }

    /**
     * Generate Let's Encrypt certificate
     */
    public function generateLetsEncryptCertificate(Domain $domain, array $options = []): ?SslCertificate
    {
        try {
            $domainName = $domain->domain_name;
            $email = $options['email'] ?? $domain->user->email;
            $includeWww = $options['include_www'] ?? true;

            $domains = [$domainName];
            if ($includeWww) {
                $domains[] = "www.{$domainName}";
            }

            // Add subdomains if requested
            if ($options['include_subdomains'] ?? false) {
                $subdomains = $domain->subdomains()->active()->pluck('subdomain')->toArray();
                foreach ($subdomains as $subdomain) {
                    $domains[] = "{$subdomain}.{$domainName}";
                }
            }

            $domainList = implode(',', $domains);

            // Generate certificate using Certbot
            $certbotProcess = new Process([
                'docker', 'run', '--rm',
                '-v', storage_path('app/ssl') . ':/etc/letsencrypt',
                '-v', storage_path('app/ssl/www') . ':/var/www/certbot',
                'certbot/certbot',
                'certonly',
                '--webroot',
                '--webroot-path=/var/www/certbot',
                '--email', $email,
                '--agree-tos',
                '--no-eff-email',
                '--force-renewal',
                '-d', $domainList
            ]);

            $certbotProcess->setTimeout(300); // 5 minutes timeout
            $certbotProcess->run();

            if (!$certbotProcess->isSuccessful()) {
                Log::error("Let's Encrypt certificate generation failed for {$domainName}: " . $certbotProcess->getErrorOutput());
                return null;
            }

            // Read certificate files
            $certPath = storage_path("app/ssl/live/{$domainName}");
            $certificate = file_get_contents("{$certPath}/fullchain.pem");
            $privateKey = file_get_contents("{$certPath}/privkey.pem");
            $chain = file_get_contents("{$certPath}/chain.pem");

            // Parse certificate info
            $certInfo = $this->parseCertificate($certificate);

            // Create SSL certificate record
            $sslCertificate = SslCertificate::create([
                'domain_id' => $domain->id,
                'certificate_authority' => SslCertificate::CA_LETSENCRYPT,
                'certificate' => $certificate,
                'private_key' => $privateKey,
                'chain' => $chain,
                'issued_at' => $certInfo['issued_at'],
                'expires_at' => $certInfo['expires_at'],
                'auto_renew' => true,
                'status' => SslCertificate::STATUS_ACTIVE
            ]);

            // Install certificate
            $this->installCertificate($domain, $sslCertificate);

            return $sslCertificate;
        } catch (\Exception $e) {
            Log::error("Failed to generate Let's Encrypt certificate for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Install custom certificate
     */
    public function installCustomCertificate(Domain $domain, array $certificateData): ?SslCertificate
    {
        try {
            $certificate = $certificateData['certificate'];
            $privateKey = $certificateData['private_key'];
            $chain = $certificateData['chain'] ?? '';

            // Validate certificate
            if (!$this->validateCertificate($certificate, $privateKey)) {
                throw new \Exception('Invalid certificate or private key');
            }

            // Parse certificate info
            $certInfo = $this->parseCertificate($certificate);

            // Create SSL certificate record
            $sslCertificate = SslCertificate::create([
                'domain_id' => $domain->id,
                'certificate_authority' => SslCertificate::CA_CUSTOM,
                'certificate' => $certificate,
                'private_key' => $privateKey,
                'chain' => $chain,
                'issued_at' => $certInfo['issued_at'],
                'expires_at' => $certInfo['expires_at'],
                'auto_renew' => false,
                'status' => SslCertificate::STATUS_ACTIVE
            ]);

            // Install certificate
            $this->installCertificate($domain, $sslCertificate);

            return $sslCertificate;
        } catch (\Exception $e) {
            Log::error("Failed to install custom certificate for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate self-signed certificate
     */
    public function generateSelfSignedCertificate(Domain $domain): ?SslCertificate
    {
        try {
            $domainName = $domain->domain_name;
            $keyPath = storage_path("app/ssl/{$domainName}.key");
            $certPath = storage_path("app/ssl/{$domainName}.crt");

            // Generate private key
            $keyProcess = new Process([
                'openssl', 'genrsa', '-out', $keyPath, '2048'
            ]);
            $keyProcess->run();

            if (!$keyProcess->isSuccessful()) {
                throw new \Exception('Failed to generate private key');
            }

            // Generate certificate
            $certProcess = new Process([
                'openssl', 'req', '-new', '-x509', '-key', $keyPath, '-out', $certPath, '-days', '365',
                '-subj', "/C=US/ST=State/L=City/O=Organization/CN={$domainName}"
            ]);
            $certProcess->run();

            if (!$certProcess->isSuccessful()) {
                throw new \Exception('Failed to generate certificate');
            }

            // Read certificate files
            $certificate = file_get_contents($certPath);
            $privateKey = file_get_contents($keyPath);

            // Parse certificate info
            $certInfo = $this->parseCertificate($certificate);

            // Create SSL certificate record
            $sslCertificate = SslCertificate::create([
                'domain_id' => $domain->id,
                'certificate_authority' => SslCertificate::CA_SELF_SIGNED,
                'certificate' => $certificate,
                'private_key' => $privateKey,
                'chain' => '',
                'issued_at' => $certInfo['issued_at'],
                'expires_at' => $certInfo['expires_at'],
                'auto_renew' => false,
                'status' => SslCertificate::STATUS_ACTIVE
            ]);

            // Install certificate
            $this->installCertificate($domain, $sslCertificate);

            // Clean up temporary files
            unlink($keyPath);
            unlink($certPath);

            return $sslCertificate;
        } catch (\Exception $e) {
            Log::error("Failed to generate self-signed certificate for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Install certificate in web server
     */
    protected function installCertificate(Domain $domain, SslCertificate $sslCertificate): bool
    {
        try {
            $domainName = $domain->domain_name;

            // Save certificate files
            $certDir = storage_path("app/nginx/certs");
            if (!is_dir($certDir)) {
                mkdir($certDir, 0755, true);
            }

            file_put_contents("{$certDir}/{$domainName}.crt", $sslCertificate->certificate);
            file_put_contents("{$certDir}/{$domainName}.key", $sslCertificate->private_key);

            if ($sslCertificate->chain) {
                file_put_contents("{$certDir}/{$domainName}.chain.pem", $sslCertificate->chain);
            }

            // Update Nginx configuration to enable SSL
            $this->webServerService->createNginxConfig($domain, [
                'enable_ssl' => true,
                'php_version' => '8.2'
            ]);

            // Reload Nginx
            return $this->webServerService->reloadNginx($domain);
        } catch (\Exception $e) {
            Log::error("Failed to install certificate for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Renew Let's Encrypt certificate
     */
    public function renewLetsEncryptCertificate(SslCertificate $sslCertificate): bool
    {
        try {
            $domain = $sslCertificate->domain;
            $domainName = $domain->domain_name;

            // Renew certificate using Certbot
            $renewProcess = new Process([
                'docker', 'run', '--rm',
                '-v', storage_path('app/ssl') . ':/etc/letsencrypt',
                '-v', storage_path('app/ssl/www') . ':/var/www/certbot',
                'certbot/certbot',
                'renew',
                '--cert-name', $domainName,
                '--force-renewal'
            ]);

            $renewProcess->setTimeout(300);
            $renewProcess->run();

            if (!$renewProcess->isSuccessful()) {
                Log::error("Certificate renewal failed for {$domainName}: " . $renewProcess->getErrorOutput());
                return false;
            }

            // Read updated certificate files
            $certPath = storage_path("app/ssl/live/{$domainName}");
            $certificate = file_get_contents("{$certPath}/fullchain.pem");
            $privateKey = file_get_contents("{$certPath}/privkey.pem");
            $chain = file_get_contents("{$certPath}/chain.pem");

            // Parse certificate info
            $certInfo = $this->parseCertificate($certificate);

            // Update SSL certificate record
            $sslCertificate->update([
                'certificate' => $certificate,
                'private_key' => $privateKey,
                'chain' => $chain,
                'issued_at' => $certInfo['issued_at'],
                'expires_at' => $certInfo['expires_at'],
                'status' => SslCertificate::STATUS_ACTIVE
            ]);

            // Reinstall certificate
            return $this->installCertificate($domain, $sslCertificate);
        } catch (\Exception $e) {
            Log::error("Failed to renew certificate for {$sslCertificate->domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate certificate and private key
     */
    protected function validateCertificate(string $certificate, string $privateKey): bool
    {
        try {
            // Parse certificate
            $certResource = openssl_x509_parse($certificate);
            if (!$certResource) {
                return false;
            }

            // Parse private key
            $keyResource = openssl_pkey_get_private($privateKey);
            if (!$keyResource) {
                return false;
            }

            // Check if certificate and private key match
            $publicKey = openssl_pkey_get_public($certificate);
            if (!$publicKey) {
                return false;
            }

            $privateKeyDetails = openssl_pkey_get_details($keyResource);
            $publicKeyDetails = openssl_pkey_get_details($publicKey);

            return $privateKeyDetails['key'] === $publicKeyDetails['key'];
        } catch (\Exception $e) {
            Log::error("Certificate validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse certificate information
     */
    protected function parseCertificate(string $certificate): array
    {
        try {
            $certData = openssl_x509_parse($certificate);

            return [
                'issued_at' => Carbon::createFromTimestamp($certData['validFrom_time_t']),
                'expires_at' => Carbon::createFromTimestamp($certData['validTo_time_t']),
                'subject' => $certData['subject'],
                'issuer' => $certData['issuer'],
                'serial_number' => $certData['serialNumber'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error("Failed to parse certificate: " . $e->getMessage());
            return [
                'issued_at' => now(),
                'expires_at' => now()->addYear(),
                'subject' => [],
                'issuer' => [],
                'serial_number' => null
            ];
        }
    }

    /**
     * Check certificate status
     */
    public function checkCertificateStatus(SslCertificate $sslCertificate): array
    {
        try {
            $domain = $sslCertificate->domain;
            $domainName = $domain->domain_name;

            // Check certificate via SSL connection
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $socket = @stream_socket_client(
                "ssl://{$domainName}:443",
                $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
            );

            if (!$socket) {
                return [
                    'status' => 'error',
                    'message' => "Cannot connect to {$domainName}:443 - {$errstr}",
                    'valid' => false
                ];
            }

            $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
            $certInfo = openssl_x509_parse($cert);

            fclose($socket);

            $expiresAt = Carbon::createFromTimestamp($certInfo['validTo_time_t']);
            $isExpired = $expiresAt->isPast();
            $expiresSoon = $expiresAt->diffInDays(now()) <= 30;

            return [
                'status' => $isExpired ? 'expired' : ($expiresSoon ? 'warning' : 'ok'),
                'message' => $isExpired ? 'Certificate has expired' : 
                           ($expiresSoon ? 'Certificate expires soon' : 'Certificate is valid'),
                'valid' => !$isExpired,
                'expires_at' => $expiresAt,
                'days_until_expiry' => max(0, $expiresAt->diffInDays(now())),
                'issuer' => $certInfo['issuer']['CN'] ?? 'Unknown'
            ];
        } catch (\Exception $e) {
            Log::error("Failed to check certificate status for {$sslCertificate->domain->domain_name}: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'valid' => false
            ];
        }
    }

    /**
     * Get certificates expiring soon
     */
    public function getCertificatesExpiringSoon(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return SslCertificate::where('auto_renew', true)
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('status', SslCertificate::STATUS_ACTIVE)
            ->get();
    }

    /**
     * Auto-renew expiring certificates
     */
    public function autoRenewCertificates(): array
    {
        $results = [];
        $expiringCertificates = $this->getCertificatesExpiringSoon(7); // Renew 7 days before expiry

        foreach ($expiringCertificates as $certificate) {
            if ($certificate->certificate_authority === SslCertificate::CA_LETSENCRYPT) {
                $success = $this->renewLetsEncryptCertificate($certificate);
                $results[] = [
                    'domain' => $certificate->domain->domain_name,
                    'success' => $success,
                    'message' => $success ? 'Certificate renewed successfully' : 'Certificate renewal failed'
                ];
            }
        }

        return $results;
    }

    /**
     * Remove SSL certificate
     */
    public function removeCertificate(SslCertificate $sslCertificate): bool
    {
        try {
            $domain = $sslCertificate->domain;
            $domainName = $domain->domain_name;

            // Remove certificate files
            $certDir = storage_path("app/nginx/certs");
            @unlink("{$certDir}/{$domainName}.crt");
            @unlink("{$certDir}/{$domainName}.key");
            @unlink("{$certDir}/{$domainName}.chain.pem");

            // Update Nginx configuration to disable SSL
            $this->webServerService->createNginxConfig($domain, [
                'enable_ssl' => false,
                'php_version' => '8.2'
            ]);

            // Reload Nginx
            $this->webServerService->reloadNginx($domain);

            // Delete certificate record
            $sslCertificate->delete();

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to remove certificate for {$sslCertificate->domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test SSL configuration
     */
    public function testSslConfiguration(Domain $domain): array
    {
        try {
            $domainName = $domain->domain_name;

            // Test SSL connection
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);

            $socket = @stream_socket_client(
                "ssl://{$domainName}:443",
                $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context
            );

            if (!$socket) {
                return [
                    'status' => 'error',
                    'message' => "SSL connection failed: {$errstr}",
                    'tests' => []
                ];
            }

            $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
            $certInfo = openssl_x509_parse($cert);

            fclose($socket);

            $tests = [
                'connection' => [
                    'status' => 'ok',
                    'message' => 'SSL connection successful'
                ],
                'certificate_valid' => [
                    'status' => Carbon::createFromTimestamp($certInfo['validTo_time_t'])->isFuture() ? 'ok' : 'error',
                    'message' => Carbon::createFromTimestamp($certInfo['validTo_time_t'])->isFuture() ? 'Certificate is valid' : 'Certificate has expired'
                ],
                'domain_match' => [
                    'status' => $this->checkDomainMatch($certInfo, $domainName) ? 'ok' : 'warning',
                    'message' => $this->checkDomainMatch($certInfo, $domainName) ? 'Domain matches certificate' : 'Domain does not match certificate'
                ]
            ];

            return [
                'status' => 'ok',
                'message' => 'SSL configuration test completed',
                'tests' => $tests
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'tests' => []
            ];
        }
    }

    /**
     * Check if domain matches certificate
     */
    protected function checkDomainMatch(array $certInfo, string $domainName): bool
    {
        $commonName = $certInfo['subject']['CN'] ?? '';

        if ($commonName === $domainName) {
            return true;
        }

        // Check Subject Alternative Names
        if (isset($certInfo['extensions']['subjectAltName'])) {
            $altNames = explode(', ', $certInfo['extensions']['subjectAltName']);
            foreach ($altNames as $altName) {
                if (strpos($altName, 'DNS:') === 0) {
                    $dns = substr($altName, 4);
                    if ($dns === $domainName || ($dns[0] === '*' && fnmatch($dns, $domainName))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}