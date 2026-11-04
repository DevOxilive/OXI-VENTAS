<script setup>
import { computed } from 'vue'

import AppButton from '@/Components/Buttons/AppButton.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'

const props = defineProps({
    remainingSeconds: {
        type: Number,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    warningSeconds: {
        type: Number,
        default: 300,
    },
})

defineEmits(['continue', 'logout'])

const countdown = computed(() => {
    const seconds = Math.max(0, Math.ceil(props.remainingSeconds))
    const minutes = Math.floor(seconds / 60)

    return `${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`
})

const remainingProgress = computed(() => {
    if (props.warningSeconds <= 0) return 0

    return Math.max(0, Math.min(100, (props.remainingSeconds / props.warningSeconds) * 100))
})

const countdownRingStyle = computed(() => ({
    background: `conic-gradient(var(--primary) ${remainingProgress.value}%, var(--secondary) ${remainingProgress.value}% 100%)`,
}))
</script>

<template>
    <GlobalModal
        title="¿Continuar sesión?"
        size="sm"
        height="auto"
        backdrop="soft"
        :show-header="false"
        :show-footer="false"
        :close-on-backdrop="false"
        :close-on-esc="false"
        :restore-focus="false"
        auto-focus-selector="[data-session-continue]"
        panel-class="overflow-hidden rounded-3xl"
    >
        <template #content>
            <section class="space-y-5 p-5 text-center sm:p-7">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-primary bg-secondary text-primary shadow-sm">
                    <span class="material-symbols-outlined text-[34px]" aria-hidden="true">
                        timer
                    </span>
                </div>

                <div>
                    <p class="text-xl font-bold tracking-tight text-text sm:text-2xl">
                        ¿Continuar sesión?
                    </p>

                    <p class="mt-2 text-sm leading-6 text-text opacity-70">
                        Por seguridad la sesión se cerrara en
                    </p>
                </div>

                <div
                    class="relative mx-auto grid h-36 w-36 place-items-center rounded-full p-[5px] shadow-sm transition-[background] duration-1000"
                    :style="countdownRingStyle"
                >
                    <div class="grid h-full w-full place-items-center rounded-full border border-secondary bg-background px-3 text-center">
                        <div>
                            <p class="text-3xl font-black tabular-nums tracking-[0.1em] text-text sm:text-4xl">
                                {{ countdown }}
                            </p>
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-text opacity-55">
                                Tiempo restante
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <template #footer>
            <footer class="flex flex-col-reverse gap-3 border-t border-secondary bg-background p-4 sm:flex-row sm:justify-end sm:px-6">
                <AppButton
                    variant="secondary"
                    :disabled="processing"
                    @click="$emit('logout')"
                >
                    Cerrar sesión
                </AppButton>

                <AppButton
                    data-session-continue
                    variant="primary"
                    :disabled="processing"
                    @click="$emit('continue')"
                >
                    {{ processing ? 'Continuando...' : 'Continuar sesión' }}
                </AppButton>
            </footer>
        </template>
    </GlobalModal>
</template>
