<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Backup;
use App\Models\Database;
use App\Models\EmailAccount;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class BulkRestoreService
{
    protected $backupService;
    protected $externalBackupParser;
    protected $databaseService;
    protected $deploymentDetection;

    public function __construct(
        BackupService $backupService,
        ExternalBackupParser $externalBackupParser,
        DatabaseService $databaseService,
        DeploymentDetectionService $deploymentDetection
    ) {
        $this->backupService = $backupService;
        $this->externalBackupParser = $externalBackupParser;
        $this->databaseService = $databaseService;
        $this->deploymentDetection = $deploymentDetection;
    }

    /**
     * Restore multiple backups in bulk
     */
    public function bulkRestore(array $backupIds, array $options = []): array
    {
        $results = [];

        foreach ($backupIds as $backupId) {
            try {
                $backup = Backup::findOrFail($backupId);
                $success = $this->backupService->restoreBackup($backup, $options);
                
                $results[$backupId] = [
                    'success' => $success,
                    'backup' => $backup,
                    'error' => null,
                ];
            } catch (Exception $e) {
                Log::error("Bulk restore failed for backup {$backupId}: " . $e->getMessage());
                $results[$backupId] = [
                    'success' => false,
                    'backup' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Restore external backup (cPanel, Virtualmin, Plesk)
     */
    public function restoreExternalBackup(string $backupPath, array $options = []): bool
    {
        try {
            // Detect backup type
            $backupType = $this->externalBackupParser->detectBackupType($backupPath);
            
            Log::info("Detected backup type: {$backupType}");

            // Parse backup based on type
            $parsedData = match ($backupType) {
                ExternalBackupParser::TYPE_CPANEL => $this->externalBackupParser->parseCPanelBackup($backupPath),
                ExternalBackupParser::TYPE_VIRTUALMIN => $this->externalBackupParser->parseVirtualminBackup($backupPath),
                ExternalBackupParser::TYPE_PLESK => $this->externalBackupParser->parsePleskBackup($backupPath),
                ExternalBackupParser::TYPE_LIBERU => $this->externalBackupParser->parseLiberuBackup($backupPath),
                default => throw new Exception("Unsupported backup type: {$backupType}"),
            };

            // Restore based on parsed data
            return $this->restoreParsedBackup($parsedData, $options);
        } catch (Exception $e) {
            Log::error("External backup restore failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore parsed backup data
     */
    protected function restoreParsedBackup(array $parsedData, array $options): bool
    {
        DB::beginTransaction();

        try {
            // Create or get domain
            $domain = $this->getOrCreateDomain($parsedData, $options);

            if (!$domain) {
                throw new Exception('Failed to create/get domain');
            }

            // Restore databases
            if (!empty($parsedData['databases'])) {
                $this->restoreDatabases($domain, $parsedData['databases'], $options);
            }

            // Restore files
            if (!empty($parsedData['files'])) {
                $this->restoreFiles($domain, $parsedData['files'], $options);
            }

            // Restore email accounts
            if (!empty($parsedData['email_accounts'])) {
                $this->restoreEmailAccounts($domain, $parsedData['email_accounts'], $options);
            }

            // Handle Liberu-specific backup format
            if ($parsedData['type'] === ExternalBackupParser::TYPE_LIBERU) {
                $this->restoreLiberuBackup($domain, $parsedData, $options);
            }

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Parsed backup restore failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get or create domain from backup data
     */
    protected function getOrCreateDomain(array $parsedData, array $options): ?Domain
    {
        $domainName = $options['domain_name'] ?? ($parsedData['domains'][0] ?? null);

        if (!$domainName) {
            throw new Exception('Domain name not specified');
        }

        // Check if domain exists
        $domain = Domain::where('domain_name', $domainName)->first();

        if (!$domain) {
            // Create new domain
            $domain = Domain::create([
                'domain_name' => $domainName,
                'user_id' => $options['user_id'] ?? auth()->id(),
                'server_id' => $options['server_id'] ?? null,
                'status' => 'active',
            ]);
        }

        return $domain;
    }

    /**
     * Restore databases from backup
     */
    protected function restoreDatabases(Domain $domain, array $databases, array $options): void
    {
        foreach ($databases as $dbData) {
            try {
                $dbName = $dbData['name'];
                $dbFile = $dbData['file'];

                // Create or get database
                $database = $domain->databases()->where('name', $dbName)->first();
                
                if (!$database) {
                    $database = Database::create([
                        'domain_id' => $domain->id,
                        'name' => $dbName,
                        'username' => $dbData['username'] ?? $dbName,
                        'password' => $dbData['password'] ?? str()->random(16),
                        'status' => 'active',
                    ]);
                }

                // Restore database from SQL file
                if (file_exists($dbFile)) {
                    $this->databaseService->restoreBackup($database, $dbFile);
                }

                Log::info("Restored database: {$dbName}");
            } catch (Exception $e) {
                Log::error("Failed to restore database {$dbData['name']}: " . $e->getMessage());
                
                if (!($options['continue_on_error'] ?? true)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Restore files from backup
     */
    protected function restoreFiles(Domain $domain, array $files, array $options): void
    {
        $deploymentMode = $this->deploymentDetection->detectDeploymentMode();

        foreach ($files as $fileData) {
            try {
                $filePath = $fileData['path'];
                $fileType = $fileData['type'];

                if ($fileType === 'web_root' || $fileType === 'home_directory') {
                    $this->restoreWebFiles($domain, $filePath, $deploymentMode, $options);
                }

                Log::info("Restored files from: {$filePath}");
            } catch (Exception $e) {
                Log::error("Failed to restore files from {$fileData['path']}: " . $e->getMessage());
                
                if (!($options['continue_on_error'] ?? true)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Restore web files based on deployment mode
     */
    protected function restoreWebFiles(Domain $domain, string $archivePath, string $deploymentMode, array $options): void
    {
        if ($deploymentMode === DeploymentDetectionService::MODE_KUBERNETES) {
            $this->restoreWebFilesKubernetes($domain, $archivePath, $options);
        } elseif ($deploymentMode === DeploymentDetectionService::MODE_DOCKER_COMPOSE) {
            $this->restoreWebFilesDocker($domain, $archivePath, $options);
        } else {
            $this->restoreWebFilesStandalone($domain, $archivePath, $options);
        }
    }

    /**
     * Restore web files in Kubernetes
     */
    protected function restoreWebFilesKubernetes(Domain $domain, string $archivePath, array $options): void
    {
        $namespace = "hosting-{$domain->domain_name}";
        $podName = "{$domain->domain_name}-web";

        // Copy archive to pod
        $process = new Process([
            'kubectl', 'cp', $archivePath, 
            "{$namespace}/{$podName}:/tmp/restore_files.tar.gz"
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Failed to copy files to Kubernetes pod');
        }

        // Extract files in pod
        $process = new Process([
            'kubectl', 'exec', '-n', $namespace, $podName, '--',
            'tar', '-xzf', '/tmp/restore_files.tar.gz', '-C', '/var/www/html'
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Failed to extract files in Kubernetes pod');
        }

        // Clean up
        $process = new Process([
            'kubectl', 'exec', '-n', $namespace, $podName, '--',
            'rm', '/tmp/restore_files.tar.gz'
        ]);
        $process->run();
    }

    /**
     * Restore web files in Docker
     */
    protected function restoreWebFilesDocker(Domain $domain, string $archivePath, array $options): void
    {
        $containerName = "{$domain->domain_name}_web";

        // Copy archive to container
        $process = new Process([
            'docker', 'cp', $archivePath, "{$containerName}:/tmp/restore_files.tar.gz"
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Failed to copy files to Docker container');
        }

        // Extract files
        $process = new Process([
            'docker', 'exec', $containerName,
            'tar', '-xzf', '/tmp/restore_files.tar.gz', '-C', '/var/www/html'
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Failed to extract files in Docker container');
        }

        // Clean up
        $process = new Process([
            'docker', 'exec', $containerName, 'rm', '/tmp/restore_files.tar.gz'
        ]);
        $process->run();
    }

    /**
     * Restore web files in standalone mode
     */
    protected function restoreWebFilesStandalone(Domain $domain, string $archivePath, array $options): void
    {
        $webRoot = $options['web_root'] ?? "/var/www/{$domain->domain_name}/public_html";

        // Ensure directory exists
        if (!is_dir($webRoot)) {
            mkdir($webRoot, 0755, true);
        }

        // Extract files
        $process = new Process([
            'tar', '-xzf', $archivePath, '-C', $webRoot
        ]);
        $process->setTimeout(1800);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Failed to extract files: ' . $process->getErrorOutput());
        }

        // Set permissions
        $process = new Process([
            'chown', '-R', 'www-data:www-data', $webRoot
        ]);
        $process->run();
    }

    /**
     * Restore email accounts
     */
    protected function restoreEmailAccounts(Domain $domain, array $emailAccounts, array $options): void
    {
        foreach ($emailAccounts as $accountData) {
            try {
                $email = $accountData['email'] ?? $accountData['name'];
                
                // Create email account if not exists
                $emailAccount = $domain->emailAccounts()->where('email', $email)->first();
                
                if (!$emailAccount) {
                    $emailAccount = EmailAccount::create([
                        'domain_id' => $domain->id,
                        'email' => $email,
                        'password' => $accountData['password'] ?? str()->random(16),
                        'quota' => $accountData['quota'] ?? 1000,
                    ]);
                }

                // Restore mail data if path provided
                if (isset($accountData['path']) && is_dir($accountData['path'])) {
                    $this->restoreMailData($emailAccount, $accountData['path'], $options);
                }

                Log::info("Restored email account: {$email}");
            } catch (Exception $e) {
                Log::error("Failed to restore email account: " . $e->getMessage());
                
                if (!($options['continue_on_error'] ?? true)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Restore mail data
     */
    protected function restoreMailData(EmailAccount $emailAccount, string $sourcePath, array $options): void
    {
        $mailPath = "/var/mail/{$emailAccount->domain->domain_name}/{$emailAccount->username}";
        
        // Copy mail data
        $process = new Process([
            'rsync', '-av', $sourcePath . '/', $mailPath . '/'
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning("Failed to restore mail data for {$emailAccount->email}");
        }
    }

    /**
     * Restore Liberu backup format
     */
    protected function restoreLiberuBackup(Domain $domain, array $parsedData, array $options): void
    {
        // Restore files
        if (isset($parsedData['files_archive']) && file_exists($parsedData['files_archive'])) {
            $this->restoreFiles($domain, [
                ['path' => $parsedData['files_archive'], 'type' => 'web_root']
            ], $options);
        }

        // Restore databases
        if (isset($parsedData['databases_dir']) && is_dir($parsedData['databases_dir'])) {
            $sqlFiles = glob($parsedData['databases_dir'] . '/*.sql');
            $databases = array_map(function ($file) {
                return [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'file' => $file,
                ];
            }, $sqlFiles);
            
            $this->restoreDatabases($domain, $databases, $options);
        }

        // Restore email
        if (isset($parsedData['email_archive']) && file_exists($parsedData['email_archive'])) {
            // Extract and restore email
            $this->restoreEmailArchive($domain, $parsedData['email_archive'], $options);
        }
    }

    /**
     * Restore email archive
     */
    protected function restoreEmailArchive(Domain $domain, string $archivePath, array $options): void
    {
        $mailPath = "/var/mail/{$domain->domain_name}";
        
        // Extract email archive
        $process = new Process([
            'tar', '-xzf', $archivePath, '-C', '/'
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning("Failed to restore email archive for {$domain->domain_name}");
        }
    }

    /**
     * Get bulk restore statistics
     */
    public function getBulkRestoreStats(array $results): array
    {
        return [
            'total' => count($results),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'details' => $results,
        ];
    }
}
