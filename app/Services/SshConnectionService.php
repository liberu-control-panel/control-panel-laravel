<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerCredential;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use Exception;

class SshConnectionService
{
    protected array $connectionPool = [];
    protected int $maxPoolSize;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct()
    {
        $this->maxPoolSize = config('ssh.connection_pool_size', 10);
        $this->timeout = config('ssh.timeout', 30);
        $this->retryAttempts = config('ssh.retry_attempts', 3);
        $this->retryDelay = config('ssh.retry_delay', 5);
    }

    /**
     * Connect to a server using its credentials
     */
    public function connect(Server $server, ?ServerCredential $credential = null): SSH2
    {
        // Use active credential if none provided
        if (!$credential) {
            $credential = $server->activeCredential;
            if (!$credential) {
                throw new Exception("No active credentials found for server: {$server->name}");
            }
        }

        // Check connection pool
        $poolKey = $this->getPoolKey($server, $credential);
        if (isset($this->connectionPool[$poolKey])) {
            $ssh = $this->connectionPool[$poolKey];
            if ($ssh->isConnected()) {
                return $ssh;
            }
            // Remove stale connection
            unset($this->connectionPool[$poolKey]);
        }

        // Create new connection with retry logic
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $ssh = $this->createConnection($server, $credential);
                
                // Add to pool if space available
                if (count($this->connectionPool) < $this->maxPoolSize) {
                    $this->connectionPool[$poolKey] = $ssh;
                }
                
                // Mark credential as used
                $credential->markAsUsed();
                
                return $ssh;
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;
                
                if ($attempt < $this->retryAttempts) {
                    Log::warning("SSH connection attempt {$attempt} failed for {$server->hostname}, retrying...");
                    sleep($this->retryDelay);
                }
            }
        }

        throw new Exception("Failed to connect to {$server->hostname} after {$this->retryAttempts} attempts: " . $lastException->getMessage());
    }

    /**
     * Create a new SSH connection
     */
    protected function createConnection(Server $server, ServerCredential $credential): SSH2
    {
        $ssh = new SSH2($server->hostname, $server->port, $this->timeout);

        // Configure security settings
        $this->configureSecurity($ssh);

        // Authenticate based on credential type
        if ($credential->usesSshKey()) {
            $this->authenticateWithKey($ssh, $credential);
        } elseif ($credential->usesPassword()) {
            $this->authenticateWithPassword($ssh, $credential);
        } else {
            throw new Exception("Invalid authentication type for credential");
        }

        // Setup keepalive
        $keepaliveInterval = config('ssh.keepalive_interval', 60);
        $ssh->enableKeepAlive($keepaliveInterval);

        Log::info("Successfully connected to {$server->hostname} as {$credential->username}");

        return $ssh;
    }

    /**
     * Configure SSH security settings
     */
    protected function configureSecurity(SSH2 $ssh): void
    {
        $security = config('ssh.security', []);

        if (!empty($security['allowed_ciphers'])) {
            $ssh->setPreferredAlgorithms([
                'crypt' => $security['allowed_ciphers']
            ]);
        }

        if (!empty($security['allowed_macs'])) {
            $ssh->setPreferredAlgorithms([
                'mac' => $security['allowed_macs']
            ]);
        }
    }

    /**
     * Authenticate using SSH key
     */
    protected function authenticateWithKey(SSH2 $ssh, ServerCredential $credential): void
    {
        try {
            $privateKey = $credential->ssh_private_key;
            $passphrase = $credential->ssh_key_passphrase;

            if ($passphrase) {
                $key = PublicKeyLoader::load($privateKey, $passphrase);
            } else {
                $key = PublicKeyLoader::load($privateKey);
            }

            if (!$ssh->login($credential->username, $key)) {
                throw new Exception("SSH key authentication failed");
            }
        } catch (Exception $e) {
            throw new Exception("SSH key authentication error: " . $e->getMessage());
        }
    }

    /**
     * Authenticate using password
     */
    protected function authenticateWithPassword(SSH2 $ssh, ServerCredential $credential): void
    {
        if (!$ssh->login($credential->username, $credential->password)) {
            throw new Exception("Password authentication failed");
        }
    }

    /**
     * Execute a command on a remote server
     */
    public function execute(Server $server, string $command, ?ServerCredential $credential = null): array
    {
        $ssh = $this->connect($server, $credential);

        Log::debug("Executing command on {$server->hostname}: {$command}");

        $output = $ssh->exec($command);
        $exitStatus = $ssh->getExitStatus();

        return [
            'output' => $output,
            'exit_status' => $exitStatus,
            'success' => $exitStatus === 0,
        ];
    }

    /**
     * Execute a command with sudo
     */
    public function executeSudo(Server $server, string $command, ?ServerCredential $credential = null): array
    {
        if (!config('ssh.sudo.enabled', false)) {
            throw new Exception("Sudo is not enabled in configuration");
        }

        // Check if command is allowed
        $allowedCommands = config('ssh.sudo.allowed_commands', []);
        $commandBase = explode(' ', trim($command))[0];
        
        if (!in_array($commandBase, $allowedCommands)) {
            throw new Exception("Command '{$commandBase}' is not in the allowed sudo commands list");
        }

        $sudoCommand = "sudo {$command}";
        
        return $this->execute($server, $sudoCommand, $credential);
    }

    /**
     * Upload a file to remote server
     */
    public function uploadFile(Server $server, string $localPath, string $remotePath, ?ServerCredential $credential = null): bool
    {
        $ssh = $this->connect($server, $credential);

        try {
            $sftp = $ssh->getSFTP();
            $result = $sftp->put($remotePath, $localPath, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE);
            
            if ($result) {
                Log::info("Successfully uploaded {$localPath} to {$server->hostname}:{$remotePath}");
            }
            
            return $result;
        } catch (Exception $e) {
            Log::error("Failed to upload file to {$server->hostname}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download a file from remote server
     */
    public function downloadFile(Server $server, string $remotePath, string $localPath, ?ServerCredential $credential = null): bool
    {
        $ssh = $this->connect($server, $credential);

        try {
            $sftp = $ssh->getSFTP();
            $result = $sftp->get($remotePath, $localPath);
            
            if ($result) {
                Log::info("Successfully downloaded {$server->hostname}:{$remotePath} to {$localPath}");
            }
            
            return $result !== false;
        } catch (Exception $e) {
            Log::error("Failed to download file from {$server->hostname}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a remote file exists
     */
    public function fileExists(Server $server, string $remotePath, ?ServerCredential $credential = null): bool
    {
        $ssh = $this->connect($server, $credential);

        try {
            $sftp = $ssh->getSFTP();
            return $sftp->file_exists($remotePath);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create a directory on remote server
     */
    public function createDirectory(Server $server, string $remotePath, int $mode = 0755, bool $recursive = true, ?ServerCredential $credential = null): bool
    {
        $ssh = $this->connect($server, $credential);

        try {
            $sftp = $ssh->getSFTP();
            return $sftp->mkdir($remotePath, $mode, $recursive);
        } catch (Exception $e) {
            Log::error("Failed to create directory on {$server->hostname}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test connection to a server
     */
    public function testConnection(Server $server, ?ServerCredential $credential = null): bool
    {
        try {
            $ssh = $this->connect($server, $credential);
            $result = $this->execute($server, 'echo "connection test"', $credential);
            return $result['success'] && strpos($result['output'], 'connection test') !== false;
        } catch (Exception $e) {
            Log::error("Connection test failed for {$server->hostname}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close a specific connection
     */
    public function disconnect(Server $server, ?ServerCredential $credential = null): void
    {
        $poolKey = $this->getPoolKey($server, $credential ?? $server->activeCredential);
        
        if (isset($this->connectionPool[$poolKey])) {
            $this->connectionPool[$poolKey]->disconnect();
            unset($this->connectionPool[$poolKey]);
        }
    }

    /**
     * Close all connections in the pool
     */
    public function disconnectAll(): void
    {
        foreach ($this->connectionPool as $ssh) {
            if ($ssh->isConnected()) {
                $ssh->disconnect();
            }
        }
        $this->connectionPool = [];
    }

    /**
     * Get pool key for connection caching
     */
    protected function getPoolKey(Server $server, ?ServerCredential $credential): string
    {
        $credentialId = $credential ? $credential->id : 'default';
        return "server_{$server->id}_credential_{$credentialId}";
    }

    /**
     * Generate SSH key pair
     */
    public function generateKeyPair(string $passphrase = '', int $bits = 2048): array
    {
        $key = \phpseclib3\Crypt\RSA::createKey($bits);
        
        if ($passphrase) {
            $privateKey = $key->toString('OpenSSH', ['password' => $passphrase]);
        } else {
            $privateKey = $key->toString('OpenSSH');
        }
        
        $publicKey = $key->getPublicKey()->toString('OpenSSH');

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Destructor to clean up connections
     */
    public function __destruct()
    {
        $this->disconnectAll();
    }
}
