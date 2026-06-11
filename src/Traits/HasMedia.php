<?php

namespace Tsrgtm\FilamentMediaLibrary\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Support\FileAddor;

trait HasMedia
{
    /**
     * Boot the HasMedia trait to delete related media when the model is deleted.
     */
    public static function bootHasMedia(): void
    {
        static::deleted(function ($model) {
            // Check if it's force deleting (SoftDeletes check)
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                foreach ($model->mediaItems()->withTrashed()->get() as $media) {
                    $media->forceDelete();
                }
            } else {
                $model->mediaItems()->delete();
            }
        });
    }

    /**
     * Get the polymorphic relation.
     */
    public function mediaItems(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->orderBy('order_column');
    }

    /**
     * Define the media collections for this model.
     * Override this method on your model to configure collections.
     *
     * Example:
     * [
     *     'featured_image' => [
     *         'single' => true,
     *         'conversions' => ['webp'],
     *         'fallback' => '/images/default-featured.png'
     *     ],
     *     'images' => [
     *         'multiple' => true,
     *     ],
     * ]
     */
    public function mediaCollections(): array
    {
        return [];
    }

    /**
     * Retrieve media items for a specific collection.
     * If the collection is defined as single, returns the first media model.
     * Otherwise, returns an Eloquent Collection.
     */
    public function media(?string $collection = null)
    {
        if (!$collection) {
            return $this->mediaItems()->get();
        }

        $collections = $this->mediaCollections();
        $isSingle = $collections[$collection]['single'] ?? false;

        if ($isSingle) {
            return $this->getFirstMedia($collection);
        }

        return $this->getMedia($collection);
    }

    /**
     * Get all media in a collection (cached/eager-loaded safe).
     */
    public function getMedia(string $collectionName = 'default'): Collection
    {
        if (!config('media-library.cache.enabled')) {
            return $this->mediaItems->where('collection_name', $collectionName);
        }

        $cacheKey = config('media-library.cache.prefix', 'media_library_') . "model_" . str_replace('\\', '-', get_class($this)) . "_{$this->id}_media";

        $allMedia = Cache::remember($cacheKey, config('media-library.cache.ttl', 3600), function () {
            return $this->mediaItems()->get();
        });

        return $allMedia->where('collection_name', $collectionName);
    }

    /**
     * Get the first media model in a collection.
     */
    public function getFirstMedia(string $collectionName = 'default'): ?Media
    {
        return $this->getMedia($collectionName)->first();
    }

    /**
     * Get the first media file URL.
     */
    public function getFirstMediaUrl(string $collectionName = 'default', ?string $conversion = null): string
    {
        $media = $this->getFirstMedia($collectionName);
        
        if ($media) {
            return $media->getUrl($conversion);
        }

        return $this->getFallbackMediaUrl($collectionName);
    }

    /**
     * Get dynamic fallback media URL.
     */
    public function getFallbackMediaUrl(string $collectionName): string
    {
        $collections = $this->mediaCollections();
        return $collections[$collectionName]['fallback'] ?? '';
    }

    /**
     * Start the Fluent API for adding a file.
     * 
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file Path or UploadedFile
     */
    public function addMedia($file): FileAddor
    {
        return new FileAddor($this, $file);
    }
}
