<script setup>
import GlobalModal from '@/Components/Modales/GlobalModal.vue'

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    mode: { type: String, default: 'view' },
    processing: { type: Boolean, default: false },
    summary: { type: Array, default: () => [] },
    size: { type: String, default: 'full' },
    height: { type: String, default: 'full' },
})

defineEmits(['close'])
</script>

<template>
    <GlobalModal
        :title="title"
        :subtitle="subtitle"
        :mode="mode"
        :size="size"
        :height="height"
        scroll-mode="controlled"
        :show-footer="false"
        :show-save="false"
        :processing="processing"
        :close-on-backdrop="!processing"
        :close-on-esc="!processing"
        @close="$emit('close')"
    >
        <template #content>
            <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto bg-secondary/20 p-3 md:p-4 lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:overflow-hidden">
                <section class="min-w-0 rounded-2xl border border-secondary bg-background p-3 lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain">
                    <div class="mb-3 flex items-center gap-2 border-b border-secondary pb-2 text-text">
                        <span class="material-symbols-outlined rounded-lg bg-secondary p-1.5 text-primary">inventory_2</span>
                        <h3 class="text-sm font-bold">Productos solicitados</h3>
                    </div>
                    <slot name="products" />
                </section>

                <aside class="order-first min-w-0 rounded-2xl border border-secondary bg-background p-3 text-text lg:order-none lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain">
                    <p class="border-b border-secondary pb-2 text-sm font-bold">Resumen</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2">
                        <div v-for="item in summary" :key="item.label" class="min-w-0 rounded-lg bg-secondary/50 px-2.5 py-2">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide opacity-55">{{ item.label }}</dt>
                            <dd class="mt-1 break-words text-sm font-bold leading-snug">{{ item.value ?? 'Sin información' }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </template>

        <template #footer>
            <slot name="footer" />
        </template>
    </GlobalModal>
</template>
