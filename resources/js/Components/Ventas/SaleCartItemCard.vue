<script setup>
import InputField from "@/Components/Forms/InputField.vue"
import QuantityStepper from "@/Components/Forms/QuantityStepper.vue"
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue"

defineProps({
  item: {
    type: Object,
    required: true,
  },
  formatMoney: {
    type: Function,
    required: true,
  },
  cartItemUnitPrice: {
    type: Function,
    required: true,
  },
  cartItemSubtotal: {
    type: Function,
    required: true,
  },
  cartItemDiscountAmount: {
    type: Function,
    required: true,
  },
})

defineEmits(["increase", "decrease", "remove", "toggle-discount", "normalize-discount", "set-presentation", "update-quantity"])
</script>

<template>
  <article class="px-1 py-3">
    <div class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_120px_120px_140px_88px] md:items-start">
      <div class="min-w-0">
        <div class="flex gap-3">
          <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-secondary bg-background">
            <img
              v-if="item.image"
              :src="item.image"
              :alt="item.name"
              class="h-full w-full object-contain"
            />
            <div v-else class="flex h-full items-center justify-center">
              <span class="material-symbols-outlined text-2xl text-text opacity-35">
                inventory_2
              </span>
            </div>
          </div>

          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-text">
              {{ item.name }}
            </p>
            <p class="mt-0.5 truncate text-xs text-text opacity-70">
              {{ item.barcode || "Sin codigo" }} · Stock: {{ Number(item.available_quantity ?? item.stock).toFixed(3) }} {{ item.presentation === 'box' ? 'cajas' : item.inventory_unit }}
            </p>
            <div
              v-if="item.has_box_presentation"
              class="mt-2 inline-flex rounded-lg border border-secondary bg-background p-0.5"
            >
              <button
                type="button"
                class="rounded-md px-2 py-1 text-xs font-semibold"
                :class="item.presentation === 'piece' ? 'bg-primary text-white' : 'text-text'"
                @click="$emit('set-presentation', 'piece')"
              >
                Piezas
              </button>
              <button
                type="button"
                class="rounded-md px-2 py-1 text-xs font-semibold"
                :class="item.presentation === 'box' ? 'bg-primary text-white' : 'text-text'"
                @click="$emit('set-presentation', 'box')"
              >
                Cajas
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-background px-3 py-2 md:bg-transparent md:px-0 md:py-0">
        <p class="text-[11px] uppercase tracking-[0.14em] text-text opacity-50 md:hidden">
          Precio
        </p>
        <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm font-semibold text-text md:mt-0">
          <del v-if="item.discount_enabled && Number(item.discount_percentage || 0) > 0" class="text-xs font-normal opacity-50" title="Precio antes del descuento">
            {{ formatMoney(item.original_price || item.price || 0) }}
          </del>
          <span title="Precio final">{{ formatMoney(cartItemUnitPrice(item)) }}</span>
        </p>
      </div>

      <div class="rounded-lg bg-background px-3 py-2 md:bg-transparent md:px-0 md:py-0">
        <p class="text-[11px] uppercase tracking-[0.14em] text-text opacity-50 md:hidden">
          Cantidad
        </p>
        <QuantityStepper
          :value="item.quantity"
          :allow-decimal="item.presentation === 'piece' && item.inventory_unit === 'kg'"
          :max-integer-digits="3"
          :max-decimal-digits="3"
          :decrease-disabled="Number(item.quantity) <= 0"
          @decrease="$emit('decrease')"
          @increase="$emit('increase')"
          @update="$emit('update-quantity', $event)"
        />
      </div>

      <div class="rounded-lg bg-background px-3 py-2 md:bg-transparent md:px-0 md:py-0">
        <p class="text-[11px] uppercase tracking-[0.14em] text-text opacity-50 md:hidden">
          Importe
        </p>
        <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-base font-bold text-text md:mt-0">
          <del v-if="item.discount_enabled && cartItemDiscountAmount(item) > 0" class="text-xs font-normal opacity-50" title="Importe antes del descuento">
            {{ formatMoney(Number(item.original_price || item.price || 0) * Number(item.quantity || 0)) }}
          </del>
          <span title="Importe final">{{ formatMoney(cartItemSubtotal(item)) }}</span>
        </p>
      </div>

      <div class="flex items-start justify-end gap-2">
        <ActionIconButton
          icon="percent"
          variant="amber"
          class="shrink-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
          :class="item.discount_enabled ? 'ring-1 ring-primary' : ''"
          title="Aplicar descuento"
          :aria-label="`Descuento para ${item.name}`"
          :aria-pressed="Boolean(item.discount_enabled)"
          @click="$emit('toggle-discount')"
        />
        <ActionIconButton
          icon="delete"
          variant="red"
          class="shrink-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
          title="Quitar producto"
          :aria-label="`Quitar ${item.name}`"
          @click="$emit('remove')"
        />
      </div>
    </div>

    <div
      v-if="item.discount_enabled"
      class="mt-2 flex items-center justify-end gap-2"
    >
        <label :for="`item_discount_percentage_${item.branch_product_id}`" class="text-xs text-text opacity-60">Descuento</label>
        <InputField
          v-model="item.discount_percentage"
          label="Porcentaje"
          :field="`item_discount_percentage_${item.branch_product_id}`"
          hide-label
          :show-counter="false"
          :aria-label="`Porcentaje de descuento para ${item.name}`"
          class="discount-input w-24"
          type="number"
          min="0"
          max="100"
          step="0.01"
          placeholder="0"
          suffix="%"
          @validate="$emit('normalize-discount')"
        />

    </div>
  </article>
</template>

<style scoped>
.discount-input :deep(input) {
  height: 2rem;
  border-radius: 0.5rem;
  padding: 0.25rem 1.75rem 0.25rem 0.5rem;
  font-size: 0.75rem;
}
</style>
