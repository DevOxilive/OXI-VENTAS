<script setup>
defineProps({
  comparison: {
    type: Array,
    default: () => [],
  },
});

const statusLabel = (status) => {
  if (status === "missing") return "Faltante";
  if (status === "surplus") return "Sobrante";
  return "Correcto";
};

const statusClass = (status) => {
  if (status === "missing") return "bg-red-100 text-red-700";
  if (status === "surplus") return "bg-yellow-100 text-yellow-700";
  return "bg-green-100 text-green-700";
};
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold text-gray-900">
      Comparativo de inventario
    </h2>

    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="border-b text-gray-500">
          <tr>
            <th class="py-2">Producto</th>
            <th class="py-2">Sistema</th>
            <th class="py-2">Contado</th>
            <th class="py-2">Dañado</th>
            <th class="py-2">Caducado</th>
            <th class="py-2">Diferencia</th>
            <th class="py-2">Estado</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="comparison.length === 0">
            <td colspan="7" class="py-6 text-center text-gray-500">
              Todavía no hay productos comparados.
            </td>
          </tr>

          <tr
            v-for="item in comparison"
            :key="item.branch_product_id"
            class="border-b last:border-b-0"
          >
            <td class="py-3">
              {{ item.product_name }}
            </td>

            <td class="py-3">
              {{ item.system_stock }}
            </td>

            <td class="py-3">
              {{ item.counted_stock }}
            </td>

            <td class="py-3">
              {{ item.damaged_stock }}
            </td>

            <td class="py-3">
              {{ item.expired_stock }}
            </td>

            <td
              class="py-3 font-semibold"
              :class="{
                'text-red-600': item.difference < 0,
                'text-green-600': item.difference === 0,
                'text-yellow-600': item.difference > 0,
              }"
            >
              {{
                item.difference > 0 ? `+${item.difference}` : item.difference
              }}
            </td>

            <td class="py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="statusClass(item.status)"
              >
                {{ statusLabel(item.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>