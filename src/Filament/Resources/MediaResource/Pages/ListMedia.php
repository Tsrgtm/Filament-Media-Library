<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        $isGrid = session('media_library_view', 'grid') === 'grid';

        return [
            Actions\Action::make('toggleView')
                ->label($isGrid ? 'Show List View' : 'Show Grid View')
                ->icon($isGrid ? 'heroicon-o-list-bullet' : 'heroicon-o-squares-2x2')
                ->color('gray')
                ->action(function () use ($isGrid) {
                    session(['media_library_view' => $isGrid ? 'list' : 'grid']);
                }),
            Actions\CreateAction::make(),
        ];
    }
}
