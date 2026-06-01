<?php

namespace Tsrgtm\FilamentMediaLibrary\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tsrgtm\FilamentMediaLibrary\Events\MediaConverted;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Services\ImageProcessor;

class ProcessMediaConversions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Media $media;

    /**
     * Create a new job instance.
     */
    public function __construct(Media $media)
    {
        $this->media = $media;

        // Configure queue connection and queue name based on config
        $this->onConnection(config('media-library.queue.connection'));
        $this->onQueue(config('media-library.queue.queue'));
    }

    /**
     * Execute the job.
     */
    public function handle(ImageProcessor $processor): void
    {
        // Check if media hasn't been hard-deleted
        if (!$this->media->exists) {
            return;
        }

        // Process image optimizations, formats, conversions
        $processor->process($this->media);

        // Fire converted event
        event(new MediaConverted($this->media));
    }
}
