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
                    $icon = match($record->getType()) {
                        'video' => '🎥',
                        'audio' => '🎵',
                        'document' => '📄',
                        default => '📁',
                    };
                    $color = match($record->getType()) {
                        'video' => 'text-purple-500',
                        'audio' => 'text-indigo-500',
                        'document' => 'text-blue-500',
                        default => 'text-gray-500',
                    };
                @endphp
                <span class="text-5xl mb-2">{{ $icon }}</span>
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
            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5" title="{{ $record->file_name }}">
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
        <button 
            type="button"
            x-data="{ copied: false }"
            x-on:click.stop="
                navigator.clipboard.writeText('{{ $record->getUrl() }}');
                copied = true;
                setTimeout(() => copied = false, 2000);
                $tooltip('Copied URL!', { theme: 'dark' });
            "
            class="p-1.5 rounded-lg bg-white/90 dark:bg-gray-800/90 text-gray-700 dark:text-gray-300 shadow-sm border border-gray-200/50 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            title="Copy URL"
        >
            <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4m-.084-.4h.084a2.25 2.25 0 0 1 2.24 2.167M10.5 2.25c-.055.194-.086.4-.086.4H10.5a2.25 2.25 0 0 0-2.24 2.167M9 19.5H6.25a2.25 2.25 0 0 1-2.25-2.25v-10.5A2.25 2.25 0 0 1 6.25 4.5h2.25M19.5 19.5h-2.25m3 0a3 3 0 0 0-3-3M19.5 19.5a3 3 0 0 1-3-3m3 3V16.5m-3-9.75h1.5m-1.5 3h1.5m-1.5 3h1.5M9 16.5h.008v.008H9V16.5Zm0-3h.008v.008H9v-.008Zm0-3h.008v.008H9V10.5Z" />
            </svg>
            <svg x-show="copied" x-cloak class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </button>
    </div>
</div>
