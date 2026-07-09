<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const storageKey = 'oxi-ventas-pwa-dismissed';
const deferredPrompt = ref(null);
const canPromptInstall = ref(false);
const isHidden = ref(false);
const showManualFallback = ref(false);
const showInstallHelp = ref(false);

const isStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
});

const isAppleMobile = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    const userAgent = window.navigator.userAgent || '';
    const platform = window.navigator.platform || '';
    const maxTouchPoints = window.navigator.maxTouchPoints || 0;

    return /iPad|iPhone|iPod/i.test(userAgent)
        || (platform === 'MacIntel' && maxTouchPoints > 1);
});

const isTouchTabletLike = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    const maxTouchPoints = window.navigator.maxTouchPoints || 0;
    return maxTouchPoints > 1;
});

const shouldShowBanner = computed(() => {
    if (isHidden.value || isStandalone.value) {
        return false;
    }

    return canPromptInstall.value || showManualFallback.value;
});

const installTitle = computed(() => {
    return canPromptInstall.value
        ? 'Instala OXI-VENTAS'
        : 'Agrega OXI-VENTAS a tu pantalla';
});

const installDescription = computed(() => {
    return canPromptInstall.value
        ? 'Abrelo como app, con acceso mas rapido y pantalla completa.'
        : 'En algunas tablets la instalacion se hace desde el menu del navegador.';
});

const primaryActionLabel = computed(() => {
    return canPromptInstall.value ? 'Instalar' : 'Ver pasos';
});

const manualSteps = computed(() => {
    if (isAppleMobile.value) {
        return [
            'Toca el boton Compartir del navegador.',
            'Busca la opcion Agregar a pantalla de inicio.',
            'Confirma el nombre OXI-VENTAS y guarda.',
        ];
    }

    return [
        'Abre el menu del navegador.',
        'Busca Instalar app o Agregar a pantalla de inicio.',
        'Confirma la instalacion para abrir OXI-VENTAS como app.',
    ];
});

const closeBanner = () => {
    isHidden.value = true;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, '1');
    }
};

const promptInstall = async () => {
    if (!deferredPrompt.value) {
        showInstallHelp.value = true;
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
let fallbackTimer = null;

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
        showManualFallback.value = false;
        showInstallHelp.value = false;
        isHidden.value = false;
    };

    installedHandler = () => {
        deferredPrompt.value = null;
        canPromptInstall.value = false;
        closeBanner();
    };

    window.addEventListener('beforeinstallprompt', beforeInstallHandler);
    window.addEventListener('appinstalled', installedHandler);

    fallbackTimer = window.setTimeout(() => {
        if (!canPromptInstall.value && isTouchTabletLike.value) {
            showManualFallback.value = true;
            isHidden.value = false;
        }
    }, 1600);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (fallbackTimer) {
        window.clearTimeout(fallbackTimer);
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
            v-if="shouldShowBanner"
            class="fixed bottom-4 left-4 right-4 z-[9999] mx-auto max-w-md rounded-2xl border border-emerald-100 bg-white/95 p-4 shadow-2xl shadow-emerald-950/10 backdrop-blur"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow">
                    <span class="material-symbols-outlined text-[22px]">install_mobile</span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ installTitle }}
                    </p>

                    <p class="mt-1 text-sm text-slate-600">
                        {{ installDescription }}
                    </p>

                    <div
                        v-if="showInstallHelp"
                        class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Pasos
                        </p>

                        <ol class="mt-2 space-y-1 text-sm text-slate-700">
                            <li
                                v-for="(step, index) in manualSteps"
                                :key="step"
                            >
                                {{ index + 1 }}. {{ step }}
                            </li>
                        </ol>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                            @click="promptInstall"
                        >
                            {{ primaryActionLabel }}
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
