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
            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 md:p-5 xl:grid xl:grid-cols-[minmax(0,3fr)_minmax(260px,1fr)] xl:overflow-hidden">
                <section class="min-w-0 xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain xl:pr-1">
                    <slot name="products" />
                </section>

                <aside class="order-first min-w-0 rounded-2xl border border-secondary bg-secondary p-4 text-text xl:order-none xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain">
                    <p class="text-[11px] font-black uppercase tracking-[0.12em] opacity-55">Resumen</p>
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 xl:block xl:divide-y xl:divide-secondary">
                        <div v-for="item in summary" :key="item.label" class="py-2 first:pt-0 last:pb-0">
                            <dt class="text-xs font-semibold opacity-55">{{ item.label }}</dt>
                            <dd class="mt-0.5 break-words font-bold">{{ item.value || 'Sin información' }}</dd>
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
