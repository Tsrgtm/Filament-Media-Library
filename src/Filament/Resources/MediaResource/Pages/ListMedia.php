<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Models\MediaSetting;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected string $view = 'media-library::filament.pages.list-media';

    public ?string $search = '';
    public ?string $activeCollection = 'all';
    public ?string $activeType = 'all';
    public ?string $filterTrashed = 'without';
    public ?string $viewLayout = 'grid';
    public bool $showSettings = true;
    public ?int $selectedMediaId = null;

    public function mount(): void
    {
        parent::mount();

        try {
            if (\Filament\Facades\Filament::getCurrentPanel() && \Filament\Facades\Filament::getCurrentPanel()->hasPlugin('media-library')) {
                /** @var \Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin $plugin */
                $plugin = \Filament\Facades\Filament::getCurrentPanel()->getPlugin('media-library');
                $this->showSettings = $plugin->shouldShowSettings();
            }
        } catch (\Throwable $e) {}
    }

    protected $queryString = [
        'search' => ['except' => ''],
        'activeCollection' => ['except' => 'all'],
        'activeType' => ['except' => 'all'],
        'filterTrashed' => ['except' => 'without'],
        'viewLayout' => ['except' => 'grid'],
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedActiveCollection()
    {
        $this->resetPage();
    }

    public function updatedActiveType()
    {
        $this->resetPage();
    }

    public function updatedFilterTrashed()
    {
        $this->resetPage();
    }

    public function selectCollection(string $collection): void
    {
        $this->activeCollection = $collection;
        $this->filterTrashed = 'without';
        $this->resetPage();
    }

    public function getRecordsProperty()
    {
        $query = Media::query();

        if ($this->search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('file_name', 'like', "%{$this->search}%")
            );
        }

        if ($this->activeCollection && $this->activeCollection !== 'all') {
            $query->where('collection_name', $this->activeCollection);
        }

        if ($this->activeType && $this->activeType !== 'all') {
            if ($this->activeType === 'document') {
                $query->where('mime_type', 'not like', 'image/%')
                      ->where('mime_type', 'not like', 'video/%')
                      ->where('mime_type', 'not like', 'audio/%');
            } else {
                $query->where('mime_type', 'like', "{$this->activeType}/%");
            }
        }

        if ($this->filterTrashed === 'only') {
            $query->onlyTrashed();
        } elseif ($this->filterTrashed === 'with') {
            $query->withTrashed();
        } else {
            $query->withoutTrashed();
        }

        return $query->latest()->paginate(24);
    }

    public function getFolders(): array
    {
        return Media::withTrashed()
            ->select('collection_name')
            ->distinct()
            ->pluck('collection_name')
            ->toArray();
    }

    public function getCollectionFileCount(string $collection): int
    {
        return Media::where('collection_name', $collection)->count();
    }

    public function getTrashedCount(): int
    {
        return Media::onlyTrashed()->count();
    }

    protected function getActions(): array
    {
        $actions = [
            $this->uploadAction(),
            $this->editAction(),
            $this->cropAction(),
            $this->trimAction(),
            $this->editDocxAction(),
            $this->editPdfAction(),
            $this->editTextAction(),
            $this->convertAction(),
            $this->deleteAction(),
            $this->restoreAction(),
            $this->forceDeleteAction(),
        ];

        if ($this->showSettings) {
            $actions[] = $this->settingsAction();
        }

        return $actions;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function uploadAction(): Actions\Action
    {
        return Actions\Action::make('upload')
            ->label('Upload Files')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading('Upload New Media')
            ->modalWidth('xl')
            ->form([
                Forms\Components\FileUpload::make('files')
                    ->label('Select Files')
                    ->multiple()
                    ->required()
                    ->maxSize(config('media-library.max_file_size') / 1024)
                    ->acceptedFileTypes(config('media-library.allowed_mimes'))
                    ->panelLayout('grid')
                    ->storeFiles(false),
                
                Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('collection_name')
                            ->label('Target Folder / Collection')
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
                            ->placeholder('Accessibility description (images only)'),
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

            ])
            ->action(function (array $data) {
                $files = $data['files'] ?? [];
                $collection = $data['collection_name'] ?? 'default';
                $alt = $data['alt_text'] ?? '';
                $generateResponsive = $data['generate_responsive_images'] ?? true;
                $widths = $generateResponsive ? ($data['responsive_widths'] ?? null) : [];

                $dummy = new Media();
                $dummy->id = 1;

                $count = 0;
                try {
                    foreach ($files as $file) {
                        $media = $dummy->addMedia($file)
                            ->withAltText($alt)
                            ->withResponsiveWidths($widths)
                            ->toCollection($collection);

                        $media->update([
                            'model_type' => Media::class,
                            'model_id' => $media->id,
                        ]);
                        $count++;
                    }

                    $this->resetPage();

                    Notification::make()
                        ->title("{$count} file(s) uploaded successfully.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Upload Failed')
                        ->body('There was an issue uploading your file: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function settingsAction(): Actions\Action
    {
        return Actions\Action::make('settings')
            ->label('Settings')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->modalWidth('4xl')
            ->fillForm(fn () => [
                'disk' => MediaSetting::get('disk', config('media-library.disk')),
                'private_disk' => MediaSetting::get('private_disk', config('media-library.private_disk')),
                'max_file_size' => MediaSetting::get('max_file_size', config('media-library.max_file_size')) / (1024 * 1024),
                'webp_enabled' => (bool)MediaSetting::get('webp_enabled', config('media-library.webp_enabled')),
                'image_quality' => (int)MediaSetting::get('image_quality', config('media-library.image_quality')),
                'cache_enabled' => (bool)MediaSetting::get('cache.enabled', config('media-library.cache.enabled')),
                'queue_enabled' => (bool)MediaSetting::get('queue.enabled', config('media-library.queue.enabled')),
                'duplicate_detection_enabled' => (bool)MediaSetting::get('duplicate_detection.enabled', config('media-library.duplicate_detection.enabled')),
                'duplicate_detection_strategy' => MediaSetting::get('duplicate_detection.strategy', config('media-library.duplicate_detection.strategy')),
            ])
            ->form([
                Grid::make(2)
                    ->schema([
                        Section::make('Storage & Optimization')
                            ->description('Configure file destinations and automatic image compression.')
                            ->schema([
                                Select::make('disk')
                                    ->label('Public Disk')
                                    ->options([
                                        'public' => 'Public Local Storage',
                                        's3' => 'AWS S3 / S3-Compatible',
                                    ])
                                    ->required(),
                                Select::make('private_disk')
                                    ->label('Private Disk')
                                    ->options([
                                        'local' => 'Private Local Storage',
                                        's3' => 'AWS S3 (Private)',
                                    ])
                                    ->required(),
                                TextInput::make('max_file_size')
                                    ->label('Max Upload Size (MB)')
                                    ->numeric()
                                    ->required()
                                    ->suffix('MB'),
                                Toggle::make('webp_enabled')
                                    ->label('Enable Auto WebP')
                                    ->helperText('Convert uploaded images to WebP automatically to save bandwidth.')
                                    ->live(),
                                Slider::make('image_quality')
                                    ->label('Image Quality')
                                    ->minValue(10)
                                    ->maxValue(100)
                                    ->step(5)
                                    ->visible(fn (callable $get) => $get('webp_enabled')),
                            ])->columnSpan(1),

                        Section::make('Engine & Security Settings')
                            ->description('Performance parameters, duplicate file checks, and queues.')
                            ->schema([
                                Toggle::make('cache_enabled')
                                    ->label('Enable Cache')
                                    ->helperText('Cache media lookup queries and URLs for faster page rendering.'),
                                Toggle::make('queue_enabled')
                                    ->label('Queue Image Processing')
                                    ->helperText('Run crop, resize, and WebP generation on background workers instead of immediate upload.'),
                                Toggle::make('duplicate_detection_enabled')
                                    ->label('Enable Duplicate Check')
                                    ->helperText('Perform SHA-256 hash checks on uploads to protect against storage waste.')
                                    ->live(),
                                Select::make('duplicate_detection_strategy')
                                    ->label('Duplicate Mitigation')
                                    ->options([
                                        'link' => 'Link Reference (Reuses existing physical file)',
                                        'separate' => 'Separate Files (Save duplicate physically but record hash)',
                                    ])
                                    ->visible(fn (callable $get) => $get('duplicate_detection_enabled')),
                            ])->columnSpan(1),
                    ]),
            ])
            ->action(function (array $data) {
                MediaSetting::set('disk', $data['disk']);
                MediaSetting::set('private_disk', $data['private_disk']);
                MediaSetting::set('max_file_size', $data['max_file_size'] * 1024 * 1024);
                MediaSetting::set('webp_enabled', $data['webp_enabled']);
                MediaSetting::set('image_quality', $data['image_quality'] ?? 80);
                MediaSetting::set('cache.enabled', $data['cache_enabled']);
                MediaSetting::set('queue.enabled', $data['queue_enabled']);
                MediaSetting::set('duplicate_detection.enabled', $data['duplicate_detection_enabled']);
                MediaSetting::set('duplicate_detection.strategy', $data['duplicate_detection_strategy'] ?? 'link');

                Notification::make()
                    ->title('Media settings saved successfully.')
                    ->success()
                    ->send();
            });
    }

    public function editAction(): Actions\Action
    {
        return Actions\Action::make('edit')
            ->label('Edit Metadata')
            ->slideOver()
            ->modalWidth('xl')
            ->fillForm(function (array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                $state = $record->toArray();
                $state['regenerate_responsive_images'] = false;
                $state['responsive_widths'] = $record->responsive_images ? array_keys($record->responsive_images) : [480, 800, 1200];
                return $state;
            })
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('alt_text')
                    ->label('Alt Text')
                    ->maxLength(255)
                    ->placeholder('Alternative text for accessibility'),
                Forms\Components\FileUpload::make('replacement_file')
                    ->label('Replace Media File')
                    ->helperText('Upload a new file to replace the current media file. Title and metadata are preserved.')
                    ->maxSize(fn() => config('media-library.max_file_size') / 1024)
                    ->acceptedFileTypes(config('media-library.allowed_mimes'))
                    ->nullable()
                    ->storeFiles(false),
                Forms\Components\KeyValue::make('custom_properties')
                    ->label('Custom Properties')
                    ->keyLabel('Key')
                    ->valueLabel('Value'),
                Forms\Components\Toggle::make('regenerate_responsive_images')
                    ->label('Regenerate Responsive Images')
                    ->default(false)
                    ->live(),
                Forms\Components\CheckboxList::make('responsive_widths')
                    ->label('Responsive Widths')
                    ->options([
                        480 => '480px (Mobile)',
                        800 => '800px (Tablet)',
                        1200 => '1200px (Desktop)',
                    ])
                    ->default([480, 800, 1200])
                    ->visible(fn (callable $get) => $get('regenerate_responsive_images')),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                
                $regenerate = $data['regenerate_responsive_images'] ?? false;
                $widths = $data['responsive_widths'] ?? [];
                $replacementFile = $data['replacement_file'] ?? null;
                
                unset($data['regenerate_responsive_images'], $data['responsive_widths'], $data['replacement_file']);
                
                $record->update($data);

                try {
                    if ($replacementFile) {
                        $record->replaceFile($replacementFile);
                        if ($record->isImage()) {
                            if (config('media-library.queue.enabled', false)) {
                                \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record, $widths);
                            } else {
                                \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatchSync($record, $widths);
                            }
                        }
                    } elseif ($regenerate && $record->isImage()) {
                        if (config('media-library.queue.enabled', false)) {
                            \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record, $widths);
                        } else {
                            \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatchSync($record, $widths);
                        }
                    }

                    Notification::make()
                        ->title('Media item updated successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Replacement Failed')
                        ->body('Failed to replace file: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function cropAction(): Actions\Action
    {
        return Actions\Action::make('crop')
            ->label('Crop Image')
            ->modalWidth('md')
            ->form([
                Forms\Components\TextInput::make('x')
                    ->label('X Coordinate')
                    ->integer()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('y')
                    ->label('Y Coordinate')
                    ->integer()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('width')
                    ->label('Crop Width')
                    ->integer()
                    ->required(),
                Forms\Components\TextInput::make('height')
                    ->label('Crop Height')
                    ->integer()
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                if (!$record->isImage()) return;

                $disk = Storage::disk($record->disk);
                if (!$disk->exists($record->path)) return;

                try {
                    $originalData = $disk->get($record->path);
                    $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                    $image = $manager->read($originalData);

                    $image->crop((int)$data['width'], (int)$data['height'], (int)$data['x'], (int)$data['y']);
                    
                    $format = pathinfo($record->file_name, PATHINFO_EXTENSION);
                    $encodedData = match(strtolower($format)) {
                        'jpeg', 'jpg' => $image->toJpeg()->toString(),
                        'png' => $image->toPng()->toString(),
                        'gif' => $image->toGif()->toString(),
                        'webp' => $image->toWebp()->toString(),
                        default => $image->toWebp()->toString(),
                    };

                    $disk->put($record->path, $encodedData);
                    $record->update([
                        'size' => strlen($encodedData),
                        'width' => $image->width(),
                        'height' => $image->height(),
                    ]);

                    // Regrow conversions & responsive variants
                    \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record);

                    Notification::make()
                        ->title('Image cropped successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Cropping Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function trimAction(): Actions\Action
    {
        return Actions\Action::make('trim')
            ->label('Trim Video')
            ->modalWidth('md')
            ->form([
                Forms\Components\TextInput::make('start')
                    ->label('Start Time (seconds)')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('end')
                    ->label('End Time (seconds)')
                    ->numeric()
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                try {
                    app(\Tsrgtm\FilamentMediaLibrary\Services\VideoProcessor::class)
                        ->trim($record, (float)$data['start'], (float)$data['end']);

                    Notification::make()
                        ->title('Video trimmed successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Trimming Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function editDocxAction(): Actions\Action
    {
        return Actions\Action::make('editDocx')
            ->label('DOCX Find & Replace')
            ->modalWidth('md')
            ->form([
                Forms\Components\TextInput::make('find')
                    ->label('Find Text')
                    ->required(),
                Forms\Components\TextInput::make('replace')
                    ->label('Replace with')
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                try {
                    app(\Tsrgtm\FilamentMediaLibrary\Services\DocxEditor::class)
                        ->findAndReplace($record, $data['find'], $data['replace']);

                    Notification::make()
                        ->title('DOCX text replaced successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Text Replacement Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function editPdfAction(): Actions\Action
    {
        return Actions\Action::make('editPdf')
            ->label('PDF Metadata Editor')
            ->modalWidth('md')
            ->fillForm(function (array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                $props = $record->custom_properties ?? [];
                return [
                    'title' => $props['pdf_title'] ?? $record->name,
                    'author' => $props['pdf_author'] ?? '',
                    'subject' => $props['pdf_subject'] ?? '',
                    'keywords' => $props['pdf_keywords'] ?? '',
                    'creator' => $props['pdf_creator'] ?? '',
                ];
            })
            ->form([
                Forms\Components\TextInput::make('title')
                    ->label('Title')
                    ->required(),
                Forms\Components\TextInput::make('author')
                    ->label('Author'),
                Forms\Components\TextInput::make('subject')
                    ->label('Subject'),
                Forms\Components\TextInput::make('keywords')
                    ->label('Keywords'),
                Forms\Components\TextInput::make('creator')
                    ->label('Creator'),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                try {
                    app(\Tsrgtm\FilamentMediaLibrary\Services\PdfEditor::class)
                        ->updateMetadata($record, $data);

                    Notification::make()
                        ->title('PDF metadata updated successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('PDF Edit Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function editTextAction(): Actions\Action
    {
        return Actions\Action::make('editText')
            ->label('Edit Text Content')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                $disk = Storage::disk($record->disk);
                $content = $disk->exists($record->path) ? $disk->get($record->path) : '';
                return [
                    'content' => $content,
                ];
            })
            ->form([
                Forms\Components\Textarea::make('content')
                    ->label('File Content')
                    ->rows(15)
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                try {
                    $disk = Storage::disk($record->disk);
                    $disk->put($record->path, $data['content']);
                    $record->update([
                        'size' => strlen($data['content']),
                        'hash' => hash('sha256', $data['content']),
                    ]);
                    Notification::make()
                        ->title('Text file saved successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Edit Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function convertAction(): Actions\Action
    {
        return Actions\Action::make('convert')
            ->label('Convert / Resize')
            ->modalWidth('md')
            ->form([
                Forms\Components\Select::make('format')
                    ->label('Target Format')
                    ->options([
                        'jpeg' => 'JPEG',
                        'png' => 'PNG',
                        'webp' => 'WebP',
                    ])
                    ->default('webp')
                    ->required(),
                Forms\Components\Slider::make('quality')
                    ->label('Compression Quality')
                    ->minValue(10)
                    ->maxValue(100)
                    ->step(5)
                    ->default(80)
                    ->required(),
                Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('width')
                            ->label('Max Width')
                            ->integer()
                            ->placeholder('e.g. 1920'),
                        Forms\Components\TextInput::make('height')
                            ->label('Max Height')
                            ->integer()
                            ->placeholder('e.g. 1080'),
                    ]),
            ])
            ->action(function (array $data, array $arguments) {
                $record = Media::findOrFail($arguments['record']);
                if (!$record->isImage()) return;

                $disk = Storage::disk($record->disk);
                $originalPath = $record->getPath();
                if (!$disk->exists($originalPath)) return;

                $originalData = $disk->get($originalPath);
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($originalData);

                $width = $data['width'] ?: null;
                $height = $data['height'] ?: null;
                if ($width || $height) {
                    $image->scale($width, $height);
                }

                $quality = (int) $data['quality'];
                $format = $data['format'];
                $mimeType = 'image/' . $format;

                $encodedData = match($format) {
                    'jpeg' => $image->toJpeg($quality)->toString(),
                    'png' => $image->toPng()->toString(),
                    'webp' => $image->toWebp($quality)->toString(),
                    default => $image->toWebp($quality)->toString(),
                };

                $disk->put($originalPath, $encodedData);
                $record->update([
                    'mime_type' => $mimeType,
                    'size' => strlen($encodedData),
                    'width' => $image->width(),
                    'height' => $image->height(),
                ]);

                \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record);

                Notification::make()
                    ->title('Image converted successfully.')
                    ->success()
                    ->send();
            });
    }

    public function deleteAction(): Actions\Action
    {
        return Actions\DeleteAction::make('delete')
            ->record(fn (array $arguments) => Media::findOrFail($arguments['record']))
            ->after(function () {
                $this->resetPage();
                Notification::make()
                    ->title('Media moved to trash.')
                    ->success()
                    ->send();
            });
    }

    public function restoreAction(): Actions\Action
    {
        return Actions\RestoreAction::make('restore')
            ->record(fn (array $arguments) => Media::onlyTrashed()->findOrFail($arguments['record']))
            ->after(function () {
                $this->resetPage();
                Notification::make()
                    ->title('Media item restored.')
                    ->success()
                    ->send();
            });
    }

    public function forceDeleteAction(): Actions\Action
    {
        return Actions\ForceDeleteAction::make('forceDelete')
            ->record(fn (array $arguments) => Media::onlyTrashed()->findOrFail($arguments['record']))
            ->after(function () {
                $this->resetPage();
                Notification::make()
                    ->title('Media permanently deleted.')
                    ->success()
                    ->send();
            });
    }

    public function selectMedia(?int $id): void
    {
        $this->selectedMediaId = $id;
    }

    public function getSelectedMediaProperty(): ?Media
    {
        if (!$this->selectedMediaId) return null;
        return Media::find($this->selectedMediaId);
    }

    public function saveVisualCrop(int $mediaId, int $x, int $y, int $width, int $height): void
    {
        $record = Media::findOrFail($mediaId);
        if (!$record->isImage()) return;

        $disk = Storage::disk($record->disk);
        if (!$disk->exists($record->path)) return;

        try {
            $originalData = $disk->get($record->path);
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($originalData);

            $image->crop($width, $height, $x, $y);
            
            $format = pathinfo($record->file_name, PATHINFO_EXTENSION);
            $encodedData = match(strtolower($format)) {
                'jpeg', 'jpg' => $image->toJpeg()->toString(),
                'png' => $image->toPng()->toString(),
                'gif' => $image->toGif()->toString(),
                'webp' => $image->toWebp()->toString(),
                default => $image->toWebp()->toString(),
            };

            $disk->put($record->path, $encodedData);
            $record->update([
                'size' => strlen($encodedData),
                'width' => $image->width(),
                'height' => $image->height(),
            ]);

            // Regrow conversions & responsive variants
            \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('Image cropped successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Cropping Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveVisualTrim(int $mediaId, float $start, float $end): void
    {
        $record = Media::findOrFail($mediaId);
        try {
            app(\Tsrgtm\FilamentMediaLibrary\Services\VideoProcessor::class)
                ->trim($record, $start, $end);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('Video trimmed successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Trimming Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveVisualDocxReplace(int $mediaId, string $find, string $replace): void
    {
        $record = Media::findOrFail($mediaId);
        try {
            app(\Tsrgtm\FilamentMediaLibrary\Services\DocxEditor::class)
                ->findAndReplace($record, $find, $replace);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('DOCX text replaced successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Text Replacement Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveSvgContent(int $mediaId, string $content): void
    {
        $record = Media::findOrFail($mediaId);
        if (!$record->isSvg()) return;

        try {
            $disk = Storage::disk($record->disk);
            $disk->put($record->path, $content);
            
            $width = null;
            $height = null;
            $xml = @simplexml_load_string($content);
            if ($xml) {
                $attrs = $xml->attributes();
                if (isset($attrs->width)) $width = (int) $attrs->width;
                if (isset($attrs->height)) $height = (int) $attrs->height;
                if (!$width && !$height && isset($attrs->viewBox)) {
                    $parts = explode(' ', $attrs->viewBox);
                    if (count($parts) === 4) {
                        $width = (int) $parts[2];
                        $height = (int) $parts[3];
                    }
                }
            }

            $record->update([
                'size' => strlen($content),
                'hash' => hash('sha256', $content),
                'width' => $width ?: $record->width,
                'height' => $height ?: $record->height,
            ]);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('SVG file saved successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('SVG Save Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveVisualPdfMeta(int $mediaId, array $metadata): void
    {
        $record = Media::findOrFail($mediaId);
        try {
            app(\Tsrgtm\FilamentMediaLibrary\Services\PdfEditor::class)
                ->updateMetadata($record, $metadata);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('PDF metadata updated successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('PDF Edit Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveVisualTextContent(int $mediaId, string $content): void
    {
        $record = Media::findOrFail($mediaId);
        try {
            $disk = Storage::disk($record->disk);
            $disk->put($record->path, $content);
            $record->update([
                'size' => strlen($content),
                'hash' => hash('sha256', $content),
            ]);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('Text file saved successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Edit Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveVisualMetadata(int $mediaId, string $name, ?string $altText): void
    {
        $record = Media::findOrFail($mediaId);
        try {
            $record->update([
                'name' => $name,
                'alt_text' => $altText,
            ]);

            $this->dispatch('media-updated');

            Notification::make()
                ->title('Metadata saved successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Save Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getErrorBag(): \Illuminate\Contracts\Support\MessageBag
    {
        try {
            return parent::getErrorBag() ?? new \Illuminate\Support\MessageBag();
        } catch (\Throwable $e) {
            return new \Illuminate\Support\MessageBag();
        }
    }
}

