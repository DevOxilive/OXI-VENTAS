import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

if (typeof window !== 'undefined' && window.__OXIVENTAS_REALTIME__) {
    const initializeWhenIdle = () => {
        const schedule = window.requestIdleCallback
            ? window.requestIdleCallback
            : (handler) => window.setTimeout(handler, 1200);

        schedule(async () => {
            const { initializeRealtime } = await import('./realtime');

            initializeRealtime();
        });
    };

    if (document.readyState === 'complete') {
        initializeWhenIdle();
    } else {
        window.addEventListener('load', initializeWhenIdle, { once: true });
    }
}
