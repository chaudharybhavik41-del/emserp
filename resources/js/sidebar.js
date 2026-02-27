/**
 * Sidebar UX helpers
 * - Click-only accordion (Bootstrap collapse)
 * - Search/filter menu items
 * - Persist last opened section (per browser)
 */

function normalize(text) {
  return (text || '').toString().trim().toLowerCase();
}

function initOneSidebar(sidebarEl) {
  const searchInput = sidebarEl.querySelector('.erp-sidebar-search');
  const clearBtn = sidebarEl.querySelector('.erp-sidebar-search-clear');
  const toggles = Array.from(sidebarEl.querySelectorAll('[data-erp-section-toggle]'));
  const items = Array.from(sidebarEl.querySelectorAll('[data-erp-menu-item]'));

  const storageKey = 'erp.sidebar.lastSection';

  // Persist section on click
  toggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-erp-section-key');
      if (key) {
        try {
          localStorage.setItem(storageKey, key);
        } catch (_) {}
      }
    });
  });

  // Restore last section (only if no section is already open due to route-active state)
  window.addEventListener('load', () => {
    const anyOpen = sidebarEl.querySelector('.collapse.show');
    if (anyOpen) return;

    let last = null;
    try {
      last = localStorage.getItem(storageKey);
    } catch (_) {}
    if (!last) return;

    const btn = sidebarEl.querySelector(`[data-erp-section-key="${CSS.escape(last)}"]`);
    if (!btn) return;

    const target = btn.getAttribute('data-bs-target');
    if (!target) return;

    const collapseEl = sidebarEl.querySelector(target);
    if (!collapseEl) return;

    // Ensure bootstrap is loaded (it is via CDN, deferred)
    const Collapse = window.bootstrap?.Collapse;
    if (!Collapse) return;
    Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
  });

  if (!searchInput) return;

  const applyFilter = () => {
    const q = normalize(searchInput.value);

    // 1) Toggle individual menu items
    items.forEach((a) => {
      const hay = normalize(a.getAttribute('data-menu-text') || a.textContent);
      const li = a.closest('li') || a;
      const match = !q || hay.includes(q);
      li.classList.toggle('d-none', !match);
    });

    // 2) Expand sections that contain matches (during search), collapse those without
    const Collapse = window.bootstrap?.Collapse;
    if (!Collapse) return;

    toggles.forEach((btn) => {
      const target = btn.getAttribute('data-bs-target');
      if (!target) return;
      const collapseEl = sidebarEl.querySelector(target);
      if (!collapseEl) return;

      if (!q) {
        // Don't force-close on empty query; let route-active state + user choices win.
        return;
      }

      const hasVisible = !!collapseEl.querySelector('li:not(.d-none)');
      const inst = Collapse.getOrCreateInstance(collapseEl, { toggle: false });
      if (hasVisible) inst.show();
      else inst.hide();
    });
  };

  searchInput.addEventListener('input', applyFilter);

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      searchInput.value = '';
      searchInput.focus();
      applyFilter();
    });
  }
}

export function initSidebar() {
  document.querySelectorAll('.erp-sidebar').forEach(initOneSidebar);
}

// Auto-init (safe to call multiple times)
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSidebar);
} else {
  initSidebar();
}
