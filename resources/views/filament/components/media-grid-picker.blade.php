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
                    @php
                        $svgPath = match($media->getType()) {
                            'video' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25Z" />',
                            'audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                            'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                            default => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />',
                        };
                        $svgColor = match($media->getType()) {
                            'video' => 'text-purple-500',
                            'audio' => 'text-indigo-500',
                            'document' => 'text-blue-500',
                            default => 'text-gray-400 dark:text-gray-500',
                        };
                    @endphp
                    <svg class="w-8 h-8 mb-1.5 {{ $svgColor }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        {!! $svgPath !!}
                    </svg>
                    <span class="text-[10px] font-medium truncate w-full text-gray-500 dark:text-gray-400">{{ $name }}</span>
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
