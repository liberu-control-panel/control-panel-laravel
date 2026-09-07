<?php

namespace App\Hosting\Backups;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final readonly class EncryptedFileArchive
{
    public function __construct(private Repository $config, private Filesystem $files) {}

    /** @return array{checksum: string, size: int, files: int} */
    public function create(string $source, string $workspace, #[\SensitiveParameter] string $key): array
    {
        $zip = $this->open($workspace.'/archive.zip', ZipArchive::CREATE | ZipArchive::EXCL);
        $manifest = [];
        $total = 0;
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $entry) {
                $relative = substr($entry->getPathname(), strlen($source) + 1);
                $this->validatePath($relative);
                $this->check(! $entry->isLink() && ($entry->isDir() || $entry->isFile()), 'Only regular files and directories can be backed up.');
                $this->check(count($manifest) < $this->config->get('hosting-backups.max_files'), 'The backup contains too many entries.');
                if ($entry->isDir()) {
                    $manifest[$relative] = ['type' => 'directory'];
                    $this->check($zip->addEmptyDir('files/'.$relative), 'Unable to archive a directory.');

                    continue;
                }

                $input = fopen($entry->getPathname(), 'rb');
                $this->check(is_resource($input), 'Unable to read a source file.');
                $copy = $workspace.'/entry-'.count($manifest);
                $output = fopen($copy, 'xb');
                $this->check(is_resource($output), 'Unable to stage a source file.');
                try {
                    $stat = fstat($input);
                    $current = lstat($entry->getPathname());
                    $this->check($stat !== false && $current !== false && ($stat['mode'] & 0170000) === 0100000
                        && $stat['ino'] === $current['ino'] && $stat['dev'] === $current['dev'] && $stat['nlink'] === 1
                        && realpath($entry->getPathname()) === $source.'/'.$relative, 'A source file changed or is an unsafe link.');
                    $size = stream_copy_to_stream($input, $output, $this->remainingBytes($total) + 1);
                    $this->check($size !== false && $size <= $this->remainingBytes($total), 'The backup exceeds its byte limit.');
                    $total += $size;
                } finally {
                    fclose($input);
                    fclose($output);
                }
                $manifest[$relative] = ['type' => 'file', 'size' => $size, 'sha256' => hash_file('sha256', $copy)];
                $this->check($zip->addFile($copy, 'files/'.$relative), 'Unable to archive a file.');
                $this->encrypt($zip, 'files/'.$relative, $key);
            }
            $json = json_encode(['version' => 1, 'entries' => $manifest], JSON_THROW_ON_ERROR);
            $this->check(strlen($json) <= $this->config->get('hosting-backups.max_manifest_bytes'), 'The backup manifest is too large.');
            $this->check($zip->addFromString('manifest.json', $json), 'Unable to write the backup manifest.');
            $this->encrypt($zip, 'manifest.json', $key);
        } finally {
            $this->check($zip->close(), 'Unable to finish the backup archive.');
        }

        $archive = $workspace.'/archive.zip';
        $this->verify($archive, $key);

        return ['checksum' => hash_file('sha256', $archive), 'size' => filesize($archive), 'files' => count($manifest)];
    }

    public function signature(string $archive, #[\SensitiveParameter] string $key, string $context): string
    {
        return hash_hmac_file('sha256', $archive, hash_hkdf('sha256', $key, 32, 'hosting-backups:authentication:v1:'.$context));
    }

    public function verify(string $archive, #[\SensitiveParameter] string $key, ?string $target = null): void
    {
        $zip = $this->open($archive, ZipArchive::RDONLY);
        $zip->setPassword($this->password($key));
        try {
            $stat = $zip->statName('manifest.json');
            $this->check($stat !== false && $stat['size'] <= $this->config->get('hosting-backups.max_manifest_bytes'), 'Missing or oversized backup manifest.');
            $this->check(($stat['encryption_method'] ?? null) === ZipArchive::EM_AES_256, 'The backup manifest must use AES-256 encryption.');
            $json = $zip->getFromName('manifest.json', (int) $this->config->get('hosting-backups.max_manifest_bytes') + 1);
            $this->check(is_string($json) && strlen($json) <= $this->config->get('hosting-backups.max_manifest_bytes'), 'The backup manifest cannot be decrypted or exceeds its limit.');
            $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $this->check(($manifest['version'] ?? null) === 1 && is_array($manifest['entries'] ?? null), 'Unsupported backup format.');
            $entries = $manifest['entries'];
            $this->check(count($entries) <= $this->config->get('hosting-backups.max_files') && $zip->numFiles === count($entries) + 1, 'The archive entries do not match the manifest.');
            $total = 0;
            foreach ($entries as $path => $entry) {
                $this->validatePath((string) $path);
                $this->check(is_array($entry) && in_array($entry['type'] ?? null, ['file', 'directory'], true), 'Invalid backup entry.');
                if ($entry['type'] === 'directory') {
                    $this->check($zip->locateName('files/'.$path.'/') !== false, 'Missing archived directory.');
                    if ($target !== null) {
                        $this->files->ensureDirectoryExists($target.'/'.$path, 0700);
                    }

                    continue;
                }
                $stat = $zip->statName('files/'.$path);
                $this->check($stat !== false && is_int($entry['size'] ?? null) && $entry['size'] >= 0
                    && $stat['size'] === $entry['size'] && $entry['size'] <= $this->remainingBytes($total)
                    && is_string($entry['sha256'] ?? null), 'Invalid archived file size or checksum.');
                $this->check(($stat['encryption_method'] ?? null) === ZipArchive::EM_AES_256, 'Archived file contents must use AES-256 encryption.');
                $stream = $zip->getStream('files/'.$path);
                $this->check(is_resource($stream), 'Unable to decrypt an archived file.');
                $output = null;
                try {
                    if ($target !== null) {
                        $this->files->ensureDirectoryExists(dirname($target.'/'.$path), 0700);
                        $output = fopen($target.'/'.$path, 'xb');
                        $this->check(is_resource($output), 'Restore refuses to overwrite an existing file.');
                        chmod($target.'/'.$path, 0600);
                    }
                    $hash = hash_init('sha256');
                    $size = 0;
                    while (! feof($stream)) {
                        $chunk = fread($stream, 65536);
                        $this->check($chunk !== false && ($chunk !== '' || feof($stream)), 'Unable to read an archived file.');
                        $size += strlen($chunk);
                        $this->check($size <= $entry['size'], 'An archived file exceeds its declared size.');
                        hash_update($hash, $chunk);
                        if ($output !== null) {
                            $this->check(fwrite($output, $chunk) === strlen($chunk), 'Unable to write restored file data.');
                        }
                    }
                    $this->check($size === $entry['size'] && hash_equals($entry['sha256'], hash_final($hash)), 'Restored data does not match the backup checksum.');
                    $total += $size;
                } finally {
                    fclose($stream);
                    if (is_resource($output)) {
                        fclose($output);
                    }
                }
                if ($target !== null) {
                    $this->check(hash_equals($entry['sha256'], hash_file('sha256', $target.'/'.$path)), 'Restored file verification failed on disk.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function validatePath(string $path): void
    {
        $this->check($path !== '' && ! preg_match('/[\\\\:\x00-\x1f\x7f]/', $path) && mb_check_encoding($path, 'UTF-8')
            && ! array_intersect(explode('/', $path), ['', '.', '..']), 'Unsafe archive path.');
    }

    private function remainingBytes(int $used): int
    {
        return max(0, (int) $this->config->get('hosting-backups.max_bytes') - $used);
    }

    private function encrypt(ZipArchive $zip, string $name, #[\SensitiveParameter] string $key): void
    {
        $this->check($zip->setEncryptionName($name, ZipArchive::EM_AES_256, $this->password($key)), 'AES-256 archive encryption is unavailable.');
    }

    private function password(#[\SensitiveParameter] string $key): string
    {
        return bin2hex(hash_hkdf('sha256', $key, 32, 'hosting-backups:encryption:v1'));
    }

    private function open(string $path, int $flags): ZipArchive
    {
        $zip = new ZipArchive();
        $this->check($zip->open($path, $flags) === true, 'Unable to open the backup archive.');

        return $zip;
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
