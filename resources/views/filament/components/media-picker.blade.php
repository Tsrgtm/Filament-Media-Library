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
                            <div class="flex flex-col items-center justify-center p-2 text-center w-full">
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
                                <span class="text-[10px] font-medium truncate w-full text-gray-500 dark:text-gray-400">{{ $media->name }}</span>
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
