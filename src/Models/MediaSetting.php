<?php

namespace Tsrgtm\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MediaSetting extends Model
{
    protected $table = 'media_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected static function boot()
    {
        parent::boot();

        // Clear dynamic configuration settings cache on save
        static::saved(function () {
            Cache::forget('media_library_settings');
        });
    }

    /**
     * Get setting value by key, with dynamic fallback.
     */
    public static function get(string $key, $default = null)
    {
        $settings = Cache::remember('media_library_settings', 86400, function () {
            return static::pluck('value', 'key')->all();
        });

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $stringValue = $value;
        if (is_bool($value)) {
            $stringValue = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $stringValue = implode(',', $value);
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string)$stringValue]
        );
        
        Cache::forget('media_library_settings');
    }
}
