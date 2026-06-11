<?php

namespace Tsrgtm\FilamentMediaLibrary\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tsrgtm\FilamentMediaLibrary\Events\MediaUploaded;
use Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class FileAddor
{
    protected Model $model;
    
    /**
     * @var string|UploadedFile
     */
    protected $file;

    protected string $name = '';
    protected string $fileName = '';
    protected array $customProperties = [];
    protected string $altText = '';
    protected bool $preservingOriginal = false;
    protected ?array $responsiveWidths = null;


    /**
     * Create a new FileAddor instance.
     */
    public function __construct(Model $model, $file)
    {
        $this->model = $model;
        $this->file = $file;

        // Set default name and filename
        if ($file instanceof UploadedFile) {
            $this->name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $this->fileName = $file->getClientOriginalName();
        } elseif (is_string($file) && file_exists($file)) {
            $this->name = pathinfo($file, PATHINFO_FILENAME);
            $this->fileName = basename($file);
        }
    }

    /**
     * Set the name attribute.
     */
    public function usingName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the file name attribute.
     */
    public function usingFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * Preserve the original file on disk rather than deleting/moving it.
     */
    public function preservingOriginal(): self
    {
        $this->preservingOriginal = true;
        return $this;
    }

    /**
     * Set custom properties.
     */
    public function withCustomProperties(array $properties): self
    {
        $this->customProperties = $properties;
        return $this;
    }

    /**
     * Set alternative text.
     */
    public function withAltText(string $altText): self
    {
        $this->altText = $altText;
        return $this;
    }

    /**
     * Set responsive widths to generate.
     */
    public function withResponsiveWidths(?array $widths): self
    {
        $this->responsiveWidths = $widths;
        return $this;
    }


    /**
     * Save the file to the specified collection and process it.
     */
    public function toCollection(string $collectionName = 'default'): Media
    {
        // 1. Get temp path
        $tempPath = $this->file instanceof UploadedFile ? $this->file->getRealPath() : $this->file;

        if (!file_exists($tempPath)) {
            throw new \InvalidArgumentException("Source file does not exist at path: {$tempPath}");
        }

        // 2. Validate file size & mime type
        $fileSize = filesize($tempPath);
        $mimeType = $this->file instanceof UploadedFile ? $this->file->getMimeType() : mime_content_type($tempPath);

        if ($fileSize > config('media-library.max_file_size', 52428800)) {
            throw new \Exception("File size exceeds limit: " . config('media-library.max_file_size'));
        }

        $allowedMimes = config('media-library.allowed_mimes', []);
        if (!empty($allowedMimes) && !in_array($mimeType, $allowedMimes)) {
            throw new \Exception("Mime type not allowed: {$mimeType}");
        }

        // 3. Setup Disk & Hash
        $diskName = config('media-library.disk', 'public');
        $hash = hash_file('sha256', $tempPath);
        
        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/')) {
            $dimensions = @getimagesize($tempPath);
            if ($dimensions) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        // Check if single collection, if so, delete/clear previous media items in this collection
        $collections = $this->model->mediaCollections();
        $isSingle = $collections[$collectionName]['single'] ?? false;
        if ($isSingle) {
            $existingMedia = $this->model->mediaItems()->where('collection_name', $collectionName)->get();
            foreach ($existingMedia as $oldMedia) {
                $oldMedia->delete(); // Soft deletes it
            }
        }

        // 4. Duplicate Detection (Strategy: Link or Separate)
        $duplicate = null;
        if (config('media-library.duplicate_detection.enabled', true)) {
            $duplicate = Media::where('hash', $hash)->where('disk', $diskName)->first();
        }

        // Sanitize file name
        $sanitizedFileName = Str::slug(pathinfo($this->fileName, PATHINFO_FILENAME)) . '.' . pathinfo($this->fileName, PATHINFO_EXTENSION);
        
        // Define directory paths
        $sanitizedModel = str_replace('\\', '-', strtolower(get_class($this->model)));
        $relativeDir = "media/{$sanitizedModel}/{$this->model->id}/{$collectionName}";
        $destinationPath = "{$relativeDir}/{$sanitizedFileName}";

        if ($duplicate && config('media-library.duplicate_detection.strategy', 'link') === 'link') {
            // Re-use physical file
            $destinationPath = $duplicate->getPath();
            $sanitizedFileName = $duplicate->file_name;
            $diskName = $duplicate->disk;
        } else {
            // Write new file
            $fileStream = fopen($tempPath, 'r');
            Storage::disk($diskName)->put($destinationPath, $fileStream);
            fclose($fileStream);
        }

        // Create Media database entry
        $media = Media::create([
            'model_type' => get_class($this->model),
            'model_id' => $this->model->id,
            'collection_name' => $collectionName,
            'name' => $this->name,
            'file_name' => $sanitizedFileName,
            'path' => $destinationPath,
            'disk' => $diskName,
            'mime_type' => $mimeType,
            'size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'alt_text' => $this->altText,
            'custom_properties' => $this->customProperties,
            'hash' => $hash,
            'order_column' => $this->model->mediaItems()->where('collection_name', $collectionName)->max('order_column') + 1,
        ]);

        // Clean up source file if not preserving
        if (!$this->preservingOriginal && !$this->file instanceof UploadedFile) {
            @unlink($tempPath);
        }

        // Fire uploaded event
        event(new MediaUploaded($media));

        // 5. Process Conversions & Responsive images
        if ($media->isImage()) {
            if (config('media-library.queue.enabled', false)) {
                ProcessMediaConversions::dispatch($media, $this->responsiveWidths);
            } else {
                ProcessMediaConversions::dispatchSync($media, $this->responsiveWidths);
            }
        }


        return $media;
    }

    /**
     * Alias for toCollection.
     */
    public function to(string $collectionName = 'default'): Media
    {
        return $this->toCollection($collectionName);
    }
}
