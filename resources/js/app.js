import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const portalSidebarScrollKey = 'portal-sidebar-scroll';

if (document.body.classList.contains('portal-shell')) {
    const themeToggle = document.getElementById('portal-theme-toggle');
    const themeLabel = themeToggle?.querySelector('[data-theme-label]');

    const applyTheme = (theme) => {
        document.documentElement.dataset.portalTheme = theme;
        localStorage.setItem('portal-theme', theme);
        if (themeLabel) {
            themeLabel.textContent = theme === 'dark' ? 'Light' : 'Dark';
        }
    };

    applyTheme(localStorage.getItem('portal-theme') || document.documentElement.dataset.portalTheme || 'light');

    themeToggle?.addEventListener('click', () => {
        const current = document.documentElement.dataset.portalTheme === 'dark' ? 'dark' : 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });

    const sidebarNav = document.getElementById('portal-sidebar-nav');
    const savedSidebarY = sessionStorage.getItem(portalSidebarScrollKey);
    if (sidebarNav && savedSidebarY !== null) {
        requestAnimationFrame(() => {
            sidebarNav.scrollTop = Number(savedSidebarY);
        });
    }

    const saveScroll = () => {
        if (sidebarNav) {
            sessionStorage.setItem(portalSidebarScrollKey, String(sidebarNav.scrollTop));
        }
    };

    window.addEventListener('beforeunload', saveScroll);
    window.addEventListener('pagehide', saveScroll);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        const isSamePage = url.pathname === window.location.pathname && url.search === window.location.search;

        if (url.origin !== window.location.origin || link.target || link.hasAttribute('download') || isSamePage) {
            return;
        }

        saveScroll();

        const bar = document.getElementById('portal-loading-bar');
        if (bar) {
            bar.style.opacity = '1';
            bar.style.width = '72%';
        }
    });
}
