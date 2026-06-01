<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" size="md">
                Save settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
