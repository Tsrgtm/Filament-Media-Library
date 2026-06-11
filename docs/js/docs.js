document.addEventListener('DOMContentLoaded', () => {
    // 0. Render Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // 1. Dark Mode Toggler
    const themeToggleBtn = document.getElementById('theme-toggle');
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    const currentTheme = localStorage.getItem('theme') || systemTheme;

    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);

    themeToggleBtn.addEventListener('click', () => {
        const theme = document.documentElement.getAttribute('data-theme');
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', nextTheme);
        localStorage.setItem('theme', nextTheme);
        updateThemeIcon(nextTheme);
    });

    function updateThemeIcon(theme) {
        if (theme === 'dark') {
            themeToggleBtn.innerHTML = `<i data-lucide="sun"></i>`;
        } else {
            themeToggleBtn.innerHTML = `<i data-lucide="moon"></i>`;
        }
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    // 2. Clipboard Copy Button in Code Blocks
    const codeBlocks = document.querySelectorAll('pre');
    codeBlocks.forEach((block) => {
        const button = document.createElement('button');
        button.className = 'copy-btn';
        button.innerHTML = 'Copy';
        block.appendChild(button);

        button.addEventListener('click', () => {
            const code = block.querySelector('code').innerText;
            navigator.clipboard.writeText(code).then(() => {
                button.innerText = 'Copied!';
                button.style.borderColor = '#10b981';
                button.style.color = '#10b981';

                setTimeout(() => {
                    button.innerText = 'Copy';
                    button.style.borderColor = '';
                    button.style.color = '';
                }, 2000);
            });
        });
    });

    // 3. Scrollspy - Highlight Active Section in Sidebar
    const sections = document.querySelectorAll('section');
    const navLinks = document.querySelectorAll('.nav-link');

    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach((section) => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (pageYOffset >= sectionTop - 120) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach((link) => {
            link.classList.remove('active');
            if (link.getAttribute('href').slice(1) === current) {
                link.classList.add('active');
            }
        });
    });

    // 4. Premium Floating Search Engine
    const searchInput = document.getElementById('search-input');
    const searchContainer = document.querySelector('.search-container');
    
    // Create dropdown element
    const dropdown = document.createElement('div');
    dropdown.className = 'search-results-dropdown';
    searchContainer.appendChild(dropdown);

    // Escape HTML helper
    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }

    // Navigation and visual highlight trigger
    function navigateToSection(sectionId) {
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.pushState(null, null, '#' + sectionId);
            
            // Flash visual cue
            targetSection.classList.remove('highlight-flash');
            void targetSection.offsetWidth; // Trigger reflow to restart keyframe
            targetSection.classList.add('highlight-flash');
        }
        searchInput.value = '';
        dropdown.classList.remove('active');
    }

    // Listen for inputs and parse matches
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim().toLowerCase();
        
        if (!query) {
            dropdown.classList.remove('active');
            dropdown.innerHTML = '';
            return;
        }

        const matches = [];
        sections.forEach((section) => {
            const heading = section.querySelector('h1, h2, h3');
            const sectionTitle = heading ? heading.innerText.trim() : 'General';
            const sectionId = section.getAttribute('id') || '';

            // 1. Check title match
            if (sectionTitle.toLowerCase().includes(query)) {
                matches.push({
                    type: 'heading',
                    sectionId: sectionId,
                    title: sectionTitle,
                    snippet: `Go to the ${sectionTitle} section.`
                });
            }

            // 2. Check inner content matches (p, li, td, code)
            const contentElements = section.querySelectorAll('p, li, td, pre code');
            contentElements.forEach((el) => {
                const text = el.innerText.trim();
                if (text.toLowerCase().includes(query) && !el.closest('.callout-content') && !el.closest('h1, h2, h3')) {
                    let snippet = text;
                    if (snippet.length > 80) {
                        const index = snippet.toLowerCase().indexOf(query);
                        const start = Math.max(0, index - 25);
                        const end = Math.min(snippet.length, index + query.length + 45);
                        snippet = (start > 0 ? '...' : '') + snippet.slice(start, end) + (end < snippet.length ? '...' : '');
                    }

                    const isCode = el.tagName.toLowerCase() === 'code' || el.closest('pre') !== null;
                    if (!matches.some(m => m.snippet === snippet && m.sectionId === sectionId)) {
                        matches.push({
                            type: isCode ? 'code' : 'text',
                            sectionId: sectionId,
                            title: sectionTitle,
                            snippet: snippet
                        });
                    }
                }
            });
        });

        // Render matches in dropdown
        dropdown.innerHTML = '';
        if (matches.length === 0) {
            dropdown.innerHTML = `<div class="search-no-results">No results found for "<strong>${escapeHtml(e.target.value)}</strong>"</div>`;
        } else {
            const limitedMatches = matches.slice(0, 8);
            limitedMatches.forEach((match, index) => {
                const item = document.createElement('div');
                item.className = 'search-result-item' + (index === 0 ? ' selected' : '');
                item.setAttribute('data-section', match.sectionId);

                let icon = 'hash';
                if (match.type === 'heading') icon = 'book-open';
                else if (match.type === 'code') icon = 'terminal';
                else icon = 'file-text';

                item.innerHTML = `
                    <i class="search-result-icon" data-lucide="${icon}"></i>
                    <div class="search-result-content">
                        <span class="search-result-title">${escapeHtml(match.title)}</span>
                        <span class="search-result-snippet">${escapeHtml(match.snippet)}</span>
                    </div>
                `;

                item.addEventListener('click', () => {
                    navigateToSection(match.sectionId);
                });

                dropdown.appendChild(item);
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
        dropdown.classList.add('active');
    });

    // Keyboard navigation within results
    searchInput.addEventListener('keydown', (e) => {
        if (!dropdown.classList.contains('active')) return;

        const items = dropdown.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        let selectedIndex = -1;
        items.forEach((item, idx) => {
            if (item.classList.contains('selected')) {
                selectedIndex = idx;
            }
        });

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (selectedIndex !== -1) items[selectedIndex].classList.remove('selected');
            const nextIdx = (selectedIndex + 1) % items.length;
            items[nextIdx].classList.add('selected');
            items[nextIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (selectedIndex !== -1) items[selectedIndex].classList.remove('selected');
            const prevIdx = (selectedIndex - 1 + items.length) % items.length;
            items[prevIdx].classList.add('selected');
            items[prevIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex !== -1) {
                const sectionId = items[selectedIndex].getAttribute('data-section');
                navigateToSection(sectionId);
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('active');
            searchInput.blur();
        }
    });

    // Show dropdown again on focus if input has value
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim()) {
            dropdown.classList.add('active');
        }
    });

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Shortcut focus listener ( '/' or 'Ctrl+K' / 'Cmd+K' )
    document.addEventListener('keydown', (e) => {
        const isKbdShortcut = (e.key === '/' || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k'));
        if (isKbdShortcut && document.activeElement !== searchInput && 
            !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });
});
