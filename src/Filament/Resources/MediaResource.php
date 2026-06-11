<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $slug = 'media-library';

    protected static ?string $modelLabel = 'Media';

    protected static ?string $pluralModelLabel = 'Media Library';

    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        try {
            if (\Filament\Facades\Filament::getCurrentPanel() && \Filament\Facades\Filament::getCurrentPanel()->hasPlugin('media-library')) {
                /** @var \Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin $plugin */
                $plugin = \Filament\Facades\Filament::getCurrentPanel()->getPlugin('media-library');
                return $plugin->getNavigationIcon() ?? static::$navigationIcon;
            }
        } catch (\Throwable $e) {}

        return static::$navigationIcon;
    }

    public static function getNavigationLabel(): string
    {
        try {
            if (\Filament\Facades\Filament::getCurrentPanel() && \Filament\Facades\Filament::getCurrentPanel()->hasPlugin('media-library')) {
                /** @var \Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin $plugin */
                $plugin = \Filament\Facades\Filament::getCurrentPanel()->getPlugin('media-library');
                return $plugin->getNavigationLabel() ?? parent::getNavigationLabel();
            }
        } catch (\Throwable $e) {}

        return parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        try {
            if (\Filament\Facades\Filament::getCurrentPanel() && \Filament\Facades\Filament::getCurrentPanel()->hasPlugin('media-library')) {
                /** @var \Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin $plugin */
                $plugin = \Filament\Facades\Filament::getCurrentPanel()->getPlugin('media-library');
                return $plugin->getNavigationGroup() ?? parent::getNavigationGroup();
            }
        } catch (\Throwable $e) {}

        return parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        try {
            if (\Filament\Facades\Filament::getCurrentPanel() && \Filament\Facades\Filament::getCurrentPanel()->hasPlugin('media-library')) {
                /** @var \Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin $plugin */
                $plugin = \Filament\Facades\Filament::getCurrentPanel()->getPlugin('media-library');
                return $plugin->getNavigationSort() ?? parent::getNavigationSort();
            }
        } catch (\Throwable $e) {}

        return parent::getNavigationSort();
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        try {
            if (\Filament\Facades\Filament::getCurrentPanel() && \Filament\Facades\Filament::getCurrentPanel()->hasPlugin('media-library')) {
                /** @var \Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin $plugin */
                $plugin = \Filament\Facades\Filament::getCurrentPanel()->getPlugin('media-library');
                return $plugin->getSlug() ?? parent::getSlug($panel);
            }
        } catch (\Throwable $e) {}

        return parent::getSlug($panel);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Media Metadata')
                    ->description('Details about this media asset.')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('File Preview')
                            ->content(function (?Media $record) {
                                if (!$record) return '-';
                                if ($record->isImage()) {
                                    return new \Illuminate\Support\HtmlString('<img src="' . $record->getUrl() . '" class="max-w-full h-auto rounded-lg border shadow-sm max-h-[250px] object-contain mx-auto" />');
                                }
                                if ($record->isVideo()) {
                                    return new \Illuminate\Support\HtmlString('<video src="' . $record->getUrl() . '" controls class="max-w-full h-auto rounded-lg border shadow-sm max-h-[250px] bg-black mx-auto" />');
                                }
                                if ($record->isPdf()) {
                                    return new \Illuminate\Support\HtmlString('<iframe src="' . $record->getUrl() . '" class="w-full h-[350px] rounded-lg border shadow-sm bg-white" />');
                                }
                                if ($record->mime_type === 'audio/mpeg' || $record->mime_type === 'audio/ogg' || $record->mime_type === 'audio/wav') {
                                    return new \Illuminate\Support\HtmlString('<div class="flex flex-col items-center justify-center p-4 border rounded-lg bg-gray-50 dark:bg-gray-900"><audio src="' . $record->getUrl() . '" controls class="w-full" /></div>');
                                }
                                $svgPath = match($record->getType()) {
                                    'video' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25Z" />',
                                    'audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                                    'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                                    default => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />',
                                };
                                $color = match($record->getType()) {
                                    'video' => 'text-purple-500',
                                    'audio' => 'text-indigo-500',
                                    'document' => 'text-blue-500',
                                    default => 'text-gray-400 dark:text-gray-500',
                                };
                                return new \Illuminate\Support\HtmlString('<div class="flex items-center justify-center border rounded-lg h-32 bg-gray-50 dark:bg-gray-900"><svg class="w-12 h-12 ' . $color . '" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">' . $svgPath . '</svg></div>');
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('file_name')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\FileUpload::make('replacement_file')
                            ->label('Replace Media File')
                            ->helperText('Upload a new file to replace the current media file. Title and metadata are preserved.')
                            ->maxSize(fn() => config('media-library.max_file_size') / 1024)
                            ->acceptedFileTypes(config('media-library.allowed_mimes'))
                            ->nullable()
                            ->storeFiles(false),
                        Forms\Components\TextInput::make('alt_text')
                            ->maxLength(255)
                            ->placeholder('Alternative text for images'),
                        Forms\Components\KeyValue::make('custom_properties')
                            ->label('Custom Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                        Forms\Components\Toggle::make('regenerate_responsive_images')
                            ->label('Regenerate Responsive Images')
                            ->default(false)
                            ->dehydrated(false)
                            ->live()
                            ->visible(fn (?Media $record) => $record && $record->isImage()),
                        Forms\Components\CheckboxList::make('responsive_widths')
                            ->label('Responsive Widths')
                            ->options([
                                480 => '480px (Mobile)',
                                800 => '800px (Tablet)',
                                1200 => '1200px (Desktop)',
                            ])
                            ->default([480, 800, 1200])
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Media $record) => $record && $record->responsive_images ? array_keys($record->responsive_images) : [480, 800, 1200])
                            ->visible(fn (callable $get, ?Media $record) => $record && $record->isImage() && $get('regenerate_responsive_images')),
                    ])->columnSpan(2),

                Section::make('System Info')
                    ->description('Read-only file specifications.')
                    ->schema([
                        Forms\Components\TextInput::make('mime_type')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('size')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => number_format($state / 1024, 2) . ' KB'),
                        Forms\Components\TextInput::make('disk')
                            ->disabled()
                            ->dehydrated(false),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('width')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('height')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        $isGrid = session('media_library_view', 'grid') === 'grid';

        return $table
            ->contentGrid($isGrid ? ['sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 5] : null)
            ->recordAction('edit')
            ->columns($isGrid ? [
                Tables\Columns\Layout\View::make('media-library::filament.components.media-card')
            ] : [
                Tables\Columns\ImageColumn::make('url')
                    ->label('Preview')
                    ->square()
                    ->state(function (Media $record) {
                        if ($record->isImage()) {
                            return $record->getUrl('thumb');
                        }
                        return null; // fallback placeholder handled in view or CSS
                    })
                    ->defaultIcon('heroicon-o-document'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('file_name')
                    ->searchable()
                    ->sortable()
                    ->color('gray')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('mime_type')
                    ->searchable()
                    ->sortable()
                    ->size('sm'),
                Tables\Columns\TextColumn::make('size')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state / 1024, 2) . ' KB')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('collection_name')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'image' => 'Image',
                        'video' => 'Video',
                        'audio' => 'Audio',
                        'document' => 'Document',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'image') {
                            $query->where('mime_type', 'like', 'image/%');
                        } elseif ($data['value'] === 'video') {
                            $query->where('mime_type', 'like', 'video/%');
                        } elseif ($data['value'] === 'audio') {
                            $query->where('mime_type', 'like', 'audio/%');
                        } elseif ($data['value'] === 'document') {
                            $query->where('mime_type', 'not like', 'image/%')
                                  ->where('mime_type', 'not like', 'video/%')
                                  ->where('mime_type', 'not like', 'audio/%');
                        }
                    }),
                Tables\Filters\SelectFilter::make('collection_name')
                    ->label('Collection')
                    ->options(fn () => Media::distinct()->pluck('collection_name', 'collection_name')->all()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->slideOver()
                    ->after(function (Media $record, array $data) {
                        $replacementFile = $data['replacement_file'] ?? null;
                        $regenerate = $data['regenerate_responsive_images'] ?? false;
                        $widths = $data['responsive_widths'] ?? [];
                        
                        if ($replacementFile) {
                            $record->replaceFile($replacementFile);
                            if ($record->isImage()) {
                                \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record, $widths);
                            }
                        } elseif ($regenerate && $record->isImage()) {
                            \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record, $widths);
                        }
                    }),
                Actions\Action::make('convert')
                    ->label('Convert / Resize')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
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
                    ->action(function (Media $record, array $data) {
                        $disk = Storage::disk($record->disk);
                        $originalPath = $record->getPath();
                        if (!$disk->exists($originalPath)) return;
                        
                        $originalData = $disk->get($originalPath);
                        
                        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                        $image = $manager->read($originalData);
                        
                        // Resize if width/height specified
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
                        
                        // Overwrite file on disk
                        $disk->put($originalPath, $encodedData);
                        
                        // Update model properties
                        $record->update([
                            'mime_type' => $mimeType,
                            'size' => strlen($encodedData),
                            'width' => $image->width(),
                            'height' => $image->height(),
                        ]);
                        
                        // Regrow conversions & responsive variants
                        \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($record);
                    })
                    ->visible(fn (Media $record) => $record->isImage()),
                Actions\ActionGroup::make([
                    Actions\Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Media $record) => $record->getUrl(), shouldOpenInNewTab: true),
                    Actions\Action::make('optimize')
                        ->label('Optimize & Regrow')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('warning')
                        ->action(function (Media $record) {
                            ProcessMediaConversions::dispatchSync($record);
                        })
                        ->visible(fn (Media $record) => $record->isImage()),
                    Actions\RestoreAction::make(),
                    Actions\DeleteAction::make(),
                    Actions\ForceDeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\BulkAction::make('optimize_all')
                        ->label('Optimize Selected')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('warning')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            foreach ($records as $record) {
                                if ($record->isImage()) {
                                    ProcessMediaConversions::dispatchSync($record);
                                }
                            }
                        }),
                    Actions\BulkAction::make('convert_selected')
                        ->label('Convert Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
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
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                            foreach ($records as $record) {
                                if (!$record->isImage()) continue;
                                
                                $disk = Storage::disk($record->disk);
                                $originalPath = $record->getPath();
                                if (!$disk->exists($originalPath)) continue;
                                
                                $originalData = $disk->get($originalPath);
                                try {
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
                                } catch (\Exception $e) {
                                    logger()->error("Bulk convert failed for media {$record->id}: " . $e->getMessage());
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
