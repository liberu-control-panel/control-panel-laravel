<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ExternalBackupParser
{
    const TYPE_CPANEL = 'cpanel';
    const TYPE_VIRTUALMIN = 'virtualmin';
    const TYPE_PLESK = 'plesk';
    const TYPE_LIBERU = 'liberu';

    /**
     * Detect backup type from archive
     */
    public function detectBackupType(string $backupPath): string
    {
        try {
            // Extract first few files to check structure
            $tempDir = $this->createTempDirectory();
            
            // List archive contents
            $process = new Process(['tar', '-tzf', $backupPath, '|', 'head', '-20']);
            $process->run();
            $contents = $process->getOutput();

            // Clean up temp directory
            $this->cleanupDirectory($tempDir);

            // Detect based on file structure
            if (str_contains($contents, 'cp/') || str_contains($contents, 'homedir.tar')) {
                return self::TYPE_CPANEL;
            }

            if (str_contains($contents, 'virtualmin.info') || str_contains($contents, 'domain.info')) {
                return self::TYPE_VIRTUALMIN;
            }

            if (str_contains($contents, 'dump.xml') || str_contains($contents, 'psa_')) {
                return self::TYPE_PLESK;
            }

            // Check if it's our own backup format
            if (str_contains($contents, 'files.tar.gz') || str_contains($contents, 'databases/')) {
                return self::TYPE_LIBERU;
            }

            throw new Exception('Unknown backup format');
        } catch (Exception $e) {
            Log::error("Failed to detect backup type: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse cPanel backup
     */
    public function parseCPanelBackup(string $backupPath): array
    {
        try {
            $extractDir = $this->extractArchive($backupPath);
            
            $data = [
                'type' => self::TYPE_CPANEL,
                'domains' => [],
                'databases' => [],
                'email_accounts' => [],
                'files' => [],
            ];

            // cPanel backup structure:
            // - homedir.tar (contains public_html, etc.)
            // - mysql/*.sql (database dumps)
            // - mail/ (email data)

            // Parse home directory
            $homedirPath = $extractDir . '/homedir.tar';
            if (file_exists($homedirPath)) {
                $data['files'][] = [
                    'path' => $homedirPath,
                    'type' => 'home_directory',
                ];
            }

            // Parse databases
            $mysqlDir = $extractDir . '/mysql';
            if (is_dir($mysqlDir)) {
                $sqlFiles = glob($mysqlDir . '/*.sql');
                foreach ($sqlFiles as $sqlFile) {
                    $data['databases'][] = [
                        'name' => pathinfo($sqlFile, PATHINFO_FILENAME),
                        'file' => $sqlFile,
                    ];
                }
            }

            // Parse email accounts
            $mailDir = $extractDir . '/mail';
            if (is_dir($mailDir)) {
                $data['email_accounts'] = $this->parseCPanelEmailAccounts($mailDir);
            }

            // Parse domains from metadata
            $metadataFile = $extractDir . '/cp/.cpanel.yml';
            if (file_exists($metadataFile)) {
                $metadata = yaml_parse_file($metadataFile);
                if (isset($metadata['DOMAIN'])) {
                    $data['domains'][] = $metadata['DOMAIN'];
                }
            }

            return $data;
        } catch (Exception $e) {
            Log::error("Failed to parse cPanel backup: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse Virtualmin backup
     */
    public function parseVirtualminBackup(string $backupPath): array
    {
        try {
            $extractDir = $this->extractArchive($backupPath);
            
            $data = [
                'type' => self::TYPE_VIRTUALMIN,
                'domains' => [],
                'databases' => [],
                'email_accounts' => [],
                'files' => [],
            ];

            // Virtualmin backup structure:
            // - virtualmin.info (metadata)
            // - domain.info (domain info)
            // - public_html.tar.gz (web files)
            // - mysql/*.sql or pgsql/*.sql (databases)
            // - mail/ (email data)

            // Parse metadata
            $infoFile = $extractDir . '/virtualmin.info';
            if (file_exists($infoFile)) {
                $info = parse_ini_file($infoFile);
                if (isset($info['dom'])) {
                    $data['domains'][] = $info['dom'];
                }
            }

            // Parse web files
            $webFilesPath = $extractDir . '/public_html.tar.gz';
            if (file_exists($webFilesPath)) {
                $data['files'][] = [
                    'path' => $webFilesPath,
                    'type' => 'web_root',
                ];
            }

            // Parse databases
            $this->parseVirtualminDatabases($extractDir, $data);

            // Parse email
            $mailDir = $extractDir . '/mail';
            if (is_dir($mailDir)) {
                $data['email_accounts'] = $this->parseVirtualminEmailAccounts($mailDir);
            }

            return $data;
        } catch (Exception $e) {
            Log::error("Failed to parse Virtualmin backup: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse Plesk backup
     */
    public function parsePleskBackup(string $backupPath): array
    {
        try {
            $extractDir = $this->extractArchive($backupPath);
            
            $data = [
                'type' => self::TYPE_PLESK,
                'domains' => [],
                'databases' => [],
                'email_accounts' => [],
                'files' => [],
            ];

            // Plesk backup structure:
            // - dump.xml (metadata)
            // - clients/*/domains/*/httpdocs/ (web files)
            // - databases/ (database dumps)

            // Parse metadata from dump.xml
            $dumpXmlPath = $extractDir . '/dump.xml';
            if (file_exists($dumpXmlPath)) {
                $this->parsePleskDumpXml($dumpXmlPath, $data);
            }

            // Parse databases
            $databasesDir = $extractDir . '/databases';
            if (is_dir($databasesDir)) {
                $sqlFiles = glob($databasesDir . '/*.sql');
                foreach ($sqlFiles as $sqlFile) {
                    $data['databases'][] = [
                        'name' => pathinfo($sqlFile, PATHINFO_FILENAME),
                        'file' => $sqlFile,
                    ];
                }
            }

            // Parse web files (Plesk structure is more complex)
            $this->parsePleskWebFiles($extractDir, $data);

            return $data;
        } catch (Exception $e) {
            Log::error("Failed to parse Plesk backup: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse our own backup format
     */
    public function parseLiberuBackup(string $backupPath): array
    {
        try {
            $extractDir = $this->extractArchive($backupPath);
            
            return [
                'type' => self::TYPE_LIBERU,
                'files_archive' => $extractDir . '/files.tar.gz',
                'databases_dir' => $extractDir . '/databases',
                'email_archive' => $extractDir . '/email.tar.gz',
            ];
        } catch (Exception $e) {
            Log::error("Failed to parse Liberu backup: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract archive to temporary directory
     */
    protected function extractArchive(string $backupPath): string
    {
        $extractDir = $this->createTempDirectory();
        
        $process = new Process([
            'tar', '-xzf', $backupPath, '-C', $extractDir
        ]);
        $process->setTimeout(3600); // 1 hour
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Failed to extract backup: ' . $process->getErrorOutput());
        }

        return $extractDir;
    }

    /**
     * Create temporary directory
     */
    protected function createTempDirectory(): string
    {
        $tempDir = storage_path('app/temp/backup_parse_' . uniqid());
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir;
    }

    /**
     * Clean up directory
     */
    protected function cleanupDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }

    /**
     * Parse cPanel email accounts
     */
    protected function parseCPanelEmailAccounts(string $mailDir): array
    {
        $accounts = [];
        
        $domains = glob($mailDir . '/*', GLOB_ONLYDIR);
        foreach ($domains as $domainDir) {
            $emailBoxes = glob($domainDir . '/*', GLOB_ONLYDIR);
            foreach ($emailBoxes as $emailBox) {
                $accounts[] = [
                    'email' => basename($emailBox) . '@' . basename($domainDir),
                    'path' => $emailBox,
                ];
            }
        }

        return $accounts;
    }

    /**
     * Parse Virtualmin databases
     */
    protected function parseVirtualminDatabases(string $extractDir, array &$data): void
    {
        // MySQL databases
        $mysqlDir = $extractDir . '/mysql';
        if (is_dir($mysqlDir)) {
            $sqlFiles = glob($mysqlDir . '/*.sql');
            foreach ($sqlFiles as $sqlFile) {
                $data['databases'][] = [
                    'name' => pathinfo($sqlFile, PATHINFO_FILENAME),
                    'file' => $sqlFile,
                    'type' => 'mysql',
                ];
            }
        }

        // PostgreSQL databases
        $pgsqlDir = $extractDir . '/pgsql';
        if (is_dir($pgsqlDir)) {
            $sqlFiles = glob($pgsqlDir . '/*.sql');
            foreach ($sqlFiles as $sqlFile) {
                $data['databases'][] = [
                    'name' => pathinfo($sqlFile, PATHINFO_FILENAME),
                    'file' => $sqlFile,
                    'type' => 'postgresql',
                ];
            }
        }
    }

    /**
     * Parse Virtualmin email accounts
     */
    protected function parseVirtualminEmailAccounts(string $mailDir): array
    {
        $accounts = [];
        
        // Virtualmin uses Maildir format
        $mailboxes = glob($mailDir . '/*', GLOB_ONLYDIR);
        foreach ($mailboxes as $mailbox) {
            if (file_exists($mailbox . '/Maildir')) {
                $accounts[] = [
                    'name' => basename($mailbox),
                    'path' => $mailbox,
                ];
            }
        }

        return $accounts;
    }

    /**
     * Parse Plesk dump.xml for metadata
     */
    protected function parsePleskDumpXml(string $dumpXmlPath, array &$data): void
    {
        try {
            $xml = simplexml_load_file($dumpXmlPath);
            
            // Extract domains
            if (isset($xml->client->domain)) {
                foreach ($xml->client->domain as $domain) {
                    $data['domains'][] = (string)$domain->name;
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to parse Plesk dump.xml: " . $e->getMessage());
        }
    }

    /**
     * Parse Plesk web files
     */
    protected function parsePleskWebFiles(string $extractDir, array &$data): void
    {
        // Plesk structure: clients/*/domains/*/httpdocs/
        $httpdocsDirs = glob($extractDir . '/clients/*/domains/*/httpdocs', GLOB_ONLYDIR);
        
        foreach ($httpdocsDirs as $httpdocsDir) {
            $data['files'][] = [
                'path' => $httpdocsDir,
                'type' => 'web_root',
            ];
        }
    }
}
