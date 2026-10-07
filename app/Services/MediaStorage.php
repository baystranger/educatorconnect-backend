<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploads on the configured public or private disk and builds their URLs.
 * Public media (photos, videos, logos) is served directly; private media (résumés,
 * claim documents) is only ever streamed through an authorised controller action.
 */
class MediaStorage
{
    public function storePublic(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, $this->publicDisk());
    }

    public function storePrivate(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, $this->privateDisk());
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk($this->publicDisk())->url($path);
    }

    public function deletePublic(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk($this->publicDisk())->delete($path);
        }
    }

    public function deletePrivate(?string $path): void
    {
        if ($path) {
            Storage::disk($this->privateDisk())->delete($path);
        }
    }

    public function privateDisk(): string
    {
        return config('platform.uploads.private_disk', 'local');
    }

    public function publicDisk(): string
    {
        return config('platform.uploads.disk', 'public');
    }
}
