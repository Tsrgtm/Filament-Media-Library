<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Components;

use Filament\Forms\Components\Field;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class MediaLibraryPicker extends Field
{
    protected string $view = 'media-library::filament.components.media-picker';
    
    protected string $collectionName = 'default';
    
    protected bool $isMultiple = false;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->dehydrated(false);

        // Load existing selected media from polymorphic relation
        $this->loadStateFromRelationshipsUsing(function (MediaLibraryPicker $component, ?Model $record) {
            if (!$record || !method_exists($record, 'mediaCollections')) {
                return [];
            }
            
            $media = $record->mediaItems()
                ->where('collection_name', $component->getCollectionName())
                ->get();
                
            return $component->isMultiple() ? $media->pluck('id')->toArray() : $media->first()?->id;
        });

        // Save selected media relations (creates new copy pointing to the same path)
        $this->saveRelationshipsUsing(function (MediaLibraryPicker $component, ?Model $record, $state) {
            if (!$record || !method_exists($record, 'mediaCollections')) {
                return;
            }
            
            $collectionName = $component->getCollectionName();
            $stateIds = array_filter(is_array($state) ? $state : [$state]);
            
            // Fetch copies currently attached to this model
            $myAttachedMedia = $record->mediaItems()
                ->where('collection_name', $collectionName)
                ->get();

            // Find which items were removed and delete their DB entries
            // (Note: since multiple rows can share a path, we only delete rows owned by this specific model)
            foreach ($myAttachedMedia as $media) {
                // If the state ID matches an existing model attachment row ID directly, or matches its parent file path,
                // we keep it. Otherwise, if it's not in the state IDs, we delete it.
                // Wait! Since $stateIds holds the source library media IDs, when we copy them, they get new IDs.
                // Let's match by physical 'path'! This is much safer and more reliable!
                $stillExistsByPath = false;
                foreach ($stateIds as $sourceId) {
                    $source = Media::find($sourceId);
                    if ($source && $source->path === $media->path) {
                        $stillExistsByPath = true;
                        break;
                    }
                }
                
                if (!$stillExistsByPath) {
                    $media->delete();
                }
            }
                
            // Re-fetch active paths
            $activePaths = $record->mediaItems()
                ->where('collection_name', $collectionName)
                ->pluck('path')
                ->toArray();
                
            foreach ($stateIds as $mediaId) {
                $sourceMedia = Media::find($mediaId);
                if ($sourceMedia && !in_array($sourceMedia->path, $activePaths)) {
                    // Create copy for this model to share physical file
                    $record->mediaItems()->create([
                        'collection_name' => $collectionName,
                        'disk' => $sourceMedia->disk,
                        'file_name' => $sourceMedia->file_name,
                        'name' => $sourceMedia->name,
                        'mime_type' => $sourceMedia->mime_type,
                        'size' => $sourceMedia->size,
                        'path' => $sourceMedia->path,
                        'width' => $sourceMedia->width,
                        'height' => $sourceMedia->height,
                        'responsive_images' => $sourceMedia->responsive_images,
                        'alt_text' => $sourceMedia->alt_text,
                    ]);
                }
            }
        });

        // Register the picker action
        $this->registerActions([
            Action::make('openPicker')
                ->label('Choose from Media Library')
                ->icon('heroicon-m-photo')
                ->modalWidth('7xl')
                ->modalHeading('Media Library Picker')
                ->modalSubmitActionLabel('Insert Selected Media')
                ->form(function (MediaLibraryPicker $component) {
                    return [
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                // Grid selector
                                \Filament\Schemas\Components\Group::make([
                                    \Filament\Forms\Components\TextInput::make('search')
                                        ->label('')
                                        ->placeholder('Search library...')
                                        ->live()
                                        ->afterStateUpdated(fn ($state, $set) => $set('selected_id', null)),
                                    
                                    \Filament\Forms\Components\Select::make('mime_type')
                                        ->label('')
                                        ->options([
                                            'all' => 'All Media',
                                            'image' => 'Images',
                                            'video' => 'Videos',
                                            'audio' => 'Audios',
                                            'document' => 'Documents',
                                        ])
                                        ->default('all')
                                        ->live()
                                        ->afterStateUpdated(fn ($state, $set) => $set('selected_id', null)),
                                    
                                    \Filament\Forms\Components\Radio::make('selected_id')
                                        ->label('Choose an item')
                                        ->hiddenLabel()
                                        ->options(function ($get) {
                                            $search = $get('search');
                                            $mimeGroup = $get('mime_type');
                                            
                                            $query = Media::query();
                                            if ($search) {
                                                $query->where('name', 'like', "%{$search}%")
                                                      ->orWhere('file_name', 'like', "%{$search}%");
                                            }
                                            if ($mimeGroup && $mimeGroup !== 'all') {
                                                if ($mimeGroup === 'document') {
                                                    $query->where('mime_type', 'not like', 'image/%')
                                                          ->where('mime_type', 'not like', 'video/%')
                                                          ->where('mime_type', 'not like', 'audio/%');
                                                } else {
                                                    $query->where('mime_type', 'like', "{$mimeGroup}/%");
                                                }
                                            }
                                            
                                            return $query->latest()->take(40)->pluck('name', 'id');
                                        })
                                        ->view('media-library::filament.components.media-grid-picker')
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if (!$state) return;
                                            $media = Media::find($state);
                                            if ($media) {
                                                $set('selected_name', $media->name);
                                                $set('selected_alt_text', $media->alt_text);
                                                $set('selected_mime_type', $media->mime_type);
                                                $set('selected_size', number_format($media->size / 1024, 2) . ' KB');
                                                $set('selected_dimensions', $media->isImage() ? "{$media->width} x {$media->height} px" : 'N/A');
                                                $set('selected_preview', $media->getUrl('thumb') ?: $media->getUrl());
                                            }
                                        }),
                                ])->columnSpan(2),
                                
                                // Sidebar Details
                                \Filament\Schemas\Components\Group::make([
                                    \Filament\Forms\Components\Placeholder::make('selected_preview_html')
                                        ->label('Selected Item Preview')
                                        ->content(fn ($get) => $get('selected_preview') 
                                            ? new \Illuminate\Support\HtmlString('<img src="' . $get('selected_preview') . '" class="w-full h-auto rounded border shadow-sm aspect-video object-cover" />') 
                                            : new \Illuminate\Support\HtmlString('<div class="flex items-center justify-center border-2 border-dashed rounded aspect-video text-gray-400">Select an item to view preview</div>')),
                                    
                                    \Filament\Forms\Components\TextInput::make('selected_name')
                                        ->label('Title')
                                        ->required()
                                        ->visible(fn ($get) => $get('selected_id') !== null),
                                    
                                    \Filament\Forms\Components\TextInput::make('selected_alt_text')
                                        ->label('Alt Text')
                                        ->visible(fn ($get) => $get('selected_id') !== null),
                                    
                                    \Filament\Schemas\Components\Grid::make(1)
                                        ->schema([
                                            \Filament\Forms\Components\Placeholder::make('selected_mime_type')
                                                ->label('Mime Type')
                                                ->content(fn ($get) => $get('selected_mime_type') ?: '-'),
                                            
                                            \Filament\Forms\Components\Placeholder::make('selected_size')
                                                ->label('File Size')
                                                ->content(fn ($get) => $get('selected_size') ?: '-'),
                                            
                                            \Filament\Forms\Components\Placeholder::make('selected_dimensions')
                                                ->label('Dimensions')
                                                ->content(fn ($get) => $get('selected_dimensions') ?: '-'),
                                        ])
                                        ->visible(fn ($get) => $get('selected_id') !== null),
                                ])->columnSpan(1),
                            ])
                    ];
                })
                ->action(function (array $data, MediaLibraryPicker $component) {
                    $mediaId = $data['selected_id'];
                    if (!$mediaId) return;
                    
                    $media = Media::find($mediaId);
                    if ($media) {
                        // Update metadata in library
                        $media->update([
                            'name' => $data['selected_name'],
                            'alt_text' => $data['selected_alt_text'],
                        ]);
                        
                        // Set the component state
                        if ($component->isMultiple()) {
                            $currentState = is_array($component->getState()) ? $component->getState() : [];
                            if (!in_array($mediaId, $currentState)) {
                                $currentState[] = $mediaId;
                            }
                            $component->state($currentState);
                        } else {
                            $component->state($mediaId);
                        }
                    }
                })
        ]);
    }

    public function collection(string $collectionName): self
    {
        $this->collectionName = $collectionName;
        return $this;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    public function multiple(bool $condition = true): self
    {
        $this->isMultiple = $condition;
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }
}
