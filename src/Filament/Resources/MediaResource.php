<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $slug = 'media-library';

    protected static ?string $modelLabel = 'Media';

    protected static ?string $pluralModelLabel = 'Media Library';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Media Metadata')
                    ->description('Details about this media asset.')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('File Preview')
                            ->content(function (?Media $record) {
                                if (!$record) return '-';
                                if ($record->isImage()) {
                                    return new \Illuminate\Support\HtmlString('<img src="' . $record->getUrl() . '" class="max-w-full h-auto rounded-lg border shadow-sm max-h-[250px] object-contain" />');
                                }
                                $icon = match($record->getType()) {
                                    'video' => '🎥',
                                    'audio' => '🎵',
                                    'document' => '📄',
                                    default => '📁',
                                };
                                return new \Illuminate\Support\HtmlString('<div class="flex items-center justify-center border rounded-lg h-32 text-5xl bg-gray-50 dark:bg-gray-900">' . $icon . '</div>');
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
                        Forms\Components\TextInput::make('alt_text')
                            ->maxLength(255)
                            ->placeholder('Alternative text for images'),
                        Forms\Components\KeyValue::make('custom_properties')
                            ->label('Custom Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ])->columnSpan(2),

                Forms\Components\Section::make('System Info')
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
                        Forms\Components\Grid::make(2)
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
                    ->placeholder('📄'),
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
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\Action::make('convert')
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
                            ->minVal(10)
                            ->maxVal(100)
                            ->default(80)
                            ->required(),
                        Forms\Components\Grid::make(2)
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Media $record) => $record->getUrl(), shouldOpenInNewTab: true),
                    Tables\Actions\Action::make('optimize')
                        ->label('Optimize & Regrow')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('warning')
                        ->action(function (Media $record) {
                            ProcessMediaConversions::dispatchSync($record);
                        })
                        ->visible(fn (Media $record) => $record->isImage()),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('optimize_all')
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
                    Tables\Actions\BulkAction::make('convert_selected')
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
                                ->minVal(10)
                                ->maxVal(100)
                                ->default(80)
                                ->required(),
                            Forms\Components\Grid::make(2)
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
