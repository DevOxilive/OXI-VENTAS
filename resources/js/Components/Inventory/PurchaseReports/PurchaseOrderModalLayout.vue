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
            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto bg-secondary/20 p-3 md:p-5 xl:grid xl:grid-cols-[minmax(0,1fr)_280px] xl:overflow-hidden">
                <section class="min-w-0 rounded-2xl border border-secondary bg-background p-3 md:p-4 xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain">
                    <div class="mb-4 flex items-center gap-2 border-b border-secondary pb-3 text-text">
                        <span class="material-symbols-outlined rounded-lg bg-secondary p-2 text-primary">inventory_2</span>
                        <h3 class="text-sm font-bold">Productos</h3>
                    </div>
                    <slot name="products" />
                </section>

                <aside class="order-first min-w-0 rounded-2xl border border-secondary bg-background p-4 text-text xl:order-none xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain">
                    <p class="border-b border-secondary pb-3 text-sm font-bold">Resumen</p>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm xl:grid-cols-1">
                        <div v-for="item in summary" :key="item.label" class="min-w-0 rounded-xl bg-secondary/50 p-3">
                            <dt class="text-xs font-semibold opacity-55">{{ item.label }}</dt>
                            <dd class="mt-1 break-words font-semibold">{{ item.value ?? 'Sin información' }}</dd>
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
