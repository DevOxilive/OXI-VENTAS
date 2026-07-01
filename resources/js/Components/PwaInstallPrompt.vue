<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const storageKey = 'oxi-ventas-pwa-dismissed';
const deferredPrompt = ref(null);
const canPromptInstall = ref(false);
const isHidden = ref(false);

const isStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
});

const closeBanner = () => {
    isHidden.value = true;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, '1');
    }
};

const promptInstall = async () => {
    if (!deferredPrompt.value) {
        return;
    }

    deferredPrompt.value.prompt();

    const choiceResult = await deferredPrompt.value.userChoice;

    if (choiceResult?.outcome === 'accepted') {
        closeBanner();
    }

    deferredPrompt.value = null;
    canPromptInstall.value = false;
};

let beforeInstallHandler = null;
let installedHandler = null;

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.localStorage.getItem(storageKey) === '1' || isStandalone.value) {
        isHidden.value = true;
        return;
    }

    beforeInstallHandler = (event) => {
        event.preventDefault();
        deferredPrompt.value = event;
        canPromptInstall.value = true;
        isHidden.value = false;
    };

    installedHandler = () => {
        deferredPrompt.value = null;
        canPromptInstall.value = false;
        closeBanner();
    };

    window.addEventListener('beforeinstallprompt', beforeInstallHandler);
    window.addEventListener('appinstalled', installedHandler);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (beforeInstallHandler) {
        window.removeEventListener('beforeinstallprompt', beforeInstallHandler);
    }

    if (installedHandler) {
        window.removeEventListener('appinstalled', installedHandler);
    }
});
</script>

<template>
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-4"
    >
        <div
            v-if="!isHidden && canPromptInstall"
            class="fixed bottom-4 left-4 right-4 z-[9999] mx-auto max-w-md rounded-2xl border border-emerald-100 bg-white/95 p-4 shadow-2xl shadow-emerald-950/10 backdrop-blur"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow">
                    <span class="material-symbols-outlined text-[22px]">install_mobile</span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">
                        Instala OXI-VENTAS
                    </p>

                    <p class="mt-1 text-sm text-slate-600">
                        Abrelo como app, con acceso mas rapido y pantalla completa.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                            @click="promptInstall"
                        >
                            Instalar
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                            @click="closeBanner"
                        >
                            Luego
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>
