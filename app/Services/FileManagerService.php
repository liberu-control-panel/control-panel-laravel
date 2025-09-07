<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class FileManagerService
{
    protected $containerManager;

    public function __construct(ContainerManagerService $containerManager)
    {
        $this->containerManager = $containerManager;
    }

    /**
     * Get directory listing for domain
     */
    public function getDirectoryListing(Domain $domain, string $path = '/'): array
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $fullPath = $this->sanitizePath($path);

            // Execute ls command in container
            $process = new Process([
                'docker', 'exec', $containerName,
                'ls', '-la', '--time-style=+%Y-%m-%d %H:%M:%S', $fullPath
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('Failed to list directory: ' . $process->getErrorOutput());
            }

            return $this->parseDirectoryListing($process->getOutput(), $path);
        } catch (\Exception $e) {
            Log::error("Failed to get directory listing for {$domain->domain_name}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'files' => []
            ];
        }
    }

    /**
     * Parse directory listing output
     */
    protected function parseDirectoryListing(string $output, string $currentPath): array
    {
        $lines = explode("\n", trim($output));
        $files = [];

        // Skip first line (total)
        array_shift($lines);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $parts = preg_split('/\s+/', $line, 9);
            if (count($parts) < 9) continue;

            $permissions = $parts[0];
            $links = $parts[1];
            $owner = $parts[2];
            $group = $parts[3];
            $size = $parts[4];
            $date = $parts[5];
            $time = $parts[6];
            $name = $parts[8];

            // Skip . and .. entries
            if ($name === '.' || $name === '..') continue;

            $isDirectory = $permissions[0] === 'd';
            $isSymlink = $permissions[0] === 'l';

            $files[] = [
                'name' => $name,
                'path' => rtrim($currentPath, '/') . '/' . $name,
                'type' => $isDirectory ? 'directory' : ($isSymlink ? 'symlink' : 'file'),
                'size' => $isDirectory ? 0 : (int) $size,
                'size_human' => $isDirectory ? '-' : $this->formatBytes((int) $size),
                'permissions' => $permissions,
                'owner' => $owner,
                'group' => $group,
                'modified' => $date . ' ' . $time,
                'is_readable' => $this->checkPermission($permissions, 'r'),
                'is_writable' => $this->checkPermission($permissions, 'w'),
                'is_executable' => $this->checkPermission($permissions, 'x')
            ];
        }

        // Sort: directories first, then files, alphabetically
        usort($files, function($a, $b) {
            if ($a['type'] === 'directory' && $b['type'] !== 'directory') return -1;
            if ($a['type'] !== 'directory' && $b['type'] === 'directory') return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'success' => true,
            'path' => $currentPath,
            'files' => $files
        ];
    }

    /**
     * Check file permissions
     */
    protected function checkPermission(string $permissions, string $type): bool
    {
        $ownerPerms = substr($permissions, 1, 3);
        return strpos($ownerPerms, $type) !== false;
    }

    /**
     * Create directory
     */
    public function createDirectory(Domain $domain, string $path, string $name): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $fullPath = $this->sanitizePath($path . '/' . $name);

            $process = new Process([
                'docker', 'exec', $containerName,
                'mkdir', '-p', $fullPath
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('Failed to create directory: ' . $process->getErrorOutput());
            }

            // Set proper permissions
            $this->setPermissions($domain, $fullPath, '755');

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create directory {$name} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload file to domain
     */
    public function uploadFile(Domain $domain, string $path, UploadedFile $file): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $fileName = $file->getClientOriginalName();
            $tempPath = $file->store('temp');
            $fullPath = $this->sanitizePath($path . '/' . $fileName);

            // Copy file to container
            $copyProcess = new Process([
                'docker', 'cp', 
                storage_path('app/' . $tempPath),
                "{$containerName}:{$fullPath}"
            ]);
            $copyProcess->run();

            // Clean up temp file
            Storage::delete($tempPath);

            if (!$copyProcess->isSuccessful()) {
                throw new \Exception('Failed to upload file: ' . $copyProcess->getErrorOutput());
            }

            // Set proper permissions
            $this->setPermissions($domain, $fullPath, '644');

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to upload file {$file->getClientOriginalName()} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Download file from domain
     */
    public function downloadFile(Domain $domain, string $filePath): ?string
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($filePath);
            $tempFile = tempnam(sys_get_temp_dir(), 'download_');

            // Copy file from container
            $copyProcess = new Process([
                'docker', 'cp',
                "{$containerName}:{$sanitizedPath}",
                $tempFile
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new \Exception('Failed to download file: ' . $copyProcess->getErrorOutput());
            }

            return $tempFile;
        } catch (\Exception $e) {
            Log::error("Failed to download file {$filePath} for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete file or directory
     */
    public function delete(Domain $domain, string $path): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($path);

            $process = new Process([
                'docker', 'exec', $containerName,
                'rm', '-rf', $sanitizedPath
            ]);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to delete {$path} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rename file or directory
     */
    public function rename(Domain $domain, string $oldPath, string $newName): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $oldSanitizedPath = $this->sanitizePath($oldPath);
            $newPath = dirname($oldPath) . '/' . $newName;
            $newSanitizedPath = $this->sanitizePath($newPath);

            $process = new Process([
                'docker', 'exec', $containerName,
                'mv', $oldSanitizedPath, $newSanitizedPath
            ]);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to rename {$oldPath} to {$newName} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy file or directory
     */
    public function copy(Domain $domain, string $sourcePath, string $destinationPath): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sourceSanitized = $this->sanitizePath($sourcePath);
            $destinationSanitized = $this->sanitizePath($destinationPath);

            $process = new Process([
                'docker', 'exec', $containerName,
                'cp', '-r', $sourceSanitized, $destinationSanitized
            ]);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to copy {$sourcePath} to {$destinationPath} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file content
     */
    public function getFileContent(Domain $domain, string $filePath): ?string
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($filePath);

            $process = new Process([
                'docker', 'exec', $containerName,
                'cat', $sanitizedPath
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('Failed to read file: ' . $process->getErrorOutput());
            }

            return $process->getOutput();
        } catch (\Exception $e) {
            Log::error("Failed to get content of {$filePath} for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save file content
     */
    public function saveFileContent(Domain $domain, string $filePath, string $content): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($filePath);
            $tempFile = tempnam(sys_get_temp_dir(), 'edit_');

            // Write content to temp file
            file_put_contents($tempFile, $content);

            // Copy temp file to container
            $copyProcess = new Process([
                'docker', 'cp', $tempFile, "{$containerName}:{$sanitizedPath}"
            ]);
            $copyProcess->run();

            // Clean up temp file
            unlink($tempFile);

            if (!$copyProcess->isSuccessful()) {
                throw new \Exception('Failed to save file: ' . $copyProcess->getErrorOutput());
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to save content to {$filePath} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set file/directory permissions
     */
    public function setPermissions(Domain $domain, string $path, string $permissions): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($path);

            $process = new Process([
                'docker', 'exec', $containerName,
                'chmod', $permissions, $sanitizedPath
            ]);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to set permissions for {$path} on {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change file/directory ownership
     */
    public function changeOwnership(Domain $domain, string $path, string $owner, string $group = null): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($path);
            $ownership = $group ? "{$owner}:{$group}" : $owner;

            $process = new Process([
                'docker', 'exec', $containerName,
                'chown', '-R', $ownership, $sanitizedPath
            ]);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to change ownership for {$path} on {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get disk usage for domain
     */
    public function getDiskUsage(Domain $domain, string $path = '/'): array
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($path);

            // Get disk usage
            $duProcess = new Process([
                'docker', 'exec', $containerName,
                'du', '-sh', $sanitizedPath
            ]);
            $duProcess->run();

            $usage = [
                'path' => $path,
                'size_human' => 'Unknown',
                'size_bytes' => 0
            ];

            if ($duProcess->isSuccessful()) {
                $output = trim($duProcess->getOutput());
                $parts = explode("\t", $output);
                if (count($parts) >= 2) {
                    $usage['size_human'] = $parts[0];
                    $usage['size_bytes'] = $this->parseSize($parts[0]);
                }
            }

            // Get detailed breakdown
            $detailProcess = new Process([
                'docker', 'exec', $containerName,
                'du', '-s', '--block-size=1', $sanitizedPath . '/*'
            ]);
            $detailProcess->run();

            $breakdown = [];
            if ($detailProcess->isSuccessful()) {
                $lines = explode("\n", trim($detailProcess->getOutput()));
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $parts = explode("\t", $line);
                    if (count($parts) >= 2) {
                        $breakdown[] = [
                            'path' => basename($parts[1]),
                            'size_bytes' => (int) $parts[0],
                            'size_human' => $this->formatBytes((int) $parts[0])
                        ];
                    }
                }
            }

            $usage['breakdown'] = $breakdown;

            return $usage;
        } catch (\Exception $e) {
            Log::error("Failed to get disk usage for {$domain->domain_name}: " . $e->getMessage());
            return [
                'path' => $path,
                'size_human' => 'Error',
                'size_bytes' => 0,
                'breakdown' => []
            ];
        }
    }

    /**
     * Search files
     */
    public function searchFiles(Domain $domain, string $query, string $path = '/', array $options = []): array
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPath = $this->sanitizePath($path);

            $findArgs = ['docker', 'exec', $containerName, 'find', $sanitizedPath];

            // Add search criteria
            if (isset($options['name'])) {
                $findArgs[] = '-name';
                $findArgs[] = "*{$query}*";
            } else {
                $findArgs[] = '-type';
                $findArgs[] = 'f';
                $findArgs[] = '-exec';
                $findArgs[] = 'grep';
                $findArgs[] = '-l';
                $findArgs[] = $query;
                $findArgs[] = '{}';
                $findArgs[] = ';';
            }

            $process = new Process($findArgs);
            $process->setTimeout(30); // 30 second timeout
            $process->run();

            $results = [];
            if ($process->isSuccessful()) {
                $lines = explode("\n", trim($process->getOutput()));
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        $results[] = [
                            'path' => $line,
                            'name' => basename($line),
                            'directory' => dirname($line)
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'query' => $query,
                'path' => $path,
                'results' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            Log::error("Failed to search files for {$domain->domain_name}: " . $e->getMessage());
            return [
                'success' => false,
                'query' => $query,
                'path' => $path,
                'results' => [],
                'count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create archive
     */
    public function createArchive(Domain $domain, array $paths, string $archiveName, string $format = 'zip'): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedPaths = array_map([$this, 'sanitizePath'], $paths);

            if ($format === 'zip') {
                $command = ['docker', 'exec', $containerName, 'zip', '-r', $archiveName];
                $command = array_merge($command, $sanitizedPaths);
            } else {
                $command = ['docker', 'exec', $containerName, 'tar', '-czf', $archiveName];
                $command = array_merge($command, $sanitizedPaths);
            }

            $process = new Process($command);
            $process->setTimeout(300); // 5 minute timeout
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to create archive for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract archive
     */
    public function extractArchive(Domain $domain, string $archivePath, string $destinationPath): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $sanitizedArchive = $this->sanitizePath($archivePath);
            $sanitizedDestination = $this->sanitizePath($destinationPath);

            // Determine archive type
            $extension = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

            if ($extension === 'zip') {
                $command = ['docker', 'exec', $containerName, 'unzip', $sanitizedArchive, '-d', $sanitizedDestination];
            } else {
                $command = ['docker', 'exec', $containerName, 'tar', '-xzf', $sanitizedArchive, '-C', $sanitizedDestination];
            }

            $process = new Process($command);
            $process->setTimeout(300); // 5 minute timeout
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to extract archive for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sanitize file path to prevent directory traversal
     */
    protected function sanitizePath(string $path): string
    {
        // Remove any attempts at directory traversal
        $path = str_replace(['../', '..\\'], '', $path);

        // Ensure path starts with /var/www/html (web root)
        if (!str_starts_with($path, '/var/www/html')) {
            $path = '/var/www/html' . '/' . ltrim($path, '/');
        }

        // Normalize path
        return rtrim($path, '/');
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Parse size string to bytes
     */
    protected function parseSize(string $size): int
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = floatval($size);

        return match($unit) {
            'K' => (int) ($value * 1024),
            'M' => (int) ($value * 1024 * 1024),
            'G' => (int) ($value * 1024 * 1024 * 1024),
            'T' => (int) ($value * 1024 * 1024 * 1024 * 1024),
            default => (int) $value
        };
    }
}