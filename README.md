# Filament Media Library

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tsrgtm/filament-media-library.svg?style=flat-square)](https://packagist.org/packages/tsrgtm/filament-media-library)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/badge/tests-passing-brightgreen.svg?style=flat-square)](#testing)

A premium, production-ready Filament-native media library plugin for Laravel. It provides polymorphic media attachments, background optimizations, responsive HTML layout generators, secure URL streaming, deduplication, and a WordPress-style media picker modal.

📖 **Interactive Documentation Portal:** [https://tsrgtm.github.io/Filament-Media-Library/](https://tsrgtm.github.io/Filament-Media-Library/)

---

## Features

- **Polymorphic Attachments:** Attach single or multiple files to any Eloquent model using a simple `HasMedia` trait.
- **Intervention Image v3 Optimizations:** Automatically compresses, scales, and creates auto-negotiated WebP fallbacks for uploaded images.
- **WordPress-style Media Details:** Visual slide-over details panel to preview files and edit alt text and titles inline.
- **WordPress-style Media Picker:** Select, search, filter, and reuse existing uploaded files in form views instead of creating duplicate uploads.
- **On-Demand Format Conversion:** Dynamically convert file formats (JPEG, PNG, WebP), compression quality (10-100), and max dimensions directly from the Filament resource table.
- **SEO-Stable Paths:** Serves assets via static, predictable URLs (`/media-serve/{media_id}/{filename}`) preventing broken images.
- **Secure File Support:** Automatically generates signed URLs valid for 1 hour for secure private disks.
- **Smart Deduplication:** Performs SHA-256 integrity checks on uploads to link identical files to the same physical disk location, saving storage space.
- **Background Processing:** Process image resizing and optimization asynchronously via Laravel Queues.

---

## Installation

1. Install the package namespace using Composer:
   ```bash
   composer require tsrgtm/filament-media-library
   ```

2. Publish package configuration and database migration schemas:
   ```bash
   php artisan vendor:publish --provider="Tsrgtm\FilamentMediaLibrary\MediaLibraryServiceProvider"
   ```

3. Run migrations:
   ```bash
   php artisan migrate
   ```

---

## Setup Models

Add the `HasMedia` trait to your Eloquent models and define your attachments collections inside the `mediaCollections` method:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tsrgtm\FilamentMediaLibrary\Traits\HasMedia;

class Post extends Model
{
    use HasMedia;

    public function mediaCollections(): array
    {
        return [
            'featured_image' => [
                'single' => true, // Replaces previous attachment automatically
                'conversions' => ['thumb', 'medium'],
                'fallback' => '/images/default-thumbnail.png',
            ],
            'gallery' => [
                'multiple' => true,
            ],
        ];
    }
}
```

### Fluent Upload API
Upload media assets directly from your PHP code:
```php
// From a request upload
$post->addMedia($request->file('cover'))
    ->withAltText('Featured blog banner')
    ->toCollection('featured_image');

// From local path
$post->addMedia(storage_path('exports/invoice.pdf'))
    ->preservingOriginal()
    ->toCollection('attachments');
```

---

## Filament Form Inputs

### 1. File Upload Component (`MediaLibraryUpload`)
To let users upload new media directly from a Filament resource form (automatically saving to the polymorphic media library relationship):

```php
use Tsrgtm\FilamentMediaLibrary\Filament\Components\MediaLibraryUpload;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            MediaLibraryUpload::make('featured_image')
                ->label('Cover Photo')
                ->collection('featured_image')
                ->image()
                ->maxSize(2048), // KB
        ]);
}
```

### 2. Media Picker Component (`MediaLibraryPicker`)
To let users search, browse, and select **existing** images from the media library (WordPress style) with an active metadata sidebar for editing alt text and titles inside the modal:

```php
use Tsrgtm\FilamentMediaLibrary\Filament\Components\MediaLibraryPicker;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            MediaLibraryPicker::make('featured_image')
                ->label('Featured Image')
                ->collection('featured_image'),

            MediaLibraryPicker::make('gallery')
                ->label('Gallery Images')
                ->collection('gallery')
                ->multiple(), // Supports multi-selection
        ]);
}
```

---

## Blade Templating & Responsive Images

To output fully responsive images with automatic `srcset` tags for various device widths (480px, 800px, 1200px) and a fallback original link:

```blade
{!! $post->getFirstMedia('featured_image')->img('medium', ['class' => 'rounded-xl border shadow-sm']) !!}
```

This compiles into clean, SEO-compliant HTML code:
```html
<img src="/media-serve/14/banner.jpg" 
     alt="Featured blog banner" 
     srcset="/media-serve/14/banner.jpg?w=480 480w, /media-serve/14/banner.jpg?w=800 800w" 
     sizes="(max-width: 1200px) 100vw, 1200px" 
     class="rounded-xl border shadow-sm" />
```

---

## Testing

Run the test suite using PHPUnit:

```bash
vendor/bin/phpunit
```

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
