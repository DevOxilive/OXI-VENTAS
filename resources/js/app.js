import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PwaInstallPrompt from './Components/PwaInstallPrompt.vue';

const appName = 'Super-Kay';
const themeStorageKey = 'color-theme';
const pages = {
    './Pages/Dashboard.vue': () => import('./Pages/Dashboard.vue'),
    ...import.meta.glob('./Pages/**/*.vue'),
};

function applyTheme(theme) {
    if (typeof document === 'undefined') return;

    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);

    const themeColorMeta = document.querySelector('meta[name="theme-color"]');
    if (themeColorMeta) {
        themeColorMeta.setAttribute('content', isDark ? '#070304' : '#e0000f');
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
    createApp({
        render: () => h(PwaInstallPrompt),
    }).mount(pwaRoot);
}

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}
