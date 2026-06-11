<?php

namespace Tsrgtm\FilamentMediaLibrary\Services;

use Tsrgtm\FilamentMediaLibrary\Models\Media;

class PdfEditor
{
    /**
     * Update PDF metadata fields stored in custom properties.
     */
    public function updateMetadata(Media $media, array $metadata): void
    {
        $customProperties = $media->custom_properties ?? [];
        
        $customProperties['pdf_title'] = $metadata['title'] ?? null;
        $customProperties['pdf_author'] = $metadata['author'] ?? null;
        $customProperties['pdf_subject'] = $metadata['subject'] ?? null;
        $customProperties['pdf_keywords'] = $metadata['keywords'] ?? null;
        $customProperties['pdf_creator'] = $metadata['creator'] ?? null;

        $media->update([
            'custom_properties' => $customProperties,
        ]);
    }
}
