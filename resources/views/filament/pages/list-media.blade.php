<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous"></script>

    @php
        $mediaItemsJson = $this->records->map(fn($item) => [
            'id'        => $item->id,
            'name'      => $item->name,
            'file_name' => $item->file_name,
            'url'       => $item->getUrl(),
            'type'      => $item->getType(),
            'mime_type' => $item->mime_type,
            'size'      => number_format($item->size / 1024, 1) . ' KB',
            'width'     => $item->width,
            'height'    => $item->height,
            'alt_text'  => $item->alt_text,
        ])->values()->toJson();
    @endphp
    <div 
        x-data="{
            // Welcome Guide state
            showGuide: !localStorage.getItem('media-library-guide-dismissed'),
            dismissGuide() {
                localStorage.setItem('media-library-guide-dismissed', 'true');
                this.showGuide = false;
            },

            // Lightbox state – items embedded server-side for stability
            lightbox: {
                show: false,
                currentIndex: 0,
                items: {{ \Illuminate\Support\Js::from(json_decode($mediaItemsJson, true)) }},
                
                open(id) {
                    let index = this.items.findIndex(i => i.id === id);
                    if (index !== -1) {
                        this.currentIndex = index;
                        this.show = true;
                    }
                },
                next() {
                    if (this.items.length === 0) return;
                    this.currentIndex = (this.currentIndex + 1) % this.items.length;
                },
                prev() {
                    if (this.items.length === 0) return;
                    this.currentIndex = (this.currentIndex - 1 + this.items.length) % this.items.length;
                },
                get current() {
                    return this.items[this.currentIndex] || {};
                },
                get total() {
                    return this.items.length;
                }
            },
            
            // Visual Editor state
            showEditor: false,
            editorMode: 'view', // 'view', 'crop', 'trim', 'svg', 'docx', 'pdf', 'text'
            activeId: null,
            activeItem: null,
            
            // Cropper state
            cropper: null,
            cropData: { x: 0, y: 0, width: 0, height: 0 },
            
            // Video trim state
            trimStart: 0,
            trimEnd: 0,
            
            // SVG Editor state
            svgContent: '',
            
            // DOCX Editor state
            docxFind: '',
            docxReplace: '',
            
            // PDF Editor state
            pdfTitle: '',
            pdfAuthor: '',
            pdfSubject: '',
            pdfKeywords: '',
            pdfCreator: '',
            
            // Text Editor state
            textContent: '',

            selectItem(item) {
                this.activeId = item.id;
                this.activeItem = item;
                this.showEditor = true;
                this.editorMode = 'view';
                
                // Set default metadata
                this.pdfTitle = item.name || '';
                
                if (item.type === 'image') {
                    if (this.cropper) {
                        this.cropper.destroy();
                        this.cropper = null;
                    }
                }
                
                if (item.file_name.toLowerCase().endsWith('.svg') || item.mime_type === 'image/svg+xml') {
                    // Fetch svg content
                    fetch(item.url)
                        .then(r => r.text())
                        .then(t => {
                            this.svgContent = t;
                        });
                }
                
                if (item.mime_type === 'text/plain' || item.file_name.toLowerCase().endsWith('.txt') || item.file_name.toLowerCase().endsWith('.json') || item.file_name.toLowerCase().endsWith('.md')) {
                    fetch(item.url)
                        .then(r => r.text())
                        .then(t => {
                            this.textContent = t;
                        });
                }
            },
            
            initCropper() {
                this.editorMode = 'crop';
                this.$nextTick(() => {
                    const img = document.getElementById('fml-cropper-image');
                    if (img) {
                        if (this.cropper) this.cropper.destroy();
                        this.cropper = new Cropper(img, {
                            viewMode: 1,
                            movable: true,
                            zoomable: true,
                            rotatable: true,
                            scalable: true,
                            crop: (event) => {
                                this.cropData = {
                                    x: Math.round(event.detail.x),
                                    y: Math.round(event.detail.y),
                                    width: Math.round(event.detail.width),
                                    height: Math.round(event.detail.height)
                                };
                            }
                        });
                    }
                });
            },
            
            saveCrop() {
                if (this.cropData) {
                    $wire.saveVisualCrop(this.activeId, this.cropData.x, this.cropData.y, this.cropData.width, this.cropData.height)
                        .then(() => {
                            this.editorMode = 'view';
                            if (this.cropper) {
                                this.cropper.destroy();
                                this.cropper = null;
                            }
                        });
                }
            },
            
            saveTrim() {
                $wire.saveVisualTrim(this.activeId, parseFloat(this.trimStart), parseFloat(this.trimEnd))
                    .then(() => {
                        this.editorMode = 'view';
                    });
            },
            
            saveSvg() {
                $wire.saveSvgContent(this.activeId, this.svgContent)
                    .then(() => {
                        this.editorMode = 'view';
                    });
            },
            
            saveDocx() {
                $wire.saveVisualDocxReplace(this.activeId, this.docxFind, this.docxReplace)
                    .then(() => {
                        this.editorMode = 'view';
                        this.docxFind = '';
                        this.docxReplace = '';
                    });
            },
            
            savePdf() {
                $wire.saveVisualPdfMeta(this.activeId, {
                    title: this.pdfTitle,
                    author: this.pdfAuthor,
                    subject: this.pdfSubject,
                    keywords: this.pdfKeywords,
                    creator: this.pdfCreator
                }).then(() => {
                    this.editorMode = 'view';
                });
            },
            
            saveText() {
                $wire.saveVisualTextContent(this.activeId, this.textContent)
                    .then(() => {
                        this.editorMode = 'view';
                    });
            },
            
            saveMeta() {
                $wire.saveVisualMetadata(this.activeId, this.activeItem.name, this.activeItem.alt_text);
            },
            
            // Context menu state
            contextMenu: {
                show: false,
                x: 0,
                y: 0,
                itemId: null,
                isImage: false,
                isVideo: false,
                isPdf: false,
                isDocx: false,
                isText: false,
                isTrashed: false,
                url: '',
                name: ''
            },
            
            closeContextMenu() {
                this.contextMenu.show = false;
            }
        }"
        @click="closeContextMenu()"
        @keydown.escape.window="lightbox.show = false; closeContextMenu(); showEditor = false;"
        @keydown.arrow-left.window="if(lightbox.show) lightbox.prev()"
        @keydown.arrow-right.window="if(lightbox.show) lightbox.next()"
        class="fml-media-box"
        :class="{ 'fml-has-editor': showEditor }"
    >
        
        <!-- Sidebar Navigation -->
        <div class="fml-sidebar">
            
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <span class="font-bold text-sm text-gray-400 uppercase tracking-wider">Navigation</span>
                
                @if($showSettings)
                    <button 
                        type="button" 
                        wire:click="mountAction('settings')"
                        wire:loading.attr="disabled"
                        class="text-gray-400 hover:text-primary-500 transition-colors p-1 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-75 disabled:cursor-wait"
                        title="Media Settings"
                    >
                        <svg wire:loading.remove wire:target="mountAction('settings')" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827a1.125 1.125 0 0 1 .26 1.43l-1.297 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg wire:loading wire:target="mountAction('settings')" class="w-5 h-5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                @endif
            </div>

            <!-- Library / Home -->
            <button 
                type="button"
                wire:click="selectCollection('all')"
                class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeCollection === 'all' && $filterTrashed !== 'only' ? 'bg-primary-50 dark:bg-primary-950/30 text-primary-600 dark:text-primary-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                <span>All Files</span>
            </button>

            <!-- Folders / Collections -->
            <div class="flex flex-col gap-1 mt-2">
                <span class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Folders</span>
                
                @foreach($this->getFolders() as $folder)
                    @if($folder)
                        <button 
                            type="button"
                            wire:click="selectCollection('{{ $folder }}')"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-sm transition-all duration-200 {{ $activeCollection === $folder && $filterTrashed !== 'only' ? 'bg-primary-50 dark:bg-primary-950/30 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                        >
                            <div class="flex items-center gap-3 truncate">
                                <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />
                                </svg>
                                <span class="truncate">{{ $folder }}</span>
                            </div>
                            <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full font-mono">
                                {{ $this->getCollectionFileCount($folder) }}
                            </span>
                        </button>
                    @endif
                @endforeach
            </div>

            <!-- Trash -->
            <div class="border-t border-gray-100 dark:border-gray-800 pt-3 mt-2">
                <button 
                    type="button"
                    wire:click="$set('filterTrashed', 'only')"
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 {{ $filterTrashed === 'only' ? 'bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span>Trash</span>
                    </div>
                    @if($this->getTrashedCount() > 0)
                        <span class="text-xs bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 px-2 py-0.5 rounded-full font-semibold">
                            {{ $this->getTrashedCount() }}
                        </span>
                    @endif
                </button>
            </div>
            
        </div>

        <!-- Main Content Area -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- Welcome / Users Guide Card -->
            <div 
                x-show="showGuide" 
                x-cloak 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-4"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                class="fml-guide-card relative"
            >
                <button type="button" @click="dismissGuide()" class="fml-guide-card-close" title="Dismiss Guide">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex items-center gap-3 mb-2">
                    <div class="p-2 bg-primary-500/10 rounded-lg text-primary-500 dark:text-primary-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 21l8.973-8.973m0 0L18 7l-8.973 8.973Zm0 0L5.25 12h4.562M21 12c0 1.657-3.134 3-7 3s-7-1.343-7-3 3.134-3 7-3 7 1.343 7 3Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-extrabold text-base text-gray-900 dark:text-gray-100">Welcome to your Media Library Guide!</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Here is a quick overview of features to get you started. Once dismissed, this guide will not show again.</p>
                    </div>
                </div>
                
                <div class="fml-guide-grid">
                    <div class="fml-guide-item">
                        <h4>📁 Upload & Folders</h4>
                        <p>Click "New Upload" to add files up to 500MB. Organise your media by target folders or collections automatically.</p>
                    </div>
                    <div class="fml-guide-item">
                        <h4>⚡ Context Actions</h4>
                        <p>Right-click any item or open the card menu to copy URLs, edit metadata, crop images, trim videos, transcode formats, or split PDFs.</p>
                    </div>
                    <div class="fml-guide-item">
                        <h4>👁️ High-Fidelity Previews</h4>
                        <p>Click any item to view it in the fullscreen Lightbox. Supports zoom for images, HTML5 video playback, audio, and PDF documents in the modal viewer.</p>
                    </div>
                </div>
            </div>
            
            <!-- Top Controls Grid -->
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <!-- Search bar -->
                <div class="flex-grow max-w-lg relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                        </svg>
                    </span>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search media files by name..." 
                        class="w-full pl-10 pr-4 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500 text-gray-900 dark:text-gray-100 transition-colors"
                    />
                </div>

                <!-- Action Buttons / Filters -->
                <div class="flex items-center gap-3">
                    
                    <!-- Grid / List view switcher -->
                    <div class="flex border border-gray-200 dark:border-gray-700 rounded-lg p-0.5 bg-gray-50 dark:bg-gray-800">
                        <button 
                            type="button" 
                            wire:click="$set('viewLayout', 'grid')"
                            class="p-1.5 rounded-md transition-colors {{ $viewLayout === 'grid' ? 'bg-white dark:bg-gray-700 shadow text-primary-500' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300' }}"
                            title="Grid View"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                        </button>
                        <button 
                            type="button" 
                            wire:click="$set('viewLayout', 'list')"
                            class="p-1.5 rounded-md transition-colors {{ $viewLayout === 'list' ? 'bg-white dark:bg-gray-700 shadow text-primary-500' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300' }}"
                            title="List View"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 5.25h16.5m-16.5-10.5h16.5" />
                            </svg>
                        </button>
                    </div>

                    <!-- Upload new button -->
                    <button 
                        type="button" 
                        wire:click="mountAction('upload')"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg bg-primary-600 hover:bg-primary-500 text-white shadow transition-all active:scale-[0.98] disabled:opacity-75 disabled:cursor-wait"
                    >
                        <svg wire:loading.remove wire:target="mountAction('upload')" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <svg wire:loading wire:target="mountAction('upload')" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>New Upload</span>
                    </button>
                </div>
            </div>

            <!-- File Explorer Filters (Top of files area) -->
            <div class="flex flex-wrap items-center gap-2 pb-2">
                @php
                    $filterTypes = [
                        'all' => [
                            'label' => 'All Types',
                            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />'
                        ],
                        'image' => [
                            'label' => 'Images',
                            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />'
                        ],
                        'video' => [
                            'label' => 'Videos',
                            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />'
                        ],
                        'audio' => [
                            'label' => 'Audios',
                            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />'
                        ],
                        'document' => [
                            'label' => 'Documents',
                            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />'
                        ],
                    ];
                @endphp
                @foreach($filterTypes as $key => $type)
                    <button 
                        type="button"
                        wire:click="$set('activeType', '{{ $key }}')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 {{ $activeType === $key ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 border-transparent shadow' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                    >
                        <svg class="w-3.5 h-3.5 {{ $activeType === $key ? 'text-white dark:text-gray-900' : 'text-gray-400 dark:text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            {!! $type['svg'] !!}
                        </svg>
                        <span>{{ $type['label'] }}</span>
                    </button>
                @endforeach

                @if($filterTrashed === 'only')
                    <button 
                        type="button"
                        wire:click="selectCollection('all')"
                        class="ml-auto text-xs text-rose-500 hover:underline flex items-center gap-1 font-semibold"
                    >
                        Exit Trash View
                    </button>
                @endif
            </div>

            <!-- Path Breadcrumb indicator -->
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="hover:text-primary-500 cursor-pointer font-medium" wire:click="selectCollection('all')">Home</span>
                
                @if($activeCollection !== 'all')
                    <svg class="w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                    <span class="font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-gray-850 px-2 py-0.5 rounded">{{ $activeCollection }}</span>
                @endif

                @if($filterTrashed === 'only')
                    <svg class="w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                    <span class="font-bold text-rose-500 bg-rose-50 dark:bg-rose-950/20 px-2 py-0.5 rounded">Trash</span>
                @endif
            </div>

            <!-- Folders Layout Row (only visible if activeCollection is 'all' and not searching/trashing) -->
            @if($activeCollection === 'all' && !$search && $filterTrashed !== 'only')
                @php
                    $foldersList = array_filter($this->getFolders());
                @endphp
                @if(count($foldersList) > 0)
                    <div class="flex flex-col gap-3">
                        <h3 class="font-semibold text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wider">Folders</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($foldersList as $folder)
                                <div 
                                    wire:click="selectCollection('{{ $folder }}')"
                                    class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-sm hover:shadow hover:border-primary-300 dark:hover:border-primary-800 transition-all duration-200 cursor-pointer group"
                                >
                                    <svg class="w-8 h-8 text-amber-500 group-hover:scale-105 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />
                                    </svg>
                                    <div class="truncate">
                                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate group-hover:text-primary-500 transition-colors">{{ $folder }}</h4>
                                        <p class="text-xs text-gray-400">{{ $this->getCollectionFileCount($folder) }} files</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            <!-- Files list title -->
            <h3 class="font-semibold text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-4">Files</h3>

            <!-- Grid or List Explorer -->
            @php
                $records = $this->records;
            @endphp
            @if($records->count() > 0)
                
                @if($viewLayout === 'grid')
                    
                    <!-- Card Grid view -->
                    <div class="fml-grid">
                        @foreach($records as $item)
                            <div 
                                class="fml-card"
                                :class="{ 'ring-2 ring-primary-500 shadow-md border-primary-500': activeId === {{ $item->id }} }"
                            >
                                
                                <!-- Card Preview Area -->
                                <div 
                                    @click.stop="
                                        let itemJson = lightbox.items.find(i => i.id === {{ $item->id }});
                                        if (itemJson) selectItem(itemJson);
                                    "
                                    @dblclick="lightbox.open({{ $item->id }})"
                                    @contextmenu.prevent="
                                        contextMenu.show = true; 
                                        contextMenu.x = $event.clientX; 
                                        contextMenu.y = $event.clientY; 
                                        contextMenu.itemId = {{ $item->id }}; 
                                        contextMenu.isImage = {{ $item->isImage() ? 'true' : 'false' }}; 
                                        contextMenu.isVideo = {{ $item->isVideo() ? 'true' : 'false' }}; 
                                        contextMenu.isPdf = {{ $item->isPdf() ? 'true' : 'false' }}; 
                                        contextMenu.isDocx = {{ $item->isDocx() ? 'true' : 'false' }}; 
                                        contextMenu.isText = {{ $item->isText() ? 'true' : 'false' }}; 
                                        contextMenu.isTrashed = {{ $filterTrashed === 'only' ? 'true' : 'false' }}; 
                                        contextMenu.url = '{{ $item->getUrl() }}'; 
                                        contextMenu.name = '{{ e($item->name) }}';
                                    "
                                    class="fml-card-preview"
                                >
                                    @if ($item->isImage())
                                        <img src="{{ $item->getUrl('thumb') ?: $item->getUrl() }}" 
                                             alt="{{ $item->alt_text ?? $item->name }}"
                                             loading="lazy"/>
                                    @elseif ($item->isVideo() && $item->getUrl('thumb') && Storage::disk($item->disk)->exists($item->getPath('thumb')))
                                        <img src="{{ $item->getUrl('thumb') }}" 
                                             alt="{{ $item->name }}"
                                             loading="lazy"/>
                                    @else
                                        <div class="flex flex-col items-center justify-center p-4 text-center">
                                            @php
                                                $svgPath = match($item->getType()) {
                                                    'video' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25Z" />',
                                                    'audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                                                    'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                                                    default => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />',
                                                };
                                                $color = match($item->getType()) {
                                                    'video' => 'text-purple-500',
                                                    'audio' => 'text-indigo-500',
                                                    'document' => 'text-blue-500',
                                                    default => 'text-gray-500',
                                                };
                                            @endphp
                                            <svg class="w-10 h-10 mb-2 {{ $color }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                {!! $svgPath !!}
                                            </svg>
                                            <span class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 {{ $color }}">
                                                {{ strtoupper(pathinfo($item->file_name, PATHINFO_EXTENSION)) ?: 'FILE' }}
                                            </span>
                                        </div>
                                    @endif

                                    <!-- Hover Action Trigger overlay -->
                                    <div class="absolute top-1.5 right-1.5 flex items-center gap-1 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-200 z-10">
                                        <!-- Copy URL Button -->
                                        <button 
                                            type="button"
                                            x-data="{ copied: false }"
                                            @click.stop="
                                                navigator.clipboard.writeText('{{ $item->getUrl() }}');
                                                copied = true;
                                                setTimeout(() => copied = false, 2000);
                                                new FilamentNotification().title('Copied URL to clipboard.').success().send();
                                            "
                                            class="p-1.5 rounded-lg bg-white/95 dark:bg-gray-850/95 text-gray-700 dark:text-gray-300 shadow-sm border border-gray-250/50 dark:border-gray-750 hover:bg-gray-50 dark:hover:bg-gray-850 hover:text-primary-500 transition-colors"
                                            title="Copy URL"
                                        >
                                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                            </svg>
                                            <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-green-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </button>

                                        <!-- Dropdown Action menu -->
                                        <div class="relative" x-data="{ dropdownOpen: false }" @click.away="dropdownOpen = false">
                                            <button 
                                                type="button"
                                                @click.stop="dropdownOpen = !dropdownOpen"
                                                class="p-1.5 rounded-lg bg-white/95 dark:bg-gray-850/95 text-gray-700 dark:text-gray-300 shadow-sm border border-gray-250/50 dark:border-gray-750 hover:bg-gray-50 dark:hover:bg-gray-850 hover:text-primary-500 transition-colors"
                                                title="Actions"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </button>
                                            
                                            <div 
                                                x-show="dropdownOpen"
                                                x-cloak
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                class="absolute right-0 mt-1.5 z-30 min-w-[160px] bg-white dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded-lg shadow-xl py-1 text-left text-xs"
                                            >
                                                <!-- Preview -->
                                                <button 
                                                    type="button"
                                                    @click.stop="lightbox.open({{ $item->id }}); dropdownOpen = false;"
                                                    class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition"
                                                >
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    <span>Preview</span>
                                                </button>
                                                
                                                <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>

                                                @if($filterTrashed === 'only')
                                                    <!-- Restore -->
                                                    <button 
                                                        type="button"
                                                        wire:click="mountAction('restore', { record: {{ $item->id }} })"
                                                        wire:loading.attr="disabled"
                                                        @click.stop="dropdownOpen = false"
                                                        class="flex items-center gap-2 w-full px-3 py-1.5 text-primary-650 hover:bg-primary-50 dark:hover:bg-primary-950/20 transition font-medium disabled:opacity-70 disabled:cursor-wait"
                                                    >
                                                        <svg wire:loading.remove wire:target="mountAction('restore', { record: {{ $item->id }} })" class="w-3.5 h-3.5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                        </svg>
                                                        <svg wire:loading wire:target="mountAction('restore', { record: {{ $item->id }} })" class="w-3.5 h-3.5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        <span>Restore</span>
                                                    </button>
                                                    
                                                    <!-- Force Delete -->
                                                    <button 
                                                        type="button"
                                                        wire:click="mountAction('forceDelete', { record: {{ $item->id }} })"
                                                        wire:loading.attr="disabled"
                                                        @click.stop="dropdownOpen = false"
                                                        class="flex items-center gap-2 w-full px-3 py-1.5 text-rose-655 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition font-medium disabled:opacity-70 disabled:cursor-wait"
                                                    >
                                                        <svg wire:loading.remove wire:target="mountAction('forceDelete', { record: {{ $item->id }} })" class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                        </svg>
                                                        <svg wire:loading wire:target="mountAction('forceDelete', { record: {{ $item->id }} })" class="w-3.5 h-3.5 animate-spin text-rose-500" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        <span>Delete Permanent</span>
                                                    </button>
                                                @else
                                                    <!-- Edit -->
                                                    <button 
                                                        type="button"
                                                        wire:click="mountAction('edit', { record: {{ $item->id }} })"
                                                        wire:loading.attr="disabled"
                                                        @click.stop="dropdownOpen = false"
                                                        class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition disabled:opacity-70 disabled:cursor-wait"
                                                    >
                                                        <svg wire:loading.remove wire:target="mountAction('edit', { record: {{ $item->id }} })" class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.013a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                        </svg>
                                                        <svg wire:loading wire:target="mountAction('edit', { record: {{ $item->id }} })" class="w-3.5 h-3.5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        <span>Edit Metadata</span>
                                                    </button>

                                                    <!-- Crop Image (only for images) -->
                                                    @if($item->isImage())
                                                        <button 
                                                            type="button"
                                                            wire:click="mountAction('crop', { record: {{ $item->id }} })"
                                                            wire:loading.attr="disabled"
                                                            @click.stop="dropdownOpen = false"
                                                            class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                                            </svg>
                                                            <span>Crop Image</span>
                                                        </button>
                                                        
                                                        <!-- Resize -->
                                                        <button 
                                                            type="button"
                                                            wire:click="mountAction('convert', { record: {{ $item->id }} })"
                                                            wire:loading.attr="disabled"
                                                            @click.stop="dropdownOpen = false"
                                                            class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition disabled:opacity-70 disabled:cursor-wait"
                                                        >
                                                            <svg wire:loading.remove wire:target="mountAction('convert', { record: {{ $item->id }} })" class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9M20.25 20.25v-4.5m0 4.5h-4.5m4.5 0L15 15" />
                                                            </svg>
                                                            <svg wire:loading wire:target="mountAction('convert', { record: {{ $item->id }} })" class="w-3.5 h-3.5 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            <span>Resize / Convert</span>
                                                        </button>
                                                    @endif

                                                    <!-- Trim Video (only for videos) -->
                                                    @if($item->isVideo())
                                                        <button 
                                                            type="button"
                                                            wire:click="mountAction('trim', { record: {{ $item->id }} })"
                                                            wire:loading.attr="disabled"
                                                            @click.stop="dropdownOpen = false"
                                                            class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75-15h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25h-10.5A2.25 2.25 0 0 1 4.5 16.5V6.75A2.25 2.25 0 0 1 6.75 4.5Z" />
                                                            </svg>
                                                            <span>Trim Video</span>
                                                        </button>
                                                    @endif

                                                    <!-- DOCX text replacement (only for DOCX) -->
                                                    @if($item->isDocx())
                                                        <button 
                                                            type="button"
                                                            wire:click="mountAction('editDocx', { record: {{ $item->id }} })"
                                                            wire:loading.attr="disabled"
                                                            @click.stop="dropdownOpen = false"
                                                            class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                            </svg>
                                                            <span>Find & Replace</span>
                                                        </button>
                                                    @endif

                                                    <!-- PDF metadata editor (only for PDF) -->
                                                    @if($item->isPdf())
                                                        <button 
                                                            type="button"
                                                            wire:click="mountAction('editPdf', { record: {{ $item->id }} })"
                                                            wire:loading.attr="disabled"
                                                            @click.stop="dropdownOpen = false"
                                                            class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5-3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                            </svg>
                                                            <span>Edit PDF Meta</span>
                                                        </button>
                                                    @endif

                                                    <!-- Text editor (only for text files) -->
                                                    @if($item->isText())
                                                        <button 
                                                            type="button"
                                                            wire:click="mountAction('editText', { record: {{ $item->id }} })"
                                                            wire:loading.attr="disabled"
                                                            @click.stop="dropdownOpen = false"
                                                            class="flex items-center gap-2 w-full px-3 py-1.5 text-gray-750 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-850 transition"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5-3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                            </svg>
                                                            <span>Edit Text</span>
                                                        </button>
                                                    @endif
                                                    
                                                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>

                                                    <!-- Delete -->
                                                    <button 
                                                        type="button"
                                                        wire:click="mountAction('delete', { record: {{ $item->id }} })"
                                                        wire:loading.attr="disabled"
                                                        @click.stop="dropdownOpen = false"
                                                        class="flex items-center gap-2 w-full px-3 py-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition font-medium disabled:opacity-70 disabled:cursor-wait"
                                                    >
                                                        <svg wire:loading.remove wire:target="mountAction('delete', { record: {{ $item->id }} })" class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                        </svg>
                                                        <svg wire:loading wire:target="mountAction('delete', { record: {{ $item->id }} })" class="w-3.5 h-3.5 animate-spin text-rose-500" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        <span>Delete</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Metadata Footer -->
                                <div class="fml-card-footer">
                                    <div class="truncate">
                                        <h4 class="fml-card-title" title="{{ $item->name }}">
                                            {{ $item->name }}
                                        </h4>
                                        <div class="fml-card-meta">
                                            <span class="font-mono">{{ number_format($item->size / 1024, 1) }} KB</span>
                                            <span>•</span>
                                            <span class="truncate">{{ $item->collection_name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                
                @else
                    
                    <!-- Traditional table layout list view but with modern style -->
                    <div class="overflow-x-auto bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 text-gray-400 dark:text-gray-400 text-xs font-semibold uppercase tracking-wider">
                                    <th class="px-6 py-4">Preview</th>
                                    <th class="px-6 py-4">Title</th>
                                    <th class="px-6 py-4">File Name</th>
                                    <th class="px-6 py-4">Mime Type</th>
                                    <th class="px-6 py-4">Size</th>
                                    <th class="px-6 py-4">Collection</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                                @foreach($records as $item)
                                    <tr 
                                        @click="
                                            let itemJson = lightbox.items.find(i => i.id === {{ $item->id }});
                                            if (itemJson) selectItem(itemJson);
                                        "
                                        @dblclick="lightbox.open({{ $item->id }})"
                                        class="hover:bg-gray-50/50 dark:hover:bg-gray-850 transition-colors cursor-pointer"
                                        :class="{ 'bg-primary-50/30 dark:bg-primary-950/20 font-semibold text-primary-600 dark:text-primary-400': activeId === {{ $item->id }} }"
                                    >
                                        <td class="px-6 py-4">
                                            @if($item->isImage())
                                                <img src="{{ $item->getUrl('thumb') ?: $item->getUrl() }}" class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700 shadow-sm" />
                                            @elseif ($item->isVideo() && $item->getUrl('thumb') && Storage::disk($item->disk)->exists($item->getPath('thumb')))
                                                <img src="{{ $item->getUrl('thumb') }}" class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700 shadow-sm" />
                                            @else
                                                @php
                                                    $svgPath = match($item->getType()) {
                                                        'video' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25Z" />',
                                                        'audio' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                                                        'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                                                        default => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a3 3 0 0 1 2.122.879l2.121 2.121H19.5a3 3 0 0 1 3 3v1.146A4.487 4.487 0 0 0 19.5 9h-15a4.487 4.487 0 0 0-3 1.146Z" />',
                                                    };
                                                    $svgColor = match($item->getType()) {
                                                        'video' => 'text-purple-500',
                                                        'audio' => 'text-indigo-500',
                                                        'document' => 'text-blue-500',
                                                        default => 'text-gray-400 dark:text-gray-500',
                                                    };
                                                @endphp
                                                <svg class="w-8 h-8 {{ $svgColor }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    {!! $svgPath !!}
                                                </svg>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100 truncate max-w-[150px]">{{ $item->name }}</td>
                                        <td class="px-6 py-4 truncate max-w-[200px] text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $item->file_name }}</td>
                                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $item->mime_type }}</td>
                                        <td class="px-6 py-4 font-mono text-xs">{{ number_format($item->size / 1024, 2) }} KB</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-md bg-gray-50 dark:bg-gray-800 px-2 py-1 text-xs font-semibold text-gray-600 dark:text-gray-450 ring-1 ring-inset ring-gray-500/10">
                                                {{ $item->collection_name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-3">
                                                @if($filterTrashed === 'only')
                                                    <button type="button" wire:click="mountAction('restore', { record: {{ $item->id }} })" class="text-xs font-bold text-primary-500 hover:text-primary-600">Restore</button>
                                                    <button type="button" wire:click="mountAction('forceDelete', { record: {{ $item->id }} })" class="text-xs font-bold text-rose-500 hover:text-rose-600">Delete Permanently</button>
                                                @else
                                                    <button type="button" wire:click="mountAction('edit', { record: {{ $item->id }} })" class="text-xs font-bold text-gray-500 hover:text-primary-500">Edit</button>
                                                    
                                                    @if($item->isImage())
                                                        <button type="button" wire:click="mountAction('crop', { record: {{ $item->id }} })" class="text-xs font-bold text-gray-500 hover:text-primary-500">Crop</button>
                                                        <button type="button" wire:click="mountAction('convert', { record: {{ $item->id }} })" class="text-xs font-bold text-emerald-500 hover:text-emerald-600">Resize</button>
                                                    @endif

                                                    @if($item->isVideo())
                                                        <button type="button" wire:click="mountAction('trim', { record: {{ $item->id }} })" class="text-xs font-bold text-purple-500 hover:text-purple-600">Trim</button>
                                                    @endif

                                                    @if($item->isDocx())
                                                        <button type="button" wire:click="mountAction('editDocx', { record: {{ $item->id }} })" class="text-xs font-bold text-blue-500 hover:text-blue-600">Find & Replace</button>
                                                    @endif

                                                    @if($item->isPdf())
                                                        <button type="button" wire:click="mountAction('editPdf', { record: {{ $item->id }} })" class="text-xs font-bold text-blue-500 hover:text-blue-600">PDF Meta</button>
                                                    @endif

                                                    @if($item->isText())
                                                        <button type="button" wire:click="mountAction('editText', { record: {{ $item->id }} })" class="text-xs font-bold text-blue-500 hover:text-blue-600">Edit Text</button>
                                                    @endif
                                                    
                                                    <button type="button" wire:click="mountAction('delete', { record: {{ $item->id }} })" class="text-xs font-bold text-rose-500 hover:text-rose-600">Delete</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                
                @endif

                <!-- Pagination area -->
                <div class="mt-4">
                    {{ $records->links() }}
                </div>

            @else
                
                <!-- Gorgeous Google Drive style Empty State -->
                <div class="flex flex-col items-center justify-center p-12 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-2xl bg-white dark:bg-gray-900 shadow-sm text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-50 dark:bg-gray-850 flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">No media files found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mt-1">There are no assets matching your filters in this collection folder. Upload files to get started!</p>
                    <button 
                        type="button" 
                        wire:click="mountAction('upload')"
                        class="mt-4 px-4 py-2 text-sm font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition-colors"
                    >
                        Upload Files
                    </button>
                </div>

            @endif

        </div>

        <!-- Right-Click Context Menu -->
        <div 
            x-show="contextMenu.show"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fml-context-menu"
            :style="'left: ' + contextMenu.x + 'px; top: ' + contextMenu.y + 'px;'"
            @click.away="closeContextMenu()"
        >
            <!-- Copy URL -->
            <button 
                type="button"
                @click.stop="
                    navigator.clipboard.writeText(contextMenu.url);
                    closeContextMenu();
                    new FilamentNotification().title('Copied URL to clipboard.').success().send();
                "
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                </svg>
                <span>Copy URL</span>
            </button>

            <!-- Preview -->
            <button 
                type="button"
                @click.stop="lightbox.open(contextMenu.itemId); closeContextMenu();"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span>Preview</span>
            </button>

            <div class="fml-context-divider"></div>

            <!-- Normal Actions -->
            <template x-if="!contextMenu.isTrashed">
                <div class="contents">
                    <!-- Edit -->
                    <button 
                        type="button"
                        @click.stop="$wire.mountAction('edit', { record: contextMenu.itemId }); closeContextMenu();"
                    >
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.013a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                        <span>Edit Metadata</span>
                    </button>

                    <!-- Crop Image -->
                    <template x-if="contextMenu.isImage">
                        <button 
                            type="button"
                            @click.stop="$wire.mountAction('crop', { record: contextMenu.itemId }); closeContextMenu();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            <span>Crop Image</span>
                        </button>
                    </template>

                    <!-- Convert / Resize -->
                    <template x-if="contextMenu.isImage">
                        <button 
                            type="button"
                            @click.stop="$wire.mountAction('convert', { record: contextMenu.itemId }); closeContextMenu();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9M20.25 20.25v-4.5m0 4.5h-4.5m4.5 0L15 15" />
                            </svg>
                            <span>Resize / Convert</span>
                        </button>
                    </template>

                    <!-- Trim Video -->
                    <template x-if="contextMenu.isVideo">
                        <button 
                            type="button"
                            @click.stop="$wire.mountAction('trim', { record: contextMenu.itemId }); closeContextMenu();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75-15h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25h-10.5A2.25 2.25 0 0 1 4.5 16.5V6.75A2.25 2.25 0 0 1 6.75 4.5Z" />
                            </svg>
                            <span>Trim Video</span>
                        </button>
                    </template>

                    <!-- DOCX Find & Replace -->
                    <template x-if="contextMenu.isDocx">
                        <button 
                            type="button"
                            @click.stop="$wire.mountAction('editDocx', { record: contextMenu.itemId }); closeContextMenu();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            <span>Find & Replace</span>
                        </button>
                    </template>

                    <!-- PDF Metadata Editor -->
                    <template x-if="contextMenu.isPdf">
                        <button 
                            type="button"
                            @click.stop="$wire.mountAction('editPdf', { record: contextMenu.itemId }); closeContextMenu();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5-3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span>Edit PDF Meta</span>
                        </button>
                    </template>

                    <!-- Plain Text Editor -->
                    <template x-if="contextMenu.isText">
                        <button 
                            type="button"
                            @click.stop="$wire.mountAction('editText', { record: contextMenu.itemId }); closeContextMenu();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5-3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span>Edit Text</span>
                        </button>
                    </template>

                    <div class="fml-context-divider"></div>

                    <!-- Delete -->
                    <button 
                        type="button"
                        @click.stop="$wire.mountAction('delete', { record: contextMenu.itemId }); closeContextMenu();"
                        class="text-rose-600 font-medium"
                    >
                        <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span>Delete</span>
                    </button>
                </div>
            </template>

            <!-- Trashed Actions -->
            <template x-if="contextMenu.isTrashed">
                <div class="contents">
                    <!-- Restore -->
                    <button 
                        type="button"
                        @click.stop="$wire.mountAction('restore', { record: contextMenu.itemId }); closeContextMenu();"
                        class="text-primary-600 font-medium"
                    >
                        <svg class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Restore</span>
                    </button>

                    <div class="fml-context-divider"></div>

                    <!-- Force Delete -->
                    <button 
                        type="button"
                        @click.stop="$wire.mountAction('forceDelete', { record: contextMenu.itemId }); closeContextMenu();"
                        class="text-rose-600 font-medium"
                    >
                        <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span>Delete Permanently</span>
                    </button>
                </div>
            </template>
        </div>

        <!-- Right Visual Editor Panel -->
        <div 
            x-show="showEditor" 
            x-cloak
            class="fml-editor-panel lg:col-span-1"
            x-transition:enter="transition ease-out duration-300 transform translate-x-4 opacity-0"
            x-transition:enter-end="transform translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200 transform translate-x-4 opacity-0"
        >
            <!-- Header -->
            <div class="fml-editor-header">
                <div>
                    <h3 class="fml-editor-title" x-text="activeItem?.name || 'Visual Editor'">Visual Preview & Editor</h3>
                    <p class="text-[10px] text-gray-400 font-mono mt-0.5" x-text="activeItem?.file_name"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        type="button"
                        @click="lightbox.open(activeId)"
                        class="text-gray-400 hover:text-primary-500 p-1 rounded-lg transition"
                        title="Fullscreen Preview"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9M20.25 20.25v-4.5m0 4.5h-4.5m4.5 0L15 15" />
                        </svg>
                    </button>
                    <button 
                        type="button" 
                        @click="showEditor = false"
                        class="text-gray-400 hover:text-rose-500 p-1 rounded-lg transition"
                        title="Close Panel"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Workspace -->
            <div class="flex flex-col gap-4">
                
                <!-- 1. View / Preview mode -->
                <div x-show="editorMode === 'view'" class="flex flex-col gap-4">
                    <div class="fml-editor-preview-container">
                        <!-- Image -->
                        <template x-if="activeItem?.type === 'image' && !(activeItem?.file_name.toLowerCase().endsWith('.svg') || activeItem?.mime_type === 'image/svg+xml')">
                            <img :src="activeItem?.url" class="rounded-lg shadow-sm" />
                        </template>
                        <!-- Video -->
                        <template x-if="activeItem?.type === 'video'">
                            <video :src="activeItem?.url" controls class="w-full rounded-lg"></video>
                        </template>
                        <!-- SVG -->
                        <template x-if="activeItem?.file_name.toLowerCase().endsWith('.svg') || activeItem?.mime_type === 'image/svg+xml'">
                            <div class="w-full h-full p-4 flex items-center justify-center bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="max-w-full max-h-[200px]" x-html="svgContent"></div>
                            </div>
                        </template>
                        <!-- Audio -->
                        <template x-if="activeItem?.type === 'audio'">
                            <div class="flex flex-col items-center justify-center p-6 text-center w-full">
                                <svg class="w-12 h-12 text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <audio :src="activeItem?.url" controls class="w-full mt-2"></audio>
                            </div>
                        </template>
                        <!-- DOCX Mock/HTML preview -->
                        <template x-if="activeItem?.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || activeItem?.mime_type === 'application/msword'">
                            <div class="fml-docx-preview w-full">
                                <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-2 mb-2">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25" />
                                    </svg>
                                    <span class="font-bold text-xs">Microsoft Word Document</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold mb-1">Visual Preview:</p>
                                <p class="text-xs italic leading-relaxed text-gray-650 dark:text-gray-350">
                                    [DOCX text stream is loaded. You can visually replace occurrences of text strings inside this file using the Find & Replace tab.]
                                </p>
                            </div>
                        </template>
                        <!-- PDF document preview -->
                        <template x-if="activeItem?.mime_type === 'application/pdf'">
                            <iframe :src="activeItem?.url" class="w-full h-[220px] border-none rounded-lg bg-white"></iframe>
                        </template>
                    </div>

                    <!-- Visual Editor Toolbar/Buttons -->
                    <div class="flex flex-wrap gap-2">
                        <!-- Image Crop -->
                        <template x-if="activeItem?.type === 'image' && !(activeItem?.file_name.toLowerCase().endsWith('.svg') || activeItem?.mime_type === 'image/svg+xml')">
                            <button 
                                type="button"
                                @click="initCropper()"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                </svg>
                                <span>Visual Crop</span>
                            </button>
                        </template>

                        <!-- Video Trim -->
                        <template x-if="activeItem?.type === 'video'">
                            <button 
                                type="button"
                                @click="
                                    editorMode = 'trim';
                                    trimStart = 0;
                                    trimEnd = 5; // Default 5 seconds
                                "
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75-15h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25h-10.5A2.25 2.25 0 0 1 4.5 16.5V6.75A2.25 2.25 0 0 1 6.75 4.5Z" />
                                </svg>
                                <span>Visual Trim</span>
                            </button>
                        </template>

                        <!-- SVG XML edit -->
                        <template x-if="activeItem?.file_name.toLowerCase().endsWith('.svg') || activeItem?.mime_type === 'image/svg+xml'">
                            <button 
                                type="button"
                                @click="editorMode = 'svg'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                                </svg>
                                <span>Edit SVG Source</span>
                            </button>
                        </template>

                        <!-- DOCX Replace -->
                        <template x-if="activeItem?.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || activeItem?.mime_type === 'application/msword'">
                            <button 
                                type="button"
                                @click="editorMode = 'docx'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                                <span>Find & Replace</span>
                            </button>
                        </template>

                        <!-- PDF Meta Edit -->
                        <template x-if="activeItem?.mime_type === 'application/pdf'">
                            <button 
                                type="button"
                                @click="editorMode = 'pdf'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5" />
                                </svg>
                                <span>PDF Metadata</span>
                            </button>
                        </template>

                        <!-- Text Edit -->
                        <template x-if="activeItem?.mime_type === 'text/plain' || activeItem?.file_name.toLowerCase().endsWith('.txt') || activeItem?.file_name.toLowerCase().endsWith('.json') || activeItem?.file_name.toLowerCase().endsWith('.md')">
                            <button 
                                type="button"
                                @click="editorMode = 'text'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white shadow hover:bg-primary-500 transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.013a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                </svg>
                                <span>Edit Text Content</span>
                            </button>
                        </template>
                    </div>

                    <!-- Metadata Edit Form -->
                    <div class="border-t border-gray-150 dark:border-gray-800 pt-4 flex flex-col gap-3">
                        <span class="font-bold text-xs text-gray-400 uppercase tracking-wider">File Metadata</span>
                        
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-500 dark:text-gray-400 font-semibold">Title</label>
                            <input 
                                type="text" 
                                x-model="activeItem.name" 
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-500 dark:text-gray-400 font-semibold">Alternative Text (Alt Text)</label>
                            <input 
                                type="text" 
                                x-model="activeItem.alt_text" 
                                placeholder="Description for screen readers..."
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <div class="flex items-center justify-between text-xs text-gray-400 font-mono py-2 bg-gray-50 dark:bg-gray-800/40 rounded-lg px-3 mt-1">
                            <div class="flex flex-col gap-0.5">
                                <span>Mime: <span class="text-gray-600 dark:text-gray-300" x-text="activeItem?.mime_type"></span></span>
                                <span>Size: <span class="text-gray-600 dark:text-gray-300" x-text="activeItem?.size"></span></span>
                            </div>
                            <template x-if="activeItem?.width">
                                <span>Dim: <span class="text-gray-600 dark:text-gray-300" x-text="activeItem?.width + 'x' + activeItem?.height"></span></span>
                            </template>
                        </div>

                        <button 
                            type="button"
                            @click="saveMeta()"
                            class="w-full px-4 py-2 text-xs font-semibold rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow hover:bg-gray-850 dark:hover:bg-gray-100 transition"
                        >
                            Save Metadata Changes
                        </button>
                    </div>
                </div>

                <!-- 2. Visual Image Crop mode -->
                <div x-show="editorMode === 'crop'" class="flex flex-col gap-4">
                    <div class="fml-cropper-wrapper bg-black flex items-center justify-center rounded-lg overflow-hidden border border-gray-850">
                        <img id="fml-cropper-image" :src="activeItem?.url" class="max-w-full" style="max-height:250px;" />
                    </div>
                    <div class="flex items-center justify-between text-xs font-mono text-gray-400 bg-gray-50 dark:bg-gray-850 px-3 py-2 rounded-lg">
                        <span>X: <span x-text="cropData.x" class="text-primary-500"></span></span>
                        <span>Y: <span x-text="cropData.y" class="text-primary-500"></span></span>
                        <span>W: <span x-text="cropData.width" class="text-primary-500"></span> px</span>
                        <span>H: <span x-text="cropData.height" class="text-primary-500"></span> px</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="saveCrop()"
                            class="flex-1 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow transition"
                        >
                            Apply Crop
                        </button>
                        <button 
                            type="button"
                            @click="
                                editorMode = 'view';
                                if (cropper) {
                                    cropper.destroy();
                                    cropper = null;
                                }
                            "
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- 3. Video Trim mode -->
                <div x-show="editorMode === 'trim'" class="flex flex-col gap-4">
                    <div class="bg-black flex items-center justify-center rounded-lg overflow-hidden border border-gray-850 p-2">
                        <video id="fml-trimmer-video" :src="activeItem?.url" controls class="w-full max-h-[160px]"></video>
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <span class="text-xs text-gray-500 font-semibold">Trim Settings</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex flex-col gap-1">
                                <label class="text-[10px] text-gray-400 font-semibold uppercase">Start Second</label>
                                <input 
                                    type="number" 
                                    step="0.1" 
                                    min="0"
                                    x-model="trimStart"
                                    class="w-full text-xs px-2.5 py-1.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100 font-mono"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-[10px] text-gray-400 font-semibold uppercase">End Second</label>
                                <input 
                                    type="number" 
                                    step="0.1"
                                    min="0.1"
                                    x-model="trimEnd"
                                    class="w-full text-xs px-2.5 py-1.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100 font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="saveTrim()"
                            class="flex-1 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow transition"
                        >
                            Trim Video
                        </button>
                        <button 
                            type="button"
                            @click="editorMode = 'view'"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- 4. SVG Visual Edit mode -->
                <div x-show="editorMode === 'svg'" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500 font-semibold">SVG Source Code Editor</span>
                        <textarea 
                            x-model="svgContent" 
                            class="fml-svg-editor-textarea"
                            spellcheck="false"
                        ></textarea>
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <span class="text-xs text-gray-500 font-semibold">Real-Time XML Render</span>
                        <div class="w-full p-4 flex items-center justify-center bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 min-h-[120px] max-h-[180px] overflow-auto">
                            <div class="max-w-full max-h-[150px]" x-html="svgContent"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="saveSvg()"
                            class="flex-1 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow transition"
                        >
                            Save Source
                        </button>
                        <button 
                            type="button"
                            @click="editorMode = 'view'"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- 5. DOCX Find & Replace mode -->
                <div x-show="editorMode === 'docx'" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3">
                        <span class="text-xs text-gray-500 font-semibold">Find & Replace in Document</span>
                        
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">Search for text</label>
                            <input 
                                type="text" 
                                x-model="docxFind" 
                                placeholder="Text to search..."
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">Replace with</label>
                            <input 
                                type="text" 
                                x-model="docxReplace" 
                                placeholder="Text to insert..."
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="saveDocx()"
                            class="flex-1 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow transition"
                        >
                            Replace All
                        </button>
                        <button 
                            type="button"
                            @click="editorMode = 'view'"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- 6. PDF Metadata mode -->
                <div x-show="editorMode === 'pdf'" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3">
                        <span class="text-xs text-gray-500 font-semibold">PDF Document Properties</span>
                        
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">PDF Title</label>
                            <input 
                                type="text" 
                                x-model="pdfTitle" 
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">Author</label>
                            <input 
                                type="text" 
                                x-model="pdfAuthor" 
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">Subject</label>
                            <input 
                                type="text" 
                                x-model="pdfSubject" 
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">Keywords</label>
                            <input 
                                type="text" 
                                x-model="pdfKeywords" 
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-gray-400 font-semibold uppercase">Creator</label>
                            <input 
                                type="text" 
                                x-model="pdfCreator" 
                                class="w-full text-xs px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="savePdf()"
                            class="flex-1 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow transition"
                        >
                            Save PDF Meta
                        </button>
                        <button 
                            type="button"
                            @click="editorMode = 'view'"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- 7. Plain Text Editor mode -->
                <div x-show="editorMode === 'text'" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500 font-semibold">Edit File Content</span>
                        <textarea 
                            x-model="textContent" 
                            class="fml-svg-editor-textarea h-[250px]"
                            spellcheck="false"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="saveText()"
                            class="flex-1 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow transition"
                        >
                            Save File
                        </button>
                        <button 
                            type="button"
                            @click="editorMode = 'view'"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Teleported Media Lightbox -->
        <template x-teleport="body">
            <div 
                x-show="lightbox.show" 
                x-cloak
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fml-lightbox select-none"
            >
                <!-- Header -->
                <div class="fml-lightbox-header">
                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-base truncate max-w-[280px] sm:max-w-[450px] md:max-w-xl" x-text="lightbox.current.name"></span>
                        <span class="text-xs text-gray-400 truncate max-w-[280px] sm:max-w-[450px] md:max-w-xl" x-text="lightbox.current.file_name + ' • ' + lightbox.current.size + (lightbox.current.width ? ' • ' + lightbox.current.width + 'x' + lightbox.current.height : '')"></span>
                    </div>
                    
                    <div class="flex items-center gap-3 ml-2 flex-shrink-0">
                        <!-- Copy URL Action -->
                        <button 
                            type="button" 
                            x-data="{ copied: false }"
                            @click="
                                navigator.clipboard.writeText(lightbox.current.url);
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                                new FilamentNotification().title('Copied URL to clipboard.').success().send();
                            "
                            class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition text-gray-200 hover:text-white"
                            title="Copy URL"
                        >
                            <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            <svg x-show="copied" x-cloak class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </button>
                        
                        <!-- Close Lightbox -->
                        <button 
                            type="button" 
                            @click="lightbox.show = false"
                            class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition text-gray-200 hover:text-white"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Main Viewer content -->
                <div class="fml-lightbox-content">
                    
                    <!-- Left Arrow -->
                    <button 
                        type="button" 
                        @click.stop="lightbox.prev()"
                        class="fml-lightbox-nav"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    
                    <!-- Content view area -->
                    <div class="fml-lightbox-viewer select-text">
                        <template x-if="lightbox.current.type === 'image'">
                            <img 
                                :src="lightbox.current.url" 
                                :alt="lightbox.current.name"
                                class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-2xl transition duration-300"
                            />
                        </template>
                        
                        <template x-if="lightbox.current.type === 'video'">
                            <video 
                                :src="lightbox.current.url" 
                                controls 
                                autoplay
                                class="fml-video-player max-h-[75vh]"
                            ></video>
                        </template>
                        
                        <template x-if="lightbox.current.type === 'audio'">
                            <div class="flex flex-col items-center justify-center p-8 bg-white/5 rounded-2xl border border-white/10 w-full max-w-md shadow-2xl text-center">
                                <div class="w-14 h-14 rounded-full bg-indigo-500/25 flex items-center justify-center text-indigo-400 mb-4 animate-pulse">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v15m0-15l-10.5 3m10.5-3V3.75a.75.75 0 0 0-.75-.75h-15a.75.75 0 0 0-.75.75v16.5c0 .414.336.75.75.75h7.5M9 21a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-3a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </div>
                                <span class="font-semibold block text-sm mb-2" x-text="lightbox.current.name"></span>
                                <audio 
                                    :src="lightbox.current.url" 
                                    controls 
                                    autoplay
                                    class="w-full mt-4"
                                ></audio>
                            </div>
                        </template>
                        
                        <!-- PDF Document Preview -->
                        <template x-if="lightbox.current.type === 'document' && lightbox.current.file_name.toLowerCase().endsWith('.pdf')">
                            <iframe 
                                :src="lightbox.current.url" 
                                class="fml-pdf-viewer"
                            ></iframe>
                        </template>

                        <!-- Non-PDF Document Fallback -->
                        <template x-if="lightbox.current.type === 'document' && !lightbox.current.file_name.toLowerCase().endsWith('.pdf')">
                            <div class="flex flex-col items-center justify-center p-8 bg-white/5 rounded-2xl border border-white/10 w-full max-w-md shadow-2xl text-center">
                                <div class="w-14 h-14 rounded-full bg-blue-500/25 flex items-center justify-center text-blue-400 mb-4">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                </div>
                                <span class="font-semibold block text-sm mb-4" x-text="lightbox.current.name"></span>
                                <a 
                                    :href="lightbox.current.url" 
                                    target="_blank"
                                    class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 font-semibold text-xs transition-all shadow-md active:scale-95 text-white"
                                >
                                    Download / View Document
                                </a>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Right Arrow -->
                    <button 
                        type="button" 
                        @click.stop="lightbox.next()"
                        class="fml-lightbox-nav"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>
                
                <!-- Bottom Index Indicator -->
                <div class="px-6 py-4 text-center text-xs text-gray-400 bg-gradient-to-t from-black/70 to-transparent">
                    <span x-text="(lightbox.currentIndex + 1) + ' / ' + lightbox.total"></span>
                </div>
            </div>
        </template>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
