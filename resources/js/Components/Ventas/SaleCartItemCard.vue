<script setup>
import InputField from "@/Components/Forms/InputField.vue"
import QuantityStepper from "@/Components/Forms/QuantityStepper.vue"
import ToggleSwitch from "@/Components/Forms/ToggleSwitch.vue"
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

defineEmits(["increase", "decrease", "remove", "toggle-discount", "normalize-discount"])
</script>

<template>
  <article class="rounded-2xl border border-secondary bg-secondary p-4">
    <div class="grid gap-4 md:grid-cols-[minmax(0,1.4fr)_120px_120px_140px_56px] md:items-start">
      <div class="min-w-0">
        <div class="flex gap-3">
          <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-secondary bg-background">
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
            <p class="mt-1 truncate text-xs text-text opacity-70">
              {{ item.barcode || "Sin codigo" }}
            </p>
            <p class="mt-2 text-xs text-text opacity-70">
              Stock disponible: {{ Number(item.stock).toFixed(0) }}
            </p>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-background px-3 py-3 md:bg-transparent md:px-0 md:py-0">
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

      <div class="rounded-xl bg-background px-3 py-3 md:bg-transparent md:px-0 md:py-0">
        <p class="text-[11px] uppercase tracking-[0.14em] text-text opacity-50 md:hidden">
          Cantidad
        </p>
        <QuantityStepper
          :value="item.quantity"
          :decrease-disabled="Number(item.quantity) <= 0"
          :increase-disabled="Number(item.quantity) >= Number(item.stock)"
          @decrease="$emit('decrease')"
          @increase="$emit('increase')"
        />
      </div>

      <div class="rounded-xl bg-background px-3 py-3 md:bg-transparent md:px-0 md:py-0">
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

      <div class="flex items-start justify-end">
        <button
          type="button"
          class="h-10 w-10 rounded-xl bg-background text-text transition hover:bg-secondary"
          @click="$emit('remove')"
        >
          x
        </button>
      </div>
    </div>

    <div class="mt-4 rounded-2xl border border-secondary bg-background p-3">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-sm font-semibold text-text">
            Descuento
          </p>
          <p class="text-xs text-text opacity-70">
            Activalo solo si este producto lleva rebaja.
          </p>
        </div>

        <ToggleSwitch
          :model-value="item.discount_enabled"
          @change="$emit('toggle-discount')"
        />
      </div>

      <div
        v-if="item.discount_enabled"
        class="mt-3 grid gap-3 lg:grid-cols-[180px_minmax(0,1fr)]"
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
    </div>
  </article>
</template>
