<?php

namespace App\Http\Controllers;

use App\Services\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves uploaded public media (photos, videos, logos) at /storage/{path}.
 *
 * The web server normally serves these straight from the public_html/storage link. When
 * that link is missing or the host does not follow it, .htaccess hands the request to
 * Laravel and this controller streams the file instead. BinaryFileResponse answers Range
 * requests, so videos can be played and skipped through.
 */
class PublicFileController extends Controller
{
    public function __invoke(MediaStorage $media, string $path): BinaryFileResponse
    {
        $disk = Storage::disk($media->publicDisk());
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));

        // Only real files inside the public uploads folder; no hidden files, no "../" escapes.
        abort_unless(
            $root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)
                && ! str_contains($path, '/.') && ! str_starts_with($path, '.'),
            404
        );

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
