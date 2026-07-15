<script setup>
import GlobalCard from "@/Components/Cards/GlobalCard.vue"

defineProps({
  branch: {
    type: Object,
    required: true,
  },
})

defineEmits(["select"])
</script>

<template>
  <GlobalCard
    :title="branch.name"
    subtitle="Punto de venta"
    description="Entra al punto de venta de esta sucursal y registra la operacion con su stock real."
    icon="storefront"
    badge="Abrir venta"
    badge-variant="neutral"
    @click="$emit('select', branch.id)"
  >
    <div class="mt-6 text-xs font-semibold text-text opacity-50">
      Inventario y cobro en tiempo real
    </div>
  </GlobalCard>
</template>
