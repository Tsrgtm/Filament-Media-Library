<?php

namespace Tsrgtm\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Services\UrlGenerator;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'model_type',
        'model_id',
        'collection_name',
        'name',
        'file_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'custom_properties',
        'responsive_images',
        'hash',
        'order_column',
    ];

    protected $casts = [
        'custom_properties' => 'array',
        'responsive_images' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        // Automatically bust cache on change
        static::saved(function (Media $media) {
            $media->bustCache();
        });

        static::deleted(function (Media $media) {
            $media->bustCache();
        });

        static::restored(function (Media $media) {
            $media->bustCache();
        });
    }

    /**
     * Get the parent model.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the type of file (image, video, audio, document)
     */
    public function getType(): string
    {
        if (str_starts_with($this->mime_type, 'image/')) {
            return 'image';
        }
        if (str_starts_with($this->mime_type, 'video/')) {
            return 'video';
        }
        if (str_starts_with($this->mime_type, 'audio/')) {
            return 'audio';
        }
        return 'document';
    }

    /**
     * Check if the media item is an image.
     */
    public function isImage(): bool
    {
        return $this->getType() === 'image';
    }

    /**
     * Get the path to the original file or a conversion.
     */
    public function getPath(?string $conversion = null): string
    {
        $directory = $this->getDirectory();
        
        if ($conversion) {
            return "{$directory}/conversions/{$conversion}-{$this->file_name}";
        }

        return $this->path;
    }

    /**
     * Get the directory where this media is stored.
     */
    public function getDirectory(): string
    {
        return dirname($this->path);
    }

    /**
     * Get the public URL for this media item.
     */
    public function getUrl(?string $conversion = null): string
    {
        if (!config('media-library.cache.enabled')) {
            return app(UrlGenerator::class)->generate($this, $conversion);
        }

        $cacheKey = $this->getCacheKey("url_" . ($conversion ?? 'original'));

        return Cache::remember($cacheKey, config('media-library.cache.ttl', 3600), function () use ($conversion) {
            return app(UrlGenerator::class)->generate($this, $conversion);
        });
    }

    /**
     * Get the HTML img tag with srcset support if responsive images exist.
     */
    public function img(string $conversion = null, array $attributes = []): string
    {
        if (!$this->isImage()) {
            return '';
        }

        $url = $this->getUrl($conversion);
        $srcset = $this->getSrcsetAttribute();
        $alt = e($this->alt_text ?? $this->name);

        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            $htmlAttributes .= " {$key}=\"" . e($value) . "\"";
        }

        $srcsetString = $srcset ? " srcset=\"{$srcset}\" sizes=\"(max-width: 1200px) 100vw, 1200px\"" : '';

        return "<img src=\"{$url}\" alt=\"{$alt}\"{$srcsetString}{$htmlAttributes} />";
    }

    /**
     * Generate responsive srcset value.
     */
    public function getSrcsetAttribute(): ?string
    {
        if (!$this->responsive_images || empty($this->responsive_images)) {
            return null;
        }

        $srcset = [];
        foreach ($this->responsive_images as $width => $fileName) {
            // Get URL for this responsive version
            $url = app(UrlGenerator::class)->generateResponsiveUrl($this, $width);
            $srcset[] = "{$url} {$width}w";
        }

        return !empty($srcset) ? implode(', ', $srcset) : null;
    }

    /**
     * Bust all cache entries related to this model.
     */
    public function bustCache(): void
    {
        if (!config('media-library.cache.enabled')) {
            return;
        }

        $prefix = config('media-library.cache.prefix', 'media_library_');
        
        // Clear URLs cache
        Cache::forget($prefix . "media_{$this->id}_url_original");
        foreach (array_keys(config('media-library.conversions', [])) as $conversion) {
            Cache::forget($prefix . "media_{$this->id}_url_{$conversion}");
        }

        // Clear query cache for the parent model's relation
        Cache::forget($prefix . "model_{$this->model_type}_{$this->model_id}_media");
    }

    /**
     * Manual cache clear helper.
     */
    public static function clearCache(): void
    {
        if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
            Cache::tags(['media_library'])->flush();
        } else {
            // Fallback for file/database cache, might require clearing whole cache or we just rely on individual key busting.
            // Under Laravel, we can't easily query wildcard keys without tags, so we warn or clear standard keys.
            Cache::flush();
        }
    }

    /**
     * Generate unique cache key.
     */
    protected function getCacheKey(string $suffix): string
    {
        return config('media-library.cache.prefix', 'media_library_') . "media_{$this->id}_{$suffix}";
    }
}
