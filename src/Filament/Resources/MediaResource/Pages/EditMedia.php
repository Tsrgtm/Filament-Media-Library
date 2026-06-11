<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();
        $regenerate = $data['regenerate_responsive_images'] ?? false;
        $widths = $data['responsive_widths'] ?? [];
        $replacementFile = $data['replacement_file'] ?? null;

        if ($replacementFile) {
            $this->record->replaceFile($replacementFile);
            if ($this->record->isImage()) {
                \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($this->record, $widths);
            }
        } elseif ($regenerate && $this->record->isImage()) {
            \Tsrgtm\FilamentMediaLibrary\Jobs\ProcessMediaConversions::dispatch($this->record, $widths);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
