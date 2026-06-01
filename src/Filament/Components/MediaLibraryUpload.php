<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Components;

use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class MediaLibraryUpload extends FileUpload
{
    protected string $collectionName = 'default';

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent saving paths directly to parent model attributes
        $this->dehydrated(false);

        // Load existing media items from the polymorphic relation
        $this->loadStateFromRelationshipsUsing(function (FileUpload $component, ?Model $record) {
            if (!$record || !method_exists($record, 'mediaCollections')) {
                return [];
            }

            $media = $record->mediaItems()
                ->where('collection_name', $component->getCollectionName())
                ->get();

            // Map database IDs to file names/paths for Filament preview UI
            return $media->pluck('path', 'id')->toArray();
        });

        // Save new uploads and remove deleted items from storage/database
        $this->saveRelationshipsUsing(function (FileUpload $component, ?Model $record, $state) {
            if (!$record || !method_exists($record, 'mediaCollections')) {
                return;
            }

            $collectionName = $component->getCollectionName();
            
            // Clean up state array to find active IDs (Filament passes keys as database IDs for pre-existing files)
            $existingMediaIds = array_keys(array_filter(is_array($state) ? $state : [], fn ($val) => !($val instanceof TemporaryUploadedFile)));

            // Delete removed media entries
            $record->mediaItems()
                ->where('collection_name', $collectionName)
                ->whereNotIn('id', $existingMediaIds)
                ->get()
                ->each(fn (Media $media) => $media->delete());

            // Process new uploads
            $files = is_array($state) ? $state : [$state];
            foreach ($files as $file) {
                if ($file instanceof TemporaryUploadedFile || $file instanceof \Illuminate\Http\UploadedFile) {
                    $record->addMedia($file)->toCollection($collectionName);
                }
            }
        });
    }

    /**
     * Define the target polymorphic media collection name.
     */
    public function collection(string $collectionName): self
    {
        $this->collectionName = $collectionName;
        return $this;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }
}
