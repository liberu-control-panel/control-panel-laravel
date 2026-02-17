<?php

namespace App\Services;

use App\Models\SftpAccount;
use App\Models\Domain;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SftpService
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
     * Create SFTP account
     */
    public function createSftpAccount(array $data): SftpAccount
    {
        // Validate home directory
        $homeDir = $data['home_directory'] ?? '/var/www/html';
        
        if ($data['domain_id'] ?? null) {
            $domain = Domain::findOrFail($data['domain_id']);
            $homeDir = "/var/www/vhosts/{$domain->domain_name}";
        }

        // Generate SSH keys if requested
        $sshKeys = [];
        if ($data['ssh_key_auth_enabled'] ?? false) {
            $sshKeys = $this->generateSshKeys(
                $data['ssh_key_type'] ?? 'rsa',
                $data['ssh_key_bits'] ?? 4096
            );
        }

        // Create SFTP account in database
        $sftpAccount = SftpAccount::create([
            'user_id' => $data['user_id'],
            'domain_id' => $data['domain_id'] ?? null,
            'username' => $data['username'],
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'home_directory' => $homeDir,
            'quota_mb' => $data['quota_mb'] ?? null,
            'bandwidth_limit_mb' => $data['bandwidth_limit_mb'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'ssh_key_auth_enabled' => $data['ssh_key_auth_enabled'] ?? false,
            'ssh_public_key' => $sshKeys['public_key'] ?? null,
            'ssh_private_key' => isset($sshKeys['private_key']) ? encrypt($sshKeys['private_key']) : null,
            'ssh_key_type' => $data['ssh_key_type'] ?? 'rsa',
            'ssh_key_bits' => $data['ssh_key_bits'] ?? 4096,
        ]);

        // Create system SFTP user
        $this->createSystemSftpUser($sftpAccount, $data['password'] ?? null);

        return $sftpAccount;
    }

    /**
     * Generate SSH key pair
     */
    public function generateSshKeys(string $keyType = 'rsa', int $keyBits = 4096): array
    {
        $tmpDir = sys_get_temp_dir();
        $keyName = 'sftp_' . Str::random(16);
        $privateKeyPath = "{$tmpDir}/{$keyName}";
        $publicKeyPath = "{$privateKeyPath}.pub";

        try {
            // Generate key based on type
            switch ($keyType) {
                case 'ed25519':
                    $command = ['ssh-keygen', '-t', 'ed25519', '-f', $privateKeyPath, '-N', '', '-C', 'control-panel-sftp'];
                    break;
                
                case 'ecdsa':
                    $command = ['ssh-keygen', '-t', 'ecdsa', '-b', '521', '-f', $privateKeyPath, '-N', '', '-C', 'control-panel-sftp'];
                    break;
                
                case 'rsa':
                default:
                    $command = ['ssh-keygen', '-t', 'rsa', '-b', (string)$keyBits, '-f', $privateKeyPath, '-N', '', '-C', 'control-panel-sftp'];
                    break;
            }

            $process = new Process($command);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception("Failed to generate SSH keys: " . $process->getErrorOutput());
            }

            // Read keys
            $privateKey = file_get_contents($privateKeyPath);
            $publicKey = file_get_contents($publicKeyPath);

            // Clean up temp files
            unlink($privateKeyPath);
            unlink($publicKeyPath);

            return [
                'private_key' => $privateKey,
                'public_key' => $publicKey,
                'key_type' => $keyType,
                'key_bits' => $keyBits,
            ];
        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($privateKeyPath)) unlink($privateKeyPath);
            if (file_exists($publicKeyPath)) unlink($publicKeyPath);
            
            Log::error("Failed to generate SSH keys: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create system SFTP user
     */
    protected function createSystemSftpUser(SftpAccount $sftpAccount, ?string $password): void
    {
        try {
            if ($this->detectionService->isKubernetes()) {
                $this->createSftpUserInKubernetes($sftpAccount, $password);
            } elseif ($this->detectionService->isDocker()) {
                $this->createSftpUserInDocker($sftpAccount, $password);
            } else {
                $this->createSftpUserStandalone($sftpAccount, $password);
            }
        } catch (Exception $e) {
            Log::error("Failed to create system SFTP user {$sftpAccount->username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Setup SSH authorized_keys for SFTP account
     */
    protected function setupSshKeys(SftpAccount $sftpAccount): void
    {
        $homeDir = $sftpAccount->home_directory;
        $sshDir = "{$homeDir}/.ssh";
        $authorizedKeysFile = "{$sshDir}/authorized_keys";

        // Create .ssh directory
        if (!file_exists($sshDir)) {
            mkdir($sshDir, 0700, true);
        }

        // Write public key to authorized_keys
        file_put_contents($authorizedKeysFile, $sftpAccount->ssh_public_key . "\n", FILE_APPEND);
        
        // Set proper permissions
        chmod($sshDir, 0700);
        chmod($authorizedKeysFile, 0600);
        
        // Set ownership
        $username = $sftpAccount->username;
        if (function_exists('posix_getpwnam')) {
            $userInfo = posix_getpwnam($username);
            if ($userInfo) {
                chown($sshDir, $userInfo['uid']);
                chgrp($sshDir, $userInfo['gid']);
                chown($authorizedKeysFile, $userInfo['uid']);
                chgrp($authorizedKeysFile, $userInfo['gid']);
            }
        }
    }

    /**
     * Create SFTP user standalone
     */
    protected function createSftpUserStandalone(SftpAccount $sftpAccount, ?string $password): void
    {
        $username = $sftpAccount->username;
        $homeDir = $sftpAccount->home_directory;

        // Create home directory if it doesn't exist
        if (!file_exists($homeDir)) {
            mkdir($homeDir, 0755, true);
        }

        // Determine nologin shell path
        $noLoginShell = file_exists('/usr/sbin/nologin') ? '/usr/sbin/nologin' : '/sbin/nologin';
        
        $process = new Process([
            'useradd',
            '-d', $homeDir,
            '-s', $noLoginShell,
            '-m',
            $username
        ]);
        $process->run();

        // Set password if provided
        if ($password) {
            $passwdProcess = new Process(['chpasswd']);
            $passwdProcess->setInput("{$username}:{$password}");
            $passwdProcess->run();
        }

        // Set up SSH keys if enabled
        if ($sftpAccount->ssh_key_auth_enabled && $sftpAccount->ssh_public_key) {
            $this->setupSshKeys($sftpAccount);
        }

        // Configure SFTP chroot
        $this->configureSftpChroot($sftpAccount);
    }

    /**
     * Create SFTP user in Docker
     */
    protected function createSftpUserInDocker(SftpAccount $sftpAccount, ?string $password): void
    {
        $username = $sftpAccount->username;
        $homeDir = $sftpAccount->home_directory;
        
        // Create user with proper shell for SFTP
        $useraddCmd = ['docker', 'exec', 'sftp', 'useradd', '-d', $homeDir, '-s', '/bin/bash', '-m', $username];
        $process = new Process($useraddCmd);
        $process->run();

        // Set password if provided
        if ($password) {
            $passwdProcess = new Process(['docker', 'exec', 'sftp', 'chpasswd']);
            $passwdProcess->setInput("{$username}:{$password}");
            $passwdProcess->run();
        }

        // Set up SSH keys if enabled
        if ($sftpAccount->ssh_key_auth_enabled && $sftpAccount->ssh_public_key) {
            $this->setupSshKeys($sftpAccount);
        }

        // Configure SFTP chroot
        $this->configureSftpChroot($sftpAccount);
    }

    /**
     * Create SFTP user in Kubernetes
     */
    protected function createSftpUserInKubernetes(SftpAccount $sftpAccount, ?string $password): void
    {
        $username = $sftpAccount->username;
        $homeDir = $sftpAccount->home_directory;
        
        $secretData = [
            'username' => $username,
            'home_directory' => $homeDir,
        ];
        
        if ($password) {
            $secretData['password'] = $password;
        }
        
        if ($sftpAccount->ssh_public_key) {
            $secretData['ssh_public_key'] = $sftpAccount->ssh_public_key;
        }

        $yamlData = '';
        foreach ($secretData as $key => $value) {
            $yamlData .= "  {$key}: " . base64_encode($value) . "\n";
        }

        $secretYaml = <<<YAML
apiVersion: v1
kind: Secret
metadata:
  name: sftp-user-{$username}
  namespace: control-panel
type: Opaque
data:
{$yamlData}
YAML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'sftp-user-');
        file_put_contents($tmpFile, $secretYaml);

        $process = new Process(['kubectl', 'apply', '-f', $tmpFile]);
        $process->run();
        
        unlink($tmpFile);

        if (!$process->isSuccessful()) {
            throw new Exception("Failed to create SFTP user in Kubernetes: " . $process->getErrorOutput());
        }
    }

    /**
     * Configure SFTP chroot in sshd_config
     */
    protected function configureSftpChroot(SftpAccount $sftpAccount): void
    {
        $sshdConfigFile = '/etc/ssh/sshd_config';
        
        if (!file_exists($sshdConfigFile) || !is_writable($sshdConfigFile)) {
            Log::warning("sshd_config not found or not writable, skipping chroot configuration");
            return;
        }

        $username = $sftpAccount->username;
        $homeDir = $sftpAccount->home_directory;

        $chrootConfig = <<<CONFIG

# SFTP chroot for {$username}
Match User {$username}
    ForceCommand internal-sftp
    ChrootDirectory {$homeDir}
    PermitTunnel no
    AllowAgentForwarding no
    AllowTcpForwarding no
    X11Forwarding no

CONFIG;

        file_put_contents($sshdConfigFile, $chrootConfig, FILE_APPEND);
        $this->reloadSshService();
    }

    /**
     * Delete SFTP account
     */
    public function deleteSftpAccount(SftpAccount $sftpAccount): bool
    {
        try {
            $this->deleteSystemSftpUser($sftpAccount);
            $sftpAccount->delete();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete SFTP account {$sftpAccount->username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete system SFTP user
     */
    protected function deleteSystemSftpUser(SftpAccount $sftpAccount): void
    {
        if ($this->detectionService->isKubernetes()) {
            $process = new Process(['kubectl', 'delete', 'secret', "sftp-user-{$sftpAccount->username}", '-n', 'control-panel']);
            $process->run();
        } elseif ($this->detectionService->isDocker()) {
            $process = new Process(['docker', 'exec', 'sftp', 'userdel', '-r', $sftpAccount->username]);
            $process->run();
        } else {
            $process = new Process(['userdel', '-r', $sftpAccount->username]);
            $process->run();
        }

        $this->removeSftpChroot($sftpAccount);
    }

    /**
     * Remove SFTP chroot configuration
     */
    protected function removeSftpChroot(SftpAccount $sftpAccount): void
    {
        $sshdConfigFile = '/etc/ssh/sshd_config';
        
        if (!file_exists($sshdConfigFile) || !is_writable($sshdConfigFile)) {
            return;
        }

        $content = file_get_contents($sshdConfigFile);
        $username = $sftpAccount->username;
        
        $pattern = "/\n# SFTP chroot for {$username}.*?(?=\n# SFTP chroot|\Z)/s";
        $content = preg_replace($pattern, '', $content);
        
        file_put_contents($sshdConfigFile, $content);
        $this->reloadSshService();
    }

    /**
     * Regenerate SSH keys for an account
     */
    public function regenerateSshKeys(SftpAccount $sftpAccount): array
    {
        $sshKeys = $this->generateSshKeys(
            $sftpAccount->ssh_key_type,
            $sftpAccount->ssh_key_bits
        );

        $sftpAccount->update([
            'ssh_public_key' => $sshKeys['public_key'],
            'ssh_private_key' => encrypt($sshKeys['private_key']),
            'ssh_key_auth_enabled' => true,
        ]);

        $this->setupSshKeys($sftpAccount);

        return [
            'public_key' => $sshKeys['public_key'],
            'private_key' => $sshKeys['private_key'],
        ];
    }

    /**
     * Reload SSH service
     */
    protected function reloadSshService(): void
    {
        try {
            if ($this->detectionService->isDocker()) {
                $process = new Process(['docker', 'exec', 'sftp', 'kill', '-HUP', '1']);
                $process->run();
            } elseif (!$this->detectionService->isKubernetes()) {
                $process = new Process(['systemctl', 'reload', 'sshd']);
                $process->run();
            }
        } catch (Exception $e) {
            Log::warning("Failed to reload SSH service: " . $e->getMessage());
        }
    }
}
