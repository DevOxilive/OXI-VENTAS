<script setup>
import InputField from "@/Components/Forms/InputField.vue"
import QuantityStepper from "@/Components/Forms/QuantityStepper.vue"
import MetricCard from "@/Components/Cards/MetricCard.vue"

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
  <article class="rounded-xl border border-secondary bg-secondary p-3">
    <div class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_120px_120px_140px_48px] md:items-start">
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
        <p class="mt-1 text-sm font-semibold text-text md:mt-0">
          {{ formatMoney(cartItemUnitPrice(item)) }}
        </p>
        <p
          v-if="item.discount_enabled && Number(item.discount_percentage || 0) > 0"
          class="mt-1 text-xs text-primary"
        >
          Rebaja {{ Number(item.discount_percentage || 0).toFixed(0) }}%
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
        <p class="mt-1 text-base font-bold text-text md:mt-0">
          {{ formatMoney(cartItemSubtotal(item)) }}
        </p>
        <p
          v-if="item.discount_enabled && cartItemDiscountAmount(item) > 0"
          class="mt-1 text-xs text-primary"
        >
          -{{ formatMoney(cartItemDiscountAmount(item)) }}
        </p>
      </div>

      <div class="flex items-start justify-end gap-2">
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-lg border border-secondary bg-background text-text transition hover:border-primary"
          :class="item.discount_enabled ? 'border-primary text-primary' : ''"
          title="Aplicar descuento"
          @click="$emit('toggle-discount')"
        >
          <span class="material-symbols-outlined text-[18px]">
            sell
          </span>
        </button>
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-lg bg-background text-text transition hover:bg-secondary"
          title="Quitar producto"
          @click="$emit('remove')"
        >
          <span class="material-symbols-outlined text-[18px]">
            close
          </span>
        </button>
      </div>
    </div>

    <div
      v-if="item.discount_enabled"
      class="mt-3 grid gap-3 rounded-xl border border-secondary bg-background p-3 lg:grid-cols-[180px_minmax(0,1fr)]"
    >
        <InputField
          v-model="item.discount_percentage"
          label="Porcentaje"
          field="item_discount_percentage"
          type="number"
          placeholder="0"
          suffix="%"
          @validate="$emit('normalize-discount')"
        />

        <div class="grid grid-cols-2 gap-3 text-sm">
          <MetricCard
            label="Precio original"
            :value="formatMoney(item.original_price)"
            size="sm"
          />

          <MetricCard
            label="Descuento"
            :value="`-${formatMoney(cartItemDiscountAmount(item))}`"
            tone="danger"
            size="sm"
          />
        </div>
    </div>
  </article>
</template>
