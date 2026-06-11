<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests\Feature;

use Tsrgtm\FilamentMediaLibrary\Models\MediaSetting;
use Tsrgtm\FilamentMediaLibrary\Tests\TestCase;

class FilamentIntegrationTest extends TestCase
{
    /** @test */
    public function it_can_set_and_retrieve_dynamic_settings()
    {
        MediaSetting::set('disk', 's3');
        MediaSetting::set('webp_enabled', false);

        $this->assertEquals('s3', MediaSetting::get('disk'));
        $this->assertEquals('false', MediaSetting::get('webp_enabled'));
    }

    /** @test */
    public function it_correctly_persists_and_reads_configurations()
    {
        MediaSetting::set('max_file_size', 10485760); // 10MB
        
        $this->assertEquals('10485760', MediaSetting::get('max_file_size'));
    }

    /** @test */
    public function it_can_instantiate_custom_media_library_upload_component()
    {
        $component = \Tsrgtm\FilamentMediaLibrary\Filament\Components\MediaLibraryUpload::make('avatar')
            ->collection('avatar');

        $this->assertInstanceOf(\Tsrgtm\FilamentMediaLibrary\Filament\Components\MediaLibraryUpload::class, $component);
        $this->assertEquals('avatar', $component->getCollectionName());
    }

    /** @test */
    public function it_can_instantiate_custom_media_library_picker_component()
    {
        $component = \Tsrgtm\FilamentMediaLibrary\Filament\Components\MediaLibraryPicker::make('featured_image')
            ->collection('featured_image');

        $this->assertInstanceOf(\Tsrgtm\FilamentMediaLibrary\Filament\Components\MediaLibraryPicker::class, $component);
        $this->assertEquals('featured_image', $component->getCollectionName());
    }

    /** @test */
    public function it_has_valid_list_media_page()
    {
        $this->assertTrue(class_exists(\Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages\ListMedia::class));
        $this->assertEquals(
            \Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource::class,
            \Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages\ListMedia::getResource()
        );
    }
}
