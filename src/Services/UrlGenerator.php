<?php

namespace Tsrgtm\FilamentMediaLibrary\Services;

use Illuminate\Support\Facades\URL;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class UrlGenerator
{
    /**
     * Generate stable, SEO-friendly URLs.
     * Supports signed URLs for private files automatically.
     */
    public function generate(Media $media, ?string $conversion = null): string
    {
        $diskName = $media->disk;
        $isPrivate = ($diskName === config('media-library.private_disk', 'local'));

        $routeName = $conversion ? 'media-serve.serve-conversion' : 'media-serve.serve';
        
        $params = [
            'media' => $media->id,
            'filename' => $media->file_name,
        ];

        if ($conversion) {
            $params['conversion'] = $conversion;
        }

        if ($isPrivate) {
            // Secure signed URL for private disks
            return URL::temporarySignedRoute(
                $routeName,
                now()->addMinutes(60), // Valid for 1 hour
                $params
            );
        }

        // Standard route for public disks
        return route($routeName, $params);
    }

    /**
     * Generate responsive image size URLs.
     */
    public function generateResponsiveUrl(Media $media, int $width): string
    {
        $diskName = $media->disk;
        $isPrivate = ($diskName === config('media-library.private_disk', 'local'));

        $params = [
            'media' => $media->id,
            'filename' => $media->file_name,
            'w' => $width,
        ];

        if ($isPrivate) {
            return URL::temporarySignedRoute(
                'media-serve.serve',
                now()->addMinutes(60),
                $params
            );
        }

        return route('media-serve.serve', $params);
    }
}
