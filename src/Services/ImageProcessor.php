<?php

namespace Tsrgtm\FilamentMediaLibrary\Services;

use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ImageProcessor
{
    protected ImageManager $manager;

    public function __construct()
    {
        // Default to GD driver for maximum host compatibility
        $this->manager = new ImageManager(new GdDriver());
    }

    /**
     * Process conversions, responsive images, and WebP version for a Media item.
     */
    public function process(Media $media, ?array $widths = null): void
    {
        if (!$media->isImage()) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $originalPath = $media->getPath();

        if (!$disk->exists($originalPath)) {
            return;
        }

        $originalData = $disk->get($originalPath);

        // 1. Generate WebP original fallback if enabled
        if (config('media-library.webp_enabled', true) && $media->mime_type !== 'image/webp') {
            try {
                $image = $this->manager->read($originalData);
                $webpData = $image->toWebp(config('media-library.image_quality', 80))->toString();
                
                $webpPath = $media->getDirectory() . "/conversions/webp-" . pathinfo($media->file_name, PATHINFO_FILENAME) . ".webp";
                $disk->put($webpPath, $webpData);
            } catch (\Exception $e) {
                logger()->error("Failed to generate WebP for media {$media->id}: " . $e->getMessage());
            }
        }

        // 2. Process configured conversions
        $this->processCustomConversions($media, $originalData, $disk);

        // 3. Process responsive images
        $this->processResponsiveImages($media, $originalData, $disk, $widths);
    }

    /**
     * Process custom image conversions defined in config or model.
     */
    protected function processCustomConversions(Media $media, string $originalData, $disk): void
    {
        // Get custom conversions configured for this model's collection
        $modelClass = $media->model_type;
        $conversionsToProcess = [];

        if (class_exists($modelClass)) {
            $modelInstance = new $modelClass();
            if (method_exists($modelInstance, 'mediaCollections')) {
                $collections = $modelInstance->mediaCollections();
                $conversionsToProcess = $collections[$media->collection_name]['conversions'] ?? [];
            }
        }

        // If nothing model-specific is set, use defaults from config
        if (empty($conversionsToProcess)) {
            $conversionsToProcess = array_keys(config('media-library.conversions', []));
        }

        $allConfigConversions = config('media-library.conversions', []);

        foreach ($conversionsToProcess as $conversionName) {
            // Find conversion configuration
            $conversionConfig = $allConfigConversions[$conversionName] ?? null;
            if (!$conversionConfig) {
                continue;
            }

            try {
                $image = $this->manager->read($originalData);

                // Resize/Crop
                $width = $conversionConfig['width'] ?? null;
                $height = $conversionConfig['height'] ?? null;

                if ($conversionConfig['fit'] ?? false) {
                    $image->cover($width, $height);
                } else {
                    $image->scale($width, $height);
                }

                // Encode to original mime-type (or webp if preferred)
                $encodedData = $this->encodeByMimeType($image, $media->mime_type);

                // Put conversion on disk
                $conversionPath = $media->getPath($conversionName);
                $disk->put($conversionPath, $encodedData);

                // If WebP is enabled, also generate WebP for this conversion
                if (config('media-library.webp_enabled', true) && $media->mime_type !== 'image/webp') {
                    $webpConversionData = $image->toWebp(config('media-library.image_quality', 80))->toString();
                    $webpConversionPath = $media->getDirectory() . "/conversions/{$conversionName}-webp-" . pathinfo($media->file_name, PATHINFO_FILENAME) . ".webp";
                    $disk->put($webpConversionPath, $webpConversionData);
                }
            } catch (\Exception $e) {
                logger()->error("Failed processing conversion '{$conversionName}' for media {$media->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process and generate responsive images at multiple widths.
     */
    protected function processResponsiveImages(Media $media, string $originalData, $disk, ?array $widths = null): void
    {
        // Clean up old responsive files if they exist
        if ($media->responsive_images) {
            foreach ($media->responsive_images as $oldFile) {
                $oldPath = $media->getDirectory() . "/responsive/{$oldFile}";
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }
            $media->update(['responsive_images' => null]);
        }

        if ($widths === null) {
            $widths = config('media-library.responsive_widths', [480, 800, 1200]);
        }

        if (empty($widths)) {
            return;
        }

        $responsiveMap = [];

        try {
            $originalImage = $this->manager->read($originalData);
            $origWidth = $originalImage->width();

            foreach ($widths as $width) {
                // Only generate for widths smaller than the original
                if ($width >= $origWidth) {
                    continue;
                }

                $image = $this->manager->read($originalData);
                $image->scale(width: $width);

                $encodedData = $this->encodeByMimeType($image, $media->mime_type);
                
                $filename = "{$width}-{$media->file_name}";
                $responsivePath = $media->getDirectory() . "/responsive/{$filename}";
                
                $disk->put($responsivePath, $encodedData);
                $responsiveMap[$width] = $filename;
            }

            if (!empty($responsiveMap)) {
                $media->update(['responsive_images' => $responsiveMap]);
            }
        } catch (\Exception $e) {
            logger()->error("Failed to generate responsive images for media {$media->id}: " . $e->getMessage());
        }
    }

    /**
     * Helper to encode an image by mime type using Intervention Image v3.
     */
    protected function encodeByMimeType($image, string $mimeType): string
    {
        $quality = config('media-library.image_quality', 80);

        if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
            return $image->toJpeg($quality)->toString();
        }
        if ($mimeType === 'image/png') {
            return $image->toPng()->toString();
        }
        if ($mimeType === 'image/gif') {
            return $image->toGif()->toString();
        }
        if ($mimeType === 'image/webp') {
            return $image->toWebp($quality)->toString();
        }
        
        return $image->toWebp($quality)->toString(); // fallback
    }
}
