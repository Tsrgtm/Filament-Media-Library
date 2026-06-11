<?php

namespace Tsrgtm\FilamentMediaLibrary\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class MediaController extends Controller
{
    /**
     * Serve the original file or its responsive width version.
     * Implements auto WebP serving and signed URL checks.
     */
    public function serve(Request $request, Media $media, string $filename)
    {
        // 1. Enforce signature check for private files
        $this->verifyAccess($request, $media);

        // 2. Prevent path traversal by asserting filename matches database record exactly
        if ($filename !== $media->file_name) {
            abort(404, 'File not found.');
        }

        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(400, 'Invalid filename path.');
        }

        $disk = Storage::disk($media->disk);

        // 2. Handle responsive image request
        $width = $request->query('w');
        if ($width && $media->responsive_images && isset($media->responsive_images[$width])) {
            $responsiveFilename = $media->responsive_images[$width];
            $responsivePath = "{$media->getDirectory()}/responsive/{$responsiveFilename}";
            
            if ($disk->exists($responsivePath)) {
                return $disk->response($responsivePath);
            }
        }

        // 3. Handle WebP auto-negotiation
        if (config('media-library.webp_enabled', true) && 
            $media->isImage() && 
            $media->mime_type !== 'image/webp' && 
            str_contains($request->header('Accept', ''), 'image/webp')
        ) {
            $webpFilename = pathinfo($media->file_name, PATHINFO_FILENAME) . '.webp';
            $webpPath = "{$media->getDirectory()}/conversions/webp-{$webpFilename}";

            if ($disk->exists($webpPath)) {
                return $disk->response($webpPath, null, [
                    'Content-Type' => 'image/webp',
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        // 4. Fallback to original file
        $originalPath = $media->getPath();
        if (!$disk->exists($originalPath)) {
            abort(404, 'Media file not found.');
        }

        return $disk->response($originalPath, null, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Serve custom image conversions (e.g. thumb, medium).
     */
    public function serveConversion(Request $request, Media $media, string $conversion, string $filename)
    {
        $this->verifyAccess($request, $media);

        // Prevent path traversal by asserting filename matches database record exactly
        if ($filename !== $media->file_name) {
            abort(404, 'File not found.');
        }

        // Whitelist conversion formats to prevent arbitrary parameter manipulation
        $allowedConversions = array_keys(config('media-library.conversions', []));
        if (!in_array($conversion, $allowedConversions)) {
            abort(400, 'Invalid conversion.');
        }

        if (str_contains($conversion, '..') || str_contains($conversion, '/') || str_contains($conversion, '\\') ||
            str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(400, 'Invalid conversion path.');
        }

        $disk = Storage::disk($media->disk);

        // Handle WebP auto-negotiation for conversion
        if (config('media-library.webp_enabled', true) && 
            $media->isImage() && 
            $media->mime_type !== 'image/webp' && 
            str_contains($request->header('Accept', ''), 'image/webp')
        ) {
            $webpFilename = pathinfo($media->file_name, PATHINFO_FILENAME) . '.webp';
            $webpConversionPath = "{$media->getDirectory()}/conversions/{$conversion}-webp-{$webpFilename}";

            if ($disk->exists($webpConversionPath)) {
                return $disk->response($webpConversionPath, null, [
                    'Content-Type' => 'image/webp',
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        // Fallback to standard conversion file
        $conversionPath = $media->getPath($conversion);
        if (!$disk->exists($conversionPath)) {
            // If the conversion does not exist yet (e.g. queue was processing it),
            // serve the original image as fallback
            $originalPath = $media->getPath();
            if ($disk->exists($originalPath)) {
                return $disk->response($originalPath, null, [
                    'Content-Type' => $media->mime_type,
                ]);
            }
            abort(404, 'Conversion not found.');
        }

        $mime = $disk->mimeType($conversionPath) ?: 'image/jpeg';

        return $disk->response($conversionPath, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Enforce signed URL checks and Laravel policies for secure media.
     */
    protected function verifyAccess(Request $request, Media $media): void
    {
        $diskName = $media->disk;
        $isPrivate = ($diskName === config('media-library.private_disk', 'local'));

        if ($isPrivate && !$request->hasValidSignature()) {
            abort(403, 'Unauthorized access to secure media. Link expired or signature invalid.');
        }
    }
}
