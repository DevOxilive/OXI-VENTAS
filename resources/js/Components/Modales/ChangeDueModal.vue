<script setup>
import { computed } from 'vue'
import GlobalModal from './GlobalModal.vue'

const props = defineProps({
    amount: {
        type: Number,
        default: 0,
    },
    subtitle: {
        type: String,
        default: 'Operación registrada correctamente',
    },
    closeButtonText: {
        type: String,
        default: 'Entendido',
    },
})

defineEmits(['close'])

const formattedAmount = computed(() => new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
}).format(Number(props.amount || 0)))
</script>

<template>
    <GlobalModal
        title="Cambio a devolver"
        :subtitle="subtitle"
        size="lg"
        height="compact"
        :columns="1"
        :show-save="false"
        panel-class="border-primary"
        @close="$emit('close')"
    >
        <template #header="{ close }">
            <header class="sticky top-0 z-10 border-b border-primary/30 bg-background px-5 py-4 md:px-8 md:py-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-text md:text-2xl">Cambio a devolver</h2>
                        <p class="mt-1 text-xs text-text opacity-70 md:text-sm">{{ subtitle }}</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-4xl leading-none text-text opacity-70 transition hover:bg-secondary hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        aria-label="Cerrar aviso de cambio"
                        title="Cerrar modal"
                        @click="close"
                    >
                        ×
                    </button>
                </div>
            </header>
        </template>

        <div class="flex min-h-64 flex-col items-center justify-center rounded-2xl border border-primary bg-secondary px-6 py-10 text-center">
            <span class="material-symbols-outlined mb-3 text-6xl text-primary">payments</span>
            <p class="text-sm font-black uppercase tracking-[0.2em] text-text opacity-65">Entrega al cliente</p>
            <p class="mt-3 text-6xl font-black leading-none text-primary sm:text-7xl">{{ formattedAmount }}</p>
            <p class="mt-5 max-w-md text-base font-semibold text-text opacity-75">Confirma este importe antes de atender la siguiente venta.</p>
        </div>

        <template #footer="{ close }">
            <footer class="sticky bottom-0 flex justify-end border-t border-primary/30 bg-background p-4 md:px-8 md:py-4">
                <button
                    type="button"
                    class="rounded-full border border-primary/40 bg-secondary px-8 py-3 text-sm font-semibold text-text transition hover:border-primary hover:bg-primary hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    data-modal-autofocus
                    @click="close"
                >
                    {{ closeButtonText }}
                </button>
            </footer>
        </template>
    </GlobalModal>
</template>
