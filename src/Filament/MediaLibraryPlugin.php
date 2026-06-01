<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tsrgtm\FilamentMediaLibrary\Filament\Pages\MediaSettingsPage;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;

class MediaLibraryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'media-library';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                MediaResource::class,
            ])
            ->pages([
                MediaSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // 
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
