<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Pages;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Tsrgtm\FilamentMediaLibrary\Models\MediaSetting;

class MediaSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Media Library Settings';

    protected static ?string $navigationLabel = 'Media Settings';

    protected static ?string $slug = 'media-library-settings';

    protected static string $view = 'media-library::filament.pages.media-settings';

    public ?array $data = [];

    public function mount(): void
    {
        // Populate the form with current values or config fallbacks
        $this->form->fill([
            'disk' => MediaSetting::get('disk', config('media-library.disk')),
            'private_disk' => MediaSetting::get('private_disk', config('media-library.private_disk')),
            'max_file_size' => MediaSetting::get('max_file_size', config('media-library.max_file_size')) / (1024 * 1024), // Bytes to MB
            'webp_enabled' => MediaSetting::get('webp_enabled', config('media-library.webp_enabled')),
            'image_quality' => MediaSetting::get('image_quality', config('media-library.image_quality')),
            'cache_enabled' => MediaSetting::get('cache.enabled', config('media-library.cache.enabled')),
            'queue_enabled' => MediaSetting::get('queue.enabled', config('media-library.queue.enabled')),
            'duplicate_detection_enabled' => MediaSetting::get('duplicate_detection.enabled', config('media-library.duplicate_detection.enabled')),
            'duplicate_detection_strategy' => MediaSetting::get('duplicate_detection.strategy', config('media-library.duplicate_detection.strategy')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                                    ->min(10)
                                    ->max(100)
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
            ->statePath('data');
    }

    public function save(): void
    {
        $input = $this->form->getState();

        // Persist to DB setting fields
        MediaSetting::set('disk', $input['disk']);
        MediaSetting::set('private_disk', $input['private_disk']);
        MediaSetting::set('max_file_size', $input['max_file_size'] * 1024 * 1024); // MB to Bytes
        MediaSetting::set('webp_enabled', $input['webp_enabled']);
        MediaSetting::set('image_quality', $input['image_quality']);
        MediaSetting::set('cache.enabled', $input['cache_enabled']);
        MediaSetting::set('queue.enabled', $input['queue_enabled']);
        MediaSetting::set('duplicate_detection.enabled', $input['duplicate_detection_enabled']);
        MediaSetting::set('duplicate_detection.strategy', $input['duplicate_detection_strategy'] ?? 'link');

        Notification::make()
            ->title('Media settings saved successfully.')
            ->success()
            ->send();
    }
}
