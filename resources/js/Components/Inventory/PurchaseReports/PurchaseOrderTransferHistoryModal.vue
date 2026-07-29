<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'

const props = defineProps({
    order: { type: Object, required: true },
})

defineEmits(['close'])

const transfers = computed(() => props.order.transfers ?? [])

function dateTime(value) {
    if (!value) return 'Sin fecha'

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}
</script>

<template>
    <GlobalModal
        :title="`Historial de ${order.folio}`"
        subtitle="Consulta quién transfirió la orden, el responsable anterior y el nuevo responsable."
        mode="view"
        size="lg"
        height="compact"
        scroll-mode="controlled"
        :show-footer="false"
        :show-save="false"
        @close="$emit('close')"
    >
        <template #content>
            <section class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5">
                <article
                    v-for="transfer in transfers"
                    :key="transfer.id"
                    class="rounded-xl border border-secondary bg-background p-4 text-text"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-black">
                                {{ transfer.from_user_name }} → {{ transfer.to_user_name }}
                            </p>
                            <p class="mt-1 text-xs opacity-65">
                                Transferida por {{ transfer.transferred_by_name }}
                            </p>
                        </div>
                        <time class="text-xs font-semibold opacity-60">
                            {{ dateTime(transfer.transferred_at) }}
                        </time>
                    </div>
                </article>

                <p v-if="!transfers.length" class="py-10 text-center text-sm text-text opacity-60">
                    Esta orden todavía no tiene transferencias registradas.
                </p>
            </section>
        </template>

        <template #footer>
            <footer class="flex w-full justify-end border-t border-secondary bg-background p-3">
                <AppButton variant="secondary" @click="$emit('close')">Cerrar</AppButton>
            </footer>
        </template>
    </GlobalModal>
</template>
