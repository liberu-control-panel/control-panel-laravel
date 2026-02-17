<?php

namespace App\Services;

use App\Models\FtpAccount;
use App\Models\Domain;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class FtpService
{
    protected $detectionService;
    protected $containerManager;

    public function __construct(
        DeploymentDetectionService $detectionService,
        ContainerManagerService $containerManager
    ) {
        $this->detectionService = $detectionService;
        $this->containerManager = $containerManager;
    }

    /**
     * Create FTP account
     */
    public function createFtpAccount(array $data): FtpAccount
    {
        // Validate home directory
        $homeDir = $data['home_directory'] ?? '/var/www/html';
        
        if ($data['domain_id'] ?? null) {
            $domain = Domain::findOrFail($data['domain_id']);
            $homeDir = "/var/www/vhosts/{$domain->domain_name}";
        }

        // Create FTP account in database
        $ftpAccount = FtpAccount::create([
            'user_id' => $data['user_id'],
            'domain_id' => $data['domain_id'] ?? null,
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'home_directory' => $homeDir,
            'quota_mb' => $data['quota_mb'] ?? null,
            'bandwidth_limit_mb' => $data['bandwidth_limit_mb'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Create system FTP user
        $this->createSystemFtpUser($ftpAccount, $data['password']);

        return $ftpAccount;
    }

    /**
     * Create system FTP user using vsftpd or proftpd
     */
    protected function createSystemFtpUser(FtpAccount $ftpAccount, string $password): void
    {
        try {
            if ($this->detectionService->isKubernetes()) {
                $this->createFtpUserInKubernetes($ftpAccount, $password);
            } elseif ($this->detectionService->isDocker()) {
                $this->createFtpUserInDocker($ftpAccount, $password);
            } else {
                $this->createFtpUserStandalone($ftpAccount, $password);
            }
        } catch (Exception $e) {
            Log::error("Failed to create system FTP user {$ftpAccount->username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create FTP user in Docker
     */
    protected function createFtpUserInDocker(FtpAccount $ftpAccount, string $password): void
    {
        // Create user with vsftpd virtual users
        $virtualUsersFile = storage_path('app/ftp/virtual_users');
        
        if (!file_exists(dirname($virtualUsersFile))) {
            mkdir(dirname($virtualUsersFile), 0755, true);
        }

        // Add user to virtual users file
        $entry = "{$ftpAccount->username}\n{$password}\n";
        file_put_contents($virtualUsersFile, $entry, FILE_APPEND);

        // Generate db file for vsftpd
        $process = new Process(['db_load', '-T', '-t', 'hash', '-f', $virtualUsersFile, storage_path('app/ftp/virtual_users.db')]);
        $process->run();

        // Create user config file
        $userConfigDir = storage_path('app/ftp/user_configs');
        if (!file_exists($userConfigDir)) {
            mkdir($userConfigDir, 0755, true);
        }

        $userConfig = "local_root={$ftpAccount->home_directory}\n";
        
        if ($ftpAccount->quota_mb) {
            // Convert MB to bytes
            $quotaBytes = $ftpAccount->quota_mb * 1024 * 1024;
            $userConfig .= "user_config_dir=/etc/vsftpd/user_configs\n";
            $userConfig .= "quota_bytes={$quotaBytes}\n";
        }

        file_put_contents("{$userConfigDir}/{$ftpAccount->username}", $userConfig);

        // Reload vsftpd in container
        $this->reloadFtpService();
    }

    /**
     * Create FTP user in Kubernetes
     */
    protected function createFtpUserInKubernetes(FtpAccount $ftpAccount, string $password): void
    {
        // Create ConfigMap for FTP user
        $username = $ftpAccount->username;
        $homeDir = $ftpAccount->home_directory;
        
        $configMapYaml = <<<YAML
apiVersion: v1
kind: ConfigMap
metadata:
  name: ftp-user-{$username}
  namespace: control-panel
data:
  username: {$username}
  password: {$password}
  home_directory: {$homeDir}
YAML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'ftp-user-');
        file_put_contents($tmpFile, $configMapYaml);

        $process = new Process(['kubectl', 'apply', '-f', $tmpFile]);
        $process->run();
        
        unlink($tmpFile);

        if (!$process->isSuccessful()) {
            throw new Exception("Failed to create FTP user in Kubernetes: " . $process->getErrorOutput());
        }
    }

    /**
     * Create FTP user standalone
     */
    protected function createFtpUserStandalone(FtpAccount $ftpAccount, string $password): void
    {
        // Create Linux user for FTP access
        $username = $ftpAccount->username;
        $homeDir = $ftpAccount->home_directory;

        // Create home directory if it doesn't exist
        if (!file_exists($homeDir)) {
            mkdir($homeDir, 0755, true);
        }

        // Create system user
        $process = new Process([
            'useradd',
            '-d', $homeDir,
            '-s', '/sbin/nologin', // No shell access, FTP only
            $username
        ]);
        $process->run();

        // Set password
        $passwdProcess = new Process(['chpasswd']);
        $passwdProcess->setInput("{$username}:{$password}");
        $passwdProcess->run();

        // Set directory ownership
        $chownProcess = new Process(['chown', '-R', "{$username}:{$username}", $homeDir]);
        $chownProcess->run();
    }

    /**
     * Delete FTP account
     */
    public function deleteFtpAccount(FtpAccount $ftpAccount): bool
    {
        try {
            // Remove system user
            $this->deleteSystemFtpUser($ftpAccount);

            // Delete from database
            $ftpAccount->delete();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete FTP account {$ftpAccount->username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete system FTP user
     */
    protected function deleteSystemFtpUser(FtpAccount $ftpAccount): void
    {
        if ($this->detectionService->isKubernetes()) {
            $this->deleteFtpUserInKubernetes($ftpAccount);
        } elseif ($this->detectionService->isDocker()) {
            $this->deleteFtpUserInDocker($ftpAccount);
        } else {
            $this->deleteFtpUserStandalone($ftpAccount);
        }
    }

    /**
     * Delete FTP user in Docker
     */
    protected function deleteFtpUserInDocker(FtpAccount $ftpAccount): void
    {
        $virtualUsersFile = storage_path('app/ftp/virtual_users');
        
        if (file_exists($virtualUsersFile)) {
            $lines = file($virtualUsersFile);
            $newLines = [];
            $skipNext = false;

            foreach ($lines as $line) {
                if ($skipNext) {
                    $skipNext = false;
                    continue;
                }

                if (trim($line) === $ftpAccount->username) {
                    $skipNext = true;
                    continue;
                }

                $newLines[] = $line;
            }

            file_put_contents($virtualUsersFile, implode('', $newLines));

            // Regenerate db file
            $process = new Process(['db_load', '-T', '-t', 'hash', '-f', $virtualUsersFile, storage_path('app/ftp/virtual_users.db')]);
            $process->run();
        }

        // Remove user config file
        $userConfigFile = storage_path("app/ftp/user_configs/{$ftpAccount->username}");
        if (file_exists($userConfigFile)) {
            unlink($userConfigFile);
        }

        $this->reloadFtpService();
    }

    /**
     * Delete FTP user in Kubernetes
     */
    protected function deleteFtpUserInKubernetes(FtpAccount $ftpAccount): void
    {
        $process = new Process(['kubectl', 'delete', 'configmap', "ftp-user-{$ftpAccount->username}", '-n', 'control-panel']);
        $process->run();
    }

    /**
     * Delete FTP user standalone
     */
    protected function deleteFtpUserStandalone(FtpAccount $ftpAccount): void
    {
        $process = new Process(['userdel', $ftpAccount->username]);
        $process->run();
    }

    /**
     * Update FTP password
     */
    public function updatePassword(FtpAccount $ftpAccount, string $newPassword): bool
    {
        try {
            // Update in database
            $ftpAccount->update(['password' => Hash::make($newPassword)]);

            // Update system password
            if (!$this->detectionService->isKubernetes() && !$this->detectionService->isDocker()) {
                $passwdProcess = new Process(['chpasswd']);
                $passwdProcess->setInput("{$ftpAccount->username}:{$newPassword}");
                $passwdProcess->run();
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to update FTP password for {$ftpAccount->username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get FTP account usage statistics
     */
    public function getUsageStats(FtpAccount $ftpAccount): array
    {
        try {
            $homeDir = $ftpAccount->home_directory;
            
            // Get directory size
            $sizeProcess = new Process(['du', '-sb', $homeDir]);
            $sizeProcess->run();

            $sizeBytes = 0;
            if ($sizeProcess->isSuccessful()) {
                $output = trim($sizeProcess->getOutput());
                if (preg_match('/^(\d+)/', $output, $matches)) {
                    $sizeBytes = (int) $matches[1];
                }
            }

            return [
                'used_bytes' => $sizeBytes,
                'used_mb' => round($sizeBytes / 1024 / 1024, 2),
                'quota_mb' => $ftpAccount->quota_mb,
                'quota_bytes' => $ftpAccount->quota_mb ? $ftpAccount->quota_mb * 1024 * 1024 : null,
                'usage_percent' => $ftpAccount->quota_mb ? round(($sizeBytes / ($ftpAccount->quota_mb * 1024 * 1024)) * 100, 2) : 0,
            ];
        } catch (Exception $e) {
            Log::error("Failed to get FTP usage stats for {$ftpAccount->username}: " . $e->getMessage());
            return [
                'used_bytes' => 0,
                'used_mb' => 0,
                'quota_mb' => $ftpAccount->quota_mb,
                'quota_bytes' => $ftpAccount->quota_mb ? $ftpAccount->quota_mb * 1024 * 1024 : null,
                'usage_percent' => 0,
            ];
        }
    }

    /**
     * Reload FTP service
     */
    protected function reloadFtpService(): void
    {
        try {
            if ($this->detectionService->isDocker()) {
                $process = new Process(['docker', 'exec', 'vsftpd', 'kill', '-HUP', '1']);
                $process->run();
            } elseif (!$this->detectionService->isKubernetes()) {
                $process = new Process(['systemctl', 'reload', 'vsftpd']);
                $process->run();
            }
        } catch (Exception $e) {
            Log::warning("Failed to reload FTP service: " . $e->getMessage());
        }
    }
}
