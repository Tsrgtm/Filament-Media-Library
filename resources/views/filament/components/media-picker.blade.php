<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{ state: $wire.$entangle('{{ $getStatePath() }}') }" class="space-y-4">
        <!-- Preview of selected items -->
        @php
            $state = $getState();
            $mediaIds = array_filter(is_array($state) ? $state : [$state]);
            $selectedMedia = \Tsrgtm\FilamentMediaLibrary\Models\Media::findMany($mediaIds);
        @endphp

        @if ($selectedMedia->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 border dark:border-gray-800 rounded-lg p-4 bg-gray-50/50 dark:bg-gray-950/20">
                @foreach ($selectedMedia as $media)
                    <div class="relative group border dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900 aspect-square flex items-center justify-center shadow-sm">
                        @if ($media->isImage())
                            <img src="{{ $media->getUrl('thumb') }}" class="object-cover w-full h-full" alt="{{ $media->name }}"/>
                        @else
                            <div class="flex flex-col items-center justify-center p-2 text-center">
                                <span class="text-3xl mb-1">
                                    {{ match($media->getType()) {
                                        'video' => '🎥',
                                        'audio' => '🎵',
                                        'document' => '📄',
                                        default => '📁',
                                    } }}
                                </span>
                                <span class="text-[10px] font-medium truncate w-full text-gray-500">{{ $media->name }}</span>
                            </div>
                        @endif
                        <!-- Remove button -->
                        <button
                            type="button"
                            x-on:click="
                                @if ($isMultiple())
                                    state = state.filter(id => id != {{ $media->id }})
                                @else
                                    state = null
                                @endif
                            "
                            class="absolute top-1 right-1 p-1 bg-red-500 hover:bg-red-600 text-white rounded-full shadow opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <div>
            {{ $getAction('openPicker') }}
        </div>
    </div>
</x-dynamic-component>
