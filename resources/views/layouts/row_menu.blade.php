{{--
    Row action menu: in any table on any page, when the last cell of a row holds two or more
    buttons, they are moved into a "⋮" dropdown. The original buttons are MOVED (not copied),
    so their click handlers, data attributes and modal toggles keep working. Rows that pages
    add later with JavaScript are handled too (MutationObserver). When the actions sit in separate
    cells at the end of the row (e.g. Edit | Delete under a colspan="2" header), those cells are
    merged into the last one first.

    Opt out for one table or cell: add data-row-menu="off" to it.
--}}
<style>
    .row-menu-toggle { background: transparent; border: 0; padding: .25rem .5rem; line-height: 1; color: #334155; }
    .row-menu-toggle:hover, .row-menu-toggle:focus, .row-menu.show .row-menu-toggle { background: #f1f5f9; border-radius: 8px; }
    .row-menu .dropdown-menu { min-width: 11rem; padding: .35rem 0; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 12px 28px rgba(15, 23, 42, .14); z-index: 1080; }
    .theme-v2 .row-menu .dropdown-menu > li > .dropdown-item,
    .row-menu .dropdown-menu > li > .dropdown-item {
        display: flex !important; align-items: center; gap: .55rem; width: 100% !important; min-width: 0 !important; height: auto !important;
        margin: 0 !important; padding: .45rem 1rem !important; border: 0 !important; border-radius: 0 !important;
        background: transparent !important; box-shadow: none !important; color: #1e293b !important;
        font-size: .875rem !important; font-weight: 500 !important; text-align: left !important; white-space: nowrap; justify-content: flex-start !important;
    }
    .row-menu .dropdown-menu > li > .dropdown-item:hover,
    .row-menu .dropdown-menu > li > .dropdown-item:focus { background: #f1f5f9 !important; }
    .row-menu .dropdown-menu > li > .dropdown-item.row-menu-danger { color: #dc2626 !important; }
    .row-menu .dropdown-menu > li > .dropdown-item:disabled,
    .row-menu .dropdown-menu > li > .dropdown-item.disabled { color: #94a3b8 !important; pointer-events: none; }
    .row-menu .dropdown-menu > li > .dropdown-item i { width: 1rem; text-align: center; }
    /* A button the page hides (d-none / hidden / display:none) hides its menu line too */
    .row-menu .dropdown-menu > li:has(> .d-none),
    .row-menu .dropdown-menu > li:has(> [hidden]),
    .row-menu .dropdown-menu > li:has(> [style*="display: none"]),
    .row-menu .dropdown-menu > li:has(> [style*="display:none"]) { display: none; }
</style>
<script>
    (function () {
        const ACTION_SELECTOR = 'button, a.btn';
        const ICON_LABELS = [
            [/bi-(pencil|pen)\b|bi-pencil-/, 'Edit'],
            [/bi-trash/, 'Delete'],
            [/bi-eye\b|bi-eye-fill/, 'View'],
            [/bi-printer/, 'Print'],
            [/bi-(download|file-earmark-arrow-down)/, 'Download'],
            [/bi-(file-earmark-pdf|filetype-pdf)/, 'PDF'],
            [/bi-check/, 'Approve'],
            [/bi-x\b|bi-x-lg|bi-x-circle|bi-x-square/, 'Remove'],
            [/bi-arrow-up\b/, 'Move up'],
            [/bi-arrow-down\b/, 'Move down'],
            [/bi-arrow-(repeat|clockwise|counterclockwise)/, 'Refresh'],
            [/bi-eraser/, 'Clear'],
            [/bi-(lock)\b|bi-lock-fill/, 'Lock'],
            [/bi-unlock/, 'Unlock'],
            [/bi-(files|copy|clipboard)/, 'Copy'],
            [/bi-(plus|plus-lg|plus-circle)/, 'Add'],
            [/bi-gear/, 'Settings'],
            [/bi-(paperclip|upload)/, 'Attachments'],
            [/bi-send/, 'Send'],
        ];

        function labelFor(button) {
            const title = button.getAttribute('title') || button.getAttribute('aria-label')
                || button.getAttribute('data-bs-original-title') || button.getAttribute('data-bs-title');
            if (title && title.trim()) return title.trim();
            const icon = button.querySelector('i[class*="bi-"]');
            const iconClass = icon ? icon.className : '';
            for (const [pattern, label] of ICON_LABELS) {
                if (pattern.test(iconClass)) return label;
            }
            return 'Action';
        }

        function isDanger(button) {
            const cls = button.className + ' ' + (button.querySelector('i') ? button.querySelector('i').className : '');
            return /danger|delete|remove|bi-trash/.test(cls);
        }

        // Only the containers the buttons were taken out of, and only if nothing else is left in them.
        function removeEmptyWrappers(cell, parents) {
            parents.forEach(el => {
                let node = el;
                while (node && node !== cell && !node.children.length && !node.textContent.trim() && !node.id) {
                    const parent = node.parentElement;
                    node.remove();
                    node = parent;
                }
            });
        }

        // A cell that holds nothing but action buttons (no other text, no form fields).
        function isButtonOnlyCell(cell) {
            if (!cell || cell.tagName !== 'TD') return false;
            if (cell.querySelector('.dropdown, .dropstart, .dropend, .dropup, input, select, textarea')) return false;
            const buttons = cell.querySelectorAll(ACTION_SELECTOR);
            if (!buttons.length) return false;
            let text = cell.textContent;
            buttons.forEach(b => { text = text.replace(b.textContent, ''); });
            return !text.trim();
        }

        // Some tables put each action in its own cell (Edit | Delete under one colspan header).
        // Pull the buttons of those neighbouring button-only cells into the last cell, and widen
        // the last cell by their colspan so the columns still line up with the header.
        function absorbButtonCells(cell) {
            if (!isButtonOnlyCell(cell)) return;
            let prev = cell.previousElementSibling;
            while (isButtonOnlyCell(prev) && !prev.closest('[data-row-menu="off"]')) {
                const before = prev.previousElementSibling;
                Array.from(prev.childNodes).reverse().forEach(node => cell.insertBefore(node, cell.firstChild));
                cell.colSpan = cell.colSpan + prev.colSpan;
                prev.remove();
                prev = before;
            }
        }

        function convertCell(cell) {
            if (cell.dataset.rowMenu === 'done' || cell.closest('[data-row-menu="off"]')) return;
            if (cell.querySelector('.dropdown, .dropstart, .dropend, .dropup, input, select, textarea')) return;

            if (cell.querySelectorAll(ACTION_SELECTOR).length === 1) absorbButtonCells(cell);
            const buttons = Array.from(cell.querySelectorAll(ACTION_SELECTOR));
            if (buttons.length < 2) return;
            cell.dataset.rowMenu = 'done';

            const wrapper = document.createElement('div');
            wrapper.className = 'dropdown row-menu d-inline-block';
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'row-menu-toggle';
            toggle.setAttribute('data-bs-toggle', 'dropdown');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Actions');
            toggle.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
            const menu = document.createElement('ul');
            menu.className = 'dropdown-menu dropdown-menu-end';

            const parents = new Set(buttons.map(b => b.parentElement).filter(p => p && p !== cell));
            buttons.forEach(button => {
                const hasText = Array.from(button.childNodes).some(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim())
                    || Array.from(button.children).some(c => c.tagName !== 'I' && c.textContent.trim());
                if (!hasText) {
                    const text = document.createElement('span');
                    text.textContent = labelFor(button);
                    button.appendChild(text);
                }
                button.classList.add('dropdown-item');
                if (isDanger(button)) button.classList.add('row-menu-danger');
                const item = document.createElement('li');
                item.appendChild(button);
                menu.appendChild(item);
            });

            wrapper.appendChild(toggle);
            wrapper.appendChild(menu);
            removeEmptyWrappers(cell, parents);
            cell.appendChild(wrapper);

            // Created up front so Bootstrap's click handler reuses this instance. Fixed positioning
            // keeps the menu from being cut off by .table-responsive scroll boxes.
            bootstrap.Dropdown.getOrCreateInstance(toggle, {
                popperConfig: config => Object.assign({}, config, {strategy: 'fixed'}),
            });
        }

        function scan(root) {
            (root || document).querySelectorAll('table tr > td:last-child').forEach(convertCell);
        }

        let queued = false;
        function queueScan() {
            if (queued) return;
            queued = true;
            requestAnimationFrame(() => { queued = false; scan(document); });
        }

        document.addEventListener('DOMContentLoaded', () => {
            scan(document);
            new MutationObserver(mutations => {
                if (mutations.some(m => m.addedNodes.length)) queueScan();
            }).observe(document.body, {childList: true, subtree: true});
        });
    })();
</script>
