<?php

namespace Tsrgtm\FilamentMediaLibrary\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tsrgtm\FilamentMediaLibrary\Models\Media;

class MediaDeleted
{
    use Dispatchable, SerializesModels;

    public Media $media;

    /**
     * Create a new event instance.
     */
    public function __construct(Media $media)
    {
        $this->media = $media;
    }
}
