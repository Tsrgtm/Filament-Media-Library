<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages;

use Filament\Forms;
use Filament\Forms\Form;
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
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload Assets')
                    ->description('Drag and drop files to add them to the media library.')
                    ->schema([
                        Forms\Components\FileUpload::make('files')
                            ->label('Select Files')
                            ->multiple()
                            ->required()
                            ->maxSize(config('media-library.max_file_size') / 1024) // in KB
                            ->acceptedFileTypes(config('media-library.allowed_mimes'))
                            ->storeFiles(false), // Handled manually by FileAddor
                        
                        Forms\Components\Select::make('collection_name')
                            ->label('Target Collection')
                            ->options([
                                'default' => 'Default',
                                'featured_image' => 'Featured Image',
                                'images' => 'Images',
                                'documents' => 'Documents',
                            ])
                            ->default('default')
                            ->required(),

                        Forms\Components\TextInput::make('alt_text')
                            ->label('Alternative Text')
                            ->placeholder('Alternative description for accessibility (images only)'),
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

        $lastMedia = null;

        // Use a temporary media object to leverage HasMedia trait API
        $dummy = new Media();
        $dummy->id = 1; // temporary placeholder ID

        foreach ($files as $file) {
            // Add media using our polymorphic Fluent API
            $lastMedia = $dummy->addMedia($file)
                ->withAltText($alt)
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
