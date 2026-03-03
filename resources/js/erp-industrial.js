/**
 * ERP Industrial UX Enhancements (pre-PWA baseline)
 * - Auto-wrap loose tables in responsive containers
 * - Show left/right overflow cues on horizontally scrollable tables
 */

function syncTableOverflow(wrapper) {
    if (!wrapper) return;

    const hasOverflow = wrapper.scrollWidth - wrapper.clientWidth > 1;
    const left = wrapper.scrollLeft > 1;
    const right = wrapper.scrollLeft + wrapper.clientWidth < wrapper.scrollWidth - 1;

    wrapper.classList.toggle('is-overflowing', hasOverflow);
    wrapper.classList.toggle('show-left', hasOverflow && left);
    wrapper.classList.toggle('show-right', hasOverflow && right);
}

function ensureResponsiveTableWrappers(root = document) {
    const looseTables = root.querySelectorAll('.erp-main-card table');

    looseTables.forEach((table) => {
        if (table.closest('.table-responsive')) return;

        const wrap = document.createElement('div');
        wrap.className = 'table-responsive erp-auto-table-wrap';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });
}

function bindOverflowIndicators(root = document) {
    const wrappers = root.querySelectorAll('.table-responsive');

    wrappers.forEach((wrapper) => {
        if (wrapper.dataset.overflowBound === '1') {
            syncTableOverflow(wrapper);
            return;
        }

        wrapper.dataset.overflowBound = '1';

        const sync = () => syncTableOverflow(wrapper);
        wrapper.addEventListener('scroll', sync, { passive: true });

        if (window.ResizeObserver) {
            const observer = new ResizeObserver(sync);
            observer.observe(wrapper);
            const table = wrapper.querySelector('table');
            if (table) observer.observe(table);
        }

        requestAnimationFrame(sync);
    });
}

export function initErpIndustrialUx(root = document) {
    ensureResponsiveTableWrappers(root);
    bindOverflowIndicators(root);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initErpIndustrialUx(document));
} else {
    initErpIndustrialUx(document);
}

