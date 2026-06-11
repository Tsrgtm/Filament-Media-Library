<?php

namespace Tsrgtm\FilamentMediaLibrary\Services;

use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use ZipArchive;

class DocxEditor
{
    /**
     * Search and replace text in a DOCX file.
     */
    public function findAndReplace(Media $media, string $find, string $replace): void
    {
        $disk = Storage::disk($media->disk);
        if (!$disk->exists($media->path)) {
            throw new \Exception("DOCX file does not exist.");
        }

        // Create local temp copy of DOCX
        $tempDocx = tempnam(sys_get_temp_dir(), 'fml_docx_');
        file_put_contents($tempDocx, $disk->get($media->path));

        $zip = new ZipArchive();
        if ($zip->open($tempDocx) !== true) {
            if (file_exists($tempDocx)) {
                @unlink($tempDocx);
            }
            throw new \Exception("Could not open DOCX archive.");
        }

        // Locate word/document.xml
        $documentXmlPath = 'word/document.xml';
        $xmlContentIndex = $zip->locateName($documentXmlPath);
        if ($xmlContentIndex === false) {
            $zip->close();
            if (file_exists($tempDocx)) {
                @unlink($tempDocx);
            }
            throw new \Exception("Invalid DOCX format: word/document.xml not found.");
        }

        $xmlContent = $zip->getFromIndex($xmlContentIndex);
        
        // Escape replacement text for XML safety
        $escapedReplace = htmlspecialchars($replace, ENT_XML1, 'UTF-8');
        
        // Perform replacement
        $modifiedXmlContent = str_replace($find, $escapedReplace, $xmlContent);

        // Save it back into the zip
        $zip->deleteName($documentXmlPath);
        $zip->addFromString($documentXmlPath, $modifiedXmlContent);
        $zip->close();

        // Write the modified file back to storage
        $disk->put($media->path, fopen($tempDocx, 'r'));

        // Update size & hash
        $media->update([
            'size' => filesize($tempDocx),
            'hash' => hash_file('sha256', $tempDocx),
        ]);

        if (file_exists($tempDocx)) {
            @unlink($tempDocx);
        }
    }
}
