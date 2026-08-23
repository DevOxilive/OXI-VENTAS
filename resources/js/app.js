import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = 'Super-Kay';
const themeStorageKey = 'color-theme';
const themeMetaColors = {
    dark: '#070304',
    light: '#e0000f',
};
let appliedTheme = null;
const pages = {
    './Pages/Dashboard.vue': () => import('./Pages/Dashboard.vue'),
    ...import.meta.glob('./Pages/**/*.vue'),
};

function runAfterFirstPaint(callback) {
    if (typeof window === 'undefined') return;

    const schedule = window.requestIdleCallback
        ? window.requestIdleCallback
        : (handler) => window.setTimeout(handler, 1200);

    if (document.readyState === 'complete') {
        schedule(callback);
        return;
    }

    window.addEventListener('load', () => schedule(callback), { once: true });
}

function applyTheme(theme) {
    if (typeof document === 'undefined') return;

    const isDark = theme === 'dark';
    const resolvedTheme = isDark ? 'dark' : 'light';
    const root = document.documentElement;
    const nextMetaColor = themeMetaColors[resolvedTheme];

    if (appliedTheme === resolvedTheme
        && root.classList.contains('dark') === isDark
        && document.querySelector('meta[name="theme-color"]')?.getAttribute('content') === nextMetaColor) {
        return;
    }

    appliedTheme = resolvedTheme;
    root.classList.toggle('dark', isDark);

    const themeColorMeta = document.querySelector('meta[name="theme-color"]');
    if (themeColorMeta && themeColorMeta.getAttribute('content') !== nextMetaColor) {
        themeColorMeta.setAttribute('content', nextMetaColor);
    }
}

if (typeof window !== 'undefined') {
    const storedTheme = window.localStorage.getItem(themeStorageKey);
    const resolvedTheme = storedTheme
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    applyTheme(resolvedTheme);

    window.addEventListener('oxi-theme-change', (event) => {
        applyTheme(event.detail?.theme === 'dark' ? 'dark' : 'light');
    });
}

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, pages),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

const pwaRoot = document.getElementById('pwa-install-root');

if (pwaRoot) {
    runAfterFirstPaint(async () => {
        const { default: PwaInstallPrompt } = await import('./Components/PwaInstallPrompt.vue');

        createApp({
            render: () => h(PwaInstallPrompt),
        }).mount(pwaRoot);
    });
}

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}
