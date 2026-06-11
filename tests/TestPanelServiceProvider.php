<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests;

use Filament\Panel;
use Filament\PanelProvider;
use Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin;

class TestPanelServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->plugin(MediaLibraryPlugin::make());
    }
}
