<?php

namespace Tsrgtm\FilamentMediaLibrary\Services;

use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;

class VideoProcessor
{
    /**
     * Get the FFMpeg instance.
     */
    protected function getFFMpeg(): ?FFMpeg
    {
        try {
            return FFMpeg::create([
                'ffmpeg.binaries'  => config('media-library.ffmpeg.ffmpeg_path', 'ffmpeg'),
                'ffprobe.binaries' => config('media-library.ffmpeg.ffprobe_path', 'ffprobe'),
                'timeout'          => 3600,
            ]);
        } catch (\Throwable $e) {
            logger()->warning("FFMpeg binaries could not be loaded: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process video optimization and extract a thumbnail poster.
     */
    public function process(Media $media): void
    {
        $ffmpeg = $this->getFFMpeg();
        if (!$ffmpeg) {
            return;
        }

        $disk = Storage::disk($media->disk);
        if (!$disk->exists($media->path)) {
            return;
        }

        // Create local temp files for processing
        $tempVideo = tempnam(sys_get_temp_dir(), 'fml_vid_');
        file_put_contents($tempVideo, $disk->get($media->path));

        try {
            $video = $ffmpeg->open($tempVideo);

            // 1. Generate Thumbnail / Poster Frame
            // Check if there is a custom thumbnail time property
            $customProps = $media->custom_properties ?? [];
            $thumbSecond = (float) ($customProps['thumbnail_second'] ?? 2.0);

            $tempThumb = tempnam(sys_get_temp_dir(), 'fml_thumb_') . '.jpg';
            $video->frame(TimeCode::fromSeconds($thumbSecond))->save($tempThumb);

            if (file_exists($tempThumb) && filesize($tempThumb) > 0) {
                $thumbPath = $media->getDirectory() . "/conversions/thumb-" . pathinfo($media->file_name, PATHINFO_FILENAME) . ".jpg";
                $disk->put($thumbPath, fopen($tempThumb, 'r'));
                @unlink($tempThumb);
            }

            // 2. Video Optimization / Transcode if required
            // We can transcode to a web-friendly H.264 MP4 if it's not already optimized or if requested
            // For now, we will perform optimization to ensure web compatibility (H264 + AAC)
            $isMp4 = str_ends_with(strtolower($media->file_name), '.mp4');
            $shouldOptimize = $customProps['should_optimize'] ?? true;

            if ($shouldOptimize) {
                $tempOptVideo = tempnam(sys_get_temp_dir(), 'fml_opt_') . '.mp4';
                $format = new X264();
                $format->setAudioCodec('aac');
                // Target bitrates
                $format->setKiloBitrate(config('media-library.video_bitrate', 1500));
                $format->setAudioKiloBitrate(config('media-library.audio_bitrate', 128));

                $video->save($format, $tempOptVideo);

                if (file_exists($tempOptVideo) && filesize($tempOptVideo) > 0) {
                    $newSize = filesize($tempOptVideo);
                    $disk->put($media->path, fopen($tempOptVideo, 'r'));
                    $media->update([
                        'size' => $newSize,
                    ]);
                    @unlink($tempOptVideo);
                }
            }
        } catch (\Throwable $e) {
            logger()->error("Failed to process/optimize video {$media->id}: " . $e->getMessage());
        } finally {
            if (file_exists($tempVideo)) {
                @unlink($tempVideo);
            }
        }
    }

    /**
     * Trim a video clip to start and end times.
     */
    public function trim(Media $media, float $start, float $end): void
    {
        $ffmpeg = $this->getFFMpeg();
        if (!$ffmpeg) {
            throw new \Exception("FFMpeg binaries could not be loaded. Please ensure FFMpeg is installed on the server.");
        }

        if ($start < 0 || $end <= $start) {
            throw new \InvalidArgumentException("Invalid start or end timestamps for video trimming.");
        }

        $disk = Storage::disk($media->disk);
        if (!$disk->exists($media->path)) {
            throw new \Exception("Original video file does not exist.");
        }

        $tempVideo = tempnam(sys_get_temp_dir(), 'fml_trim_in_');
        file_put_contents($tempVideo, $disk->get($media->path));

        $tempTrimmed = tempnam(sys_get_temp_dir(), 'fml_trim_out_') . '.mp4';

        try {
            $video = $ffmpeg->open($tempVideo);
            $duration = $end - $start;

            $format = new X264();
            $format->setAudioCodec('aac');
            
            // Apply clipping filter
            $video->filters()->clip(TimeCode::fromSeconds($start), \FFMpeg\Coordinate\Duration::fromSeconds($duration));
            $video->save($format, $tempTrimmed);

            if (!file_exists($tempTrimmed) || filesize($tempTrimmed) === 0) {
                throw new \Exception("FFMpeg failed to trim the video.");
            }

            // Replace original file on disk
            $disk->put($media->path, fopen($tempTrimmed, 'r'));

            // Update Media details
            $media->update([
                'size' => filesize($tempTrimmed),
                'hash' => hash_file('sha256', $tempTrimmed),
            ]);

            // Regenerate thumbnail conversions
            $this->process($media);

        } catch (\Throwable $e) {
            throw new \Exception("Failed to trim video: " . $e->getMessage());
        } finally {
            if (file_exists($tempVideo)) {
                @unlink($tempVideo);
            }
            if (file_exists($tempTrimmed)) {
                @unlink($tempTrimmed);
            }
        }
    }
}
