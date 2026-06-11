<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;
use Tsrgtm\FilamentMediaLibrary\Tests\TestCase;

class PluginCustomizationTest extends TestCase
{
    /** @test */
    public function it_can_customize_resource_navigation()
    {
        $panel = new Panel();
        $panel->id('test-panel');
        
        $plugin = MediaLibraryPlugin::make()
            ->icon('heroicon-o-folder')
            ->label('Custom Media')
            ->navigationGroup('System')
            ->navigationSort(5)
            ->slug('custom-media');
            
        $panel->plugin($plugin);
        
        Filament::setCurrentPanel($panel);
        
        $this->assertEquals('heroicon-o-folder', MediaResource::getNavigationIcon());
        $this->assertEquals('Custom Media', MediaResource::getNavigationLabel());
        $this->assertEquals('System', MediaResource::getNavigationGroup());
        $this->assertEquals(5, MediaResource::getNavigationSort());
        $this->assertEquals('custom-media', MediaResource::getSlug());
    }

    /** @test */
    public function it_can_resolve_resource_pages()
    {
        $pages = MediaResource::getPages();
        
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
