<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /**
     * Override standard form for custom multi-upload layout.
     */
    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Upload Assets')
                    ->description('Drag and drop files to add them to the media library.')
                    ->schema([
                        Forms\Components\FileUpload::make('files')
                            ->label('Select Files')
                            ->multiple()
                            ->required()
                            ->maxSize(config('media-library.max_file_size') / 1024) // in KB
                            ->acceptedFileTypes(config('media-library.allowed_mimes'))
                            ->panelLayout('grid')
                            ->storeFiles(false), // Handled manually by FileAddor
                        
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('collection_name')
                                    ->label('Target Collection')
                                    ->options(fn () => Media::distinct()->pluck('collection_name', 'collection_name')->merge(['default' => 'default'])->all())
                                    ->default('default')
                                    ->searchable()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('New Folder / Collection Name')
                                            ->required()
                                            ->maxLength(255)
                                    ])
                                    ->createOptionUsing(fn (array $data) => $data['name'])
                                    ->required(),

                                Forms\Components\TextInput::make('alt_text')
                                    ->label('Alternative Text')
                                    ->placeholder('Alternative description for accessibility (images only)'),
                            ]),

                        Forms\Components\Toggle::make('generate_responsive_images')
                            ->label('Generate Responsive Images')
                            ->default(true)
                            ->live(),

                        Forms\Components\CheckboxList::make('responsive_widths')
                            ->label('Responsive Widths')
                            ->options([
                                480 => '480px (Mobile)',
                                800 => '800px (Tablet)',
                                1200 => '1200px (Desktop)',
                            ])
                            ->default([480, 800, 1200])
                            ->visible(fn (callable $get) => $get('generate_responsive_images')),
                    ]),
            ]);
    }

    /**
     * Handle the record creation process manually.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $files = $data['files'] ?? [];
        $collection = $data['collection_name'] ?? 'default';
        $alt = $data['alt_text'] ?? '';
        $generateResponsive = $data['generate_responsive_images'] ?? true;
        $widths = $generateResponsive ? ($data['responsive_widths'] ?? null) : [];

        $lastMedia = null;

        // Use a temporary media object to leverage HasMedia trait API
        $dummy = new Media();
        $dummy->id = 1; // temporary placeholder ID

        foreach ($files as $file) {
            // Add media using our polymorphic Fluent API
            $lastMedia = $dummy->addMedia($file)
                ->withAltText($alt)
                ->withResponsiveWidths($widths)
                ->toCollection($collection);

            // Update polymorphic properties to point to itself
            $lastMedia->update([
                'model_type' => Media::class,
                'model_id' => $lastMedia->id,
            ]);
        }

        return $lastMedia;
    }
}
