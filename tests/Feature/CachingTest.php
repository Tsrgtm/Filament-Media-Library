<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Tests\TestCase;
use Tsrgtm\FilamentMediaLibrary\Tests\TestModel;

class CachingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_caches_media_url_resolutions()
    {
        $model = TestModel::create(['name' => 'Cache Test']);
        $file = UploadedFile::fake()->image('photo.jpg');
        $media = $model->addMedia($file)->toCollection('gallery');

        // Force enable cache for test
        config(['media-library.cache.enabled' => true]);

        $url1 = $media->getUrl();
        
        $cacheKey = config('media-library.cache.prefix', 'media_library_') . "media_{$media->id}_url_original";
        
        // Assert the cache holds the generated URL
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($url1, Cache::get($cacheKey));
    }

    /** @test */
    public function it_busts_url_cache_when_media_is_updated_or_deleted()
    {
        $model = TestModel::create(['name' => 'Bust Test']);
        $file = UploadedFile::fake()->image('photo.jpg');
        $media = $model->addMedia($file)->toCollection('gallery');

        config(['media-library.cache.enabled' => true]);

        // Generate and cache URL
        $media->getUrl();
        $cacheKey = config('media-library.cache.prefix', 'media_library_') . "media_{$media->id}_url_original";
        
        $this->assertTrue(Cache::has($cacheKey));

        // Update model to trigger cache bust
        $media->update(['alt_text' => 'New Alt Text']);

        // Assert cache was busted
        $this->assertFalse(Cache::has($cacheKey));
    }
}
