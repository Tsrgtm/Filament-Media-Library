@php
    $options = $getOptions();
    $state = $getState();
@endphp

<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[450px] overflow-y-auto p-2 border rounded-lg bg-gray-50/50 dark:bg-gray-950/20 shadow-inner">
    @foreach ($options as $id => $name)
        @php
            $media = \Tsrgtm\FilamentMediaLibrary\Models\Media::find($id);
            if (!$media) continue;
            $isSelected = (string) $state === (string) $id;
        @endphp
        <label class="relative aspect-square cursor-pointer rounded-lg overflow-hidden border-2 transition-all {{ $isSelected ? 'border-primary-600 ring-2 ring-primary-500/50' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700' }}">
            <input 
                type="radio" 
                name="{{ $getId() }}" 
                value="{{ $id }}" 
                wire:model.live="{{ $getStatePath() }}" 
                class="sr-only" 
            />
            
            @if ($media->isImage())
                <img src="{{ $media->getUrl('thumb') }}" class="object-cover w-full h-full" alt="{{ $name }}" />
            @else
                <div class="flex flex-col items-center justify-center h-full bg-white dark:bg-gray-900 p-2 text-center">
                    <span class="text-3xl mb-1">
                        {{ match($media->getType()) {
                            'video' => '🎥',
                            'audio' => '🎵',
                            'document' => '📄',
                            default => '📁',
                        } }}
                    </span>
                    <span class="text-[10px] font-medium truncate w-full text-gray-500">{{ $name }}</span>
                </div>
            @endif
            
            <!-- Checkmark badge if selected -->
            @if ($isSelected)
                <div class="absolute top-1.5 right-1.5 bg-primary-600 text-white rounded-full p-0.5 shadow-sm z-10">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
            @endif
        </label>
    @endforeach
</div>
