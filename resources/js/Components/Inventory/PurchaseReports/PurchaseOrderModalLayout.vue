<script setup>
import GlobalModal from '@/Components/Modales/GlobalModal.vue'

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    mode: { type: String, default: 'view' },
    processing: { type: Boolean, default: false },
    summary: { type: Array, default: () => [] },
})

defineEmits(['close'])
</script>

<template>
    <GlobalModal
        :title="title"
        :subtitle="subtitle"
        :mode="mode"
        size="2xl"
        height="compact"
        scroll-mode="controlled"
        :show-footer="false"
        :show-save="false"
        :processing="processing"
        :close-on-backdrop="!processing"
        :close-on-esc="!processing"
        @close="$emit('close')"
    >
        <template #content>
            <div class="grid min-h-0 flex-1 gap-4 overflow-hidden p-4 md:grid-cols-[minmax(0,3fr)_minmax(190px,1fr)] md:p-5">
                <section class="min-h-0 overflow-y-auto overscroll-contain pr-1">
                    <slot name="products" />
                </section>

                <aside class="min-h-0 rounded-2xl border border-secondary bg-secondary p-4 text-text">
                    <p class="text-[11px] font-black uppercase tracking-[0.12em] opacity-55">Resumen</p>
                    <dl class="mt-3 divide-y divide-secondary text-sm">
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
