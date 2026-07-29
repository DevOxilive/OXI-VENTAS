<script setup>
import { ref, watch } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    physicalCount: {
        type: Object,
        default: null,
    },
    processing: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'confirm'])
const selectedScope = ref('all')

watch(() => props.show, (show) => {
    if (show) selectedScope.value = 'all'
})
</script>

<template>
    <GlobalModal
        v-if="show"
        title="Reabrir auditoría"
        :subtitle="physicalCount?.folio ?? physicalCount?.name ?? ''"
        mode="update"
        size="sm"
        height="auto"
        :columns="1"
        :processing="processing"
        save-button-text="Reabrir auditoría"
        close-button-text="Cancelar"
        content-class="space-y-4"
        @save="emit('confirm', selectedScope)"
        @close="emit('close')"
    >
        <div>
            <h3 class="text-base font-semibold text-text">
                Productos de la nueva ronda
            </h3>
            <p class="mt-1 text-sm text-text opacity-65">
                Define el alcance del conteo antes de reactivar la captura.
            </p>
        </div>

        <div class="grid gap-3">
            <SelectionCheckboxCard
                class="w-full"
                variant="soft"
                :checked="selectedScope === 'all'"
                title="Todos los productos"
                description="Permite volver a contar el inventario completo de la sucursal."
                @toggle="selectedScope = 'all'"
            />

            <SelectionCheckboxCard
                class="w-full"
                variant="soft"
                :checked="selectedScope === 'zero_stock'"
                title="Solo productos sin existencias"
                description="Limita la búsqueda y la captura a productos con stock en cero."
                @toggle="selectedScope = 'zero_stock'"
            />
        </div>
    </GlobalModal>
</template>
