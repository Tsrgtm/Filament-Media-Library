<div class="flex flex-col h-full border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-shadow duration-200 group relative">
    
    <!-- Preview Area -->
    <div class="aspect-square bg-gray-50 dark:bg-gray-900 flex items-center justify-center relative overflow-hidden border-b border-gray-100 dark:border-gray-700/50">
        @if ($record->isImage())
            <img src="{{ $record->getUrl('thumb') }}" 
                 alt="{{ $record->alt_text ?? $record->name }}"
                 class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300 ease-out" 
                 loading="lazy"/>
        @else
            <!-- Non-image icons -->
            <div class="flex flex-col items-center justify-center p-4 text-center">
                @php
                    $svgPath = match($record->getType()) {
                        'video' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25Z" />',
                        'audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                        'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                        default => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />',
                    };
                    $color = match($record->getType()) {
                        'video' => 'text-purple-500',
                        'audio' => 'text-indigo-500',
                        'document' => 'text-blue-500',
                        default => 'text-gray-500',
                    };
                @endphp
                <svg class="w-12 h-12 mb-3 {{ $color }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    {!! $svgPath !!}
                </svg>
                <span class="text-xs font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 {{ $color }}">
                    {{ strtoupper(pathinfo($record->file_name, PATHINFO_EXTENSION)) ?: 'FILE' }}
                </span>
            </div>
        @endif

        <!-- Quick Alt Hover Indicator -->
        @if ($record->alt_text)
            <div class="absolute bottom-2 left-2 bg-black/70 text-white text-[10px] px-2 py-0.5 rounded backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                ALT: {{ Str::limit($record->alt_text, 20) }}
            </div>
        @endif
    </div>

    <!-- Metadata Details -->
    <div class="p-4 flex flex-col flex-grow justify-between gap-2">
        <div>
            <h4 class="font-medium text-gray-900 dark:text-gray-100 text-sm truncate" title="{{ $record->name }}">
                {{ $record->name }}
            </h4>
            <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5" title="{{ $record->file_name }}">
                {{ $record->file_name }}
            </p>
        </div>

        <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100 dark:border-gray-700/50">
            <span class="text-[11px] font-mono text-gray-400 dark:text-gray-500">
                {{ number_format($record->size / 1024, 1) }} KB
            </span>

            <span class="inline-flex items-center rounded-md bg-primary-50 dark:bg-primary-950/30 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-400 ring-1 ring-inset ring-primary-700/10 dark:ring-primary-400/20">
                {{ $record->collection_name }}
            </span>
        </div>
    </div>

    <!-- Hover Action Trigger overlay -->
    <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
        <!-- Clipboard Copy URL Button -->
        <x-filament::button size="xs">
            New user
        </x-filament::button>
        <button 
            type="button"
            x-data="{ copied: false }"
            x-on:click.stop="
                navigator.clipboard.writeText('{{ $record->getUrl() }}');
                copied = true;
                setTimeout(() => copied = false, 2000);
                new FilamentNotification().title('Copied URL to clipboard.').success().send();
            "
            class="p-1.5 rounded-lg bg-white/90 dark:bg-gray-800/90 text-gray-700 dark:text-gray-300 shadow-sm border border-gray-200/50 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            title="Copy URL"
        >
            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
            <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </button>
    </div>
</div>
