<script setup>
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { formatInventoryQuantity, normalizeInventoryUnit } from '@/utils/quantityFormatter'

defineProps({
    notes: {
        type: String,
        default: '',
    },
    selectedProducts: {
        type: Array,
        default: () => [],
    },
    selectedCount: {
        type: Number,
        default: 0,
    },
    totalQuantity: {
        type: Number,
        default: 0,
    },
    estimatedTotal: {
        type: Number,
        default: 0,
    },
})

const emit = defineEmits([
    'update-notes',
    'update-item',
    'remove',
    'save',
])

function updateQuantity(item, value) {
    emit('update-item', item.branch_product_id, 'requested_quantity', value === '' ? '' : Math.max(1, Number(value || 1)))
}

function decreaseQuantity(item) {
    const next = Number(item.requested_quantity || 0) - 1
    emit('update-item', item.branch_product_id, 'requested_quantity', Math.max(1, next))
}

function increaseQuantity(item) {
    emit('update-item', item.branch_product_id, 'requested_quantity', Number(item.requested_quantity || 0) + 1)
}

function itemUnit(item) {
    return item.inventory_unit ?? item.base_unit ?? item.unit ?? 'pza'
}

function quantity(value, item = null) {
    return formatInventoryQuantity(value, item ? itemUnit(item) : 'pza')
}

function allowsDecimalQuantity(item) {
    return normalizeInventoryUnit(itemUnit(item)) === 'kg'
}
</script>

<template>
    <aside class="rounded-2xl border border-secondary bg-background p-5 shadow-sm xl:sticky xl:top-6 xl:self-start">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-text">
                    Lista de compra
                </h2>
                <p class="text-sm text-text opacity-70">
                    {{ selectedCount }} productos seleccionados
                </p>
            </div>

            <span class="rounded-full bg-secondary px-3 py-1 text-xs font-bold text-primary">
                Borrador
            </span>
        </div>

        <div class="mt-4">
            <TextareaField
                :model-value="notes"
                label="Notas"
                field="notes"
                :rows="3"
                placeholder="Notas generales del reporte..."
                @update:model-value="$emit('update-notes', $event)"
            />
        </div>

        <div v-if="!selectedCount" class="mt-6 rounded-xl border border-dashed border-secondary bg-secondary p-6 text-center">
            <p class="text-sm font-medium text-text">
                Todavía no hay productos seleccionados.
            </p>
            <p class="mt-1 text-xs text-text opacity-70">
                Marca productos desde el inventario para agregarlos aquí.
            </p>
        </div>

        <div v-else class="mt-5 max-h-[460px] space-y-3 overflow-y-auto pr-1">
            <div v-for="item in selectedProducts" :key="item.branch_product_id"
                class="rounded-xl border border-secondary bg-background p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-text">
                            {{ item.name }}
                        </p>
                        <p class="text-xs text-text opacity-70">
                            {{ item.code || item.main_barcode || 'Sin código' }}
                        </p>
                        <p class="mt-1 text-xs text-text opacity-70">
                            Stock: {{ quantity(item.stock, item) }} · Mínimo: {{ quantity(item.min_stock, item) }}
                        </p>
                    </div>

                    <button type="button"
                        class="rounded-lg px-2 py-1 text-sm font-bold text-primary transition hover:bg-secondary"
                        @click="$emit('remove', item.branch_product_id)">
                        ×
                    </button>
                </div>

                <div class="mt-3">
                    <QuantityStepper
                        :value="item.requested_quantity"
                        :aria-label="`Cantidad solicitada de ${item.name}`"
                        :allow-decimal="allowsDecimalQuantity(item)"
                        :decrease-disabled="Number(item.requested_quantity || 0) <= 1"
                        @decrease="decreaseQuantity(item)"
                        @increase="increaseQuantity(item)"
                        @update="updateQuantity(item, $event)"
                    />
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-xl bg-secondary p-4">
            <div class="flex justify-between text-sm">
                <span class="text-text opacity-70">Cantidad total</span>
                <span class="font-semibold text-text">
                    {{ quantity(totalQuantity, selectedProducts.find((item) => allowsDecimalQuantity(item)) || null) }}
                </span>
            </div>

            <div class="mt-2 flex justify-between text-sm">
                <span class="text-text opacity-70">Estimado</span>
                <span class="font-semibold text-text">
                    ${{ Number(estimatedTotal).toFixed(2) }}
                </span>
            </div>
        </div>

        <button type="button" :disabled="!selectedCount"
            class="mt-5 w-full rounded-xl border border-primary bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40"
            @click="$emit('save')">
            Guardar borrador
        </button>
    </aside>
</template>
