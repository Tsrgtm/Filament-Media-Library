<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default disk to use for storing uploaded media files. You can choose
    | local, public, s3, or any other configured storage disk.
    |
    */
    'disk' => env('MEDIA_LIBRARY_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Private Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk to use for private media files (e.g., invoices, documents)
    | that require access control and signed URLs.
    |
    */
    'private_disk' => env('MEDIA_LIBRARY_PRIVATE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Max File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file upload size in bytes. Defaults to 50MB.
    |
    */
    'max_file_size' => env('MEDIA_LIBRARY_MAX_FILE_SIZE', 52428800), // 50MB

    /*
    |--------------------------------------------------------------------------
    | Allowed Mime Types
    |--------------------------------------------------------------------------
    |
    | Only files matching these mime types will be allowed.
    |
    */
    'allowed_mimes' => [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/svg+xml',
        'image/webp',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        // Audio/Video
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how media queries and URL generations are cached.
    |
    */
    'cache' => [
        'enabled' => env('MEDIA_LIBRARY_CACHE_ENABLED', true),
        'ttl' => env('MEDIA_LIBRARY_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('MEDIA_LIBRARY_CACHE_PREFIX', 'media_library_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure background processing. If enabled, conversions and optimizations
    | will run via Laravel queues. If disabled, they run synchronously.
    |
    */
    'queue' => [
        'enabled' => env('MEDIA_LIBRARY_QUEUE_ENABLED', false),
        'connection' => env('MEDIA_LIBRARY_QUEUE_CONNECTION', null), // uses default
        'queue' => env('MEDIA_LIBRARY_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Optimization & Conversions
    |--------------------------------------------------------------------------
    |
    | Settings for image processing including WebP conversion, responsive image
    | generation, and default optimization parameters.
    |
    */
    'webp_enabled' => env('MEDIA_LIBRARY_WEBP_ENABLED', true),
    'image_quality' => env('MEDIA_LIBRARY_QUALITY', 80),

    'conversions' => [
        'thumb' => [
            'width' => 150,
            'height' => 150,
            'fit' => true,
        ],
        'medium' => [
            'width' => 800,
            'height' => null,
            'fit' => false,
        ],
        'large' => [
            'width' => 1200,
            'height' => null,
            'fit' => false,
        ],
    ],

    'responsive_widths' => [480, 800, 1200],

    /*
    |--------------------------------------------------------------------------
    | Duplicate Detection
    |--------------------------------------------------------------------------
    |
    | Detect duplicate file uploads using SHA-256 hashes.
    | 'strategy' options:
    |   - 'link': Reference the same physical file on disk (saves storage space).
    |   - 'separate': Save physical files separately, but register the hash.
    |
    */
    'duplicate_detection' => [
        'enabled' => env('MEDIA_LIBRARY_DUPLICATE_DETECTION', true),
        'strategy' => env('MEDIA_LIBRARY_DUPLICATE_STRATEGY', 'link'),
    ],
];
