<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileManagerService
{
    public function listFiles(string $path = ''): array
    {
        $files = Storage::files($path);
        $directories = Storage::directories($path);

        $items = [];

        foreach ($directories as $directory) {
            $items[] = [
                'name' => basename($directory),
                'type' => 'directory',
                'size' => '-',
                'last_modified' => Storage::lastModified($directory),
                'path' => $directory,
            ];
        }

        foreach ($files as $file) {
            $items[] = [
                'name' => basename($file),
                'type' => 'file',
                'size' => $this->formatSize(Storage::size($file)),
                'last_modified' => Storage::lastModified($file),
                'path' => $file,
            ];
        }

        return $items;
    }

    public function uploadFile(string $path, $file): void
    {
        Storage::putFileAs($path, $file, $file->getClientOriginalName());
    }

    public function deleteFile(string $path): void
    {
        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    private function formatSize(int $sizeInBytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $sizeInBytes > 0 ? floor(log($sizeInBytes, 1024)) : 0;
        return number_format($sizeInBytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
}