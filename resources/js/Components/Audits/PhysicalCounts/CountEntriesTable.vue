<script setup>
defineProps({
  entries: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold text-gray-900">Últimos registros</h2>

    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="border-b text-gray-500">
          <tr>
            <th class="py-2">Producto</th>
            <th class="py-2">Código</th>
            <th class="py-2">Contado</th>
            <th class="py-2">Dañado</th>
            <th class="py-2">Caducado</th>
            <th class="py-2">Lote</th>
            <th class="py-2">Caducidad</th>
            <th class="py-2">Usuario</th>
            <th class="py-2">Fecha</th>
            <th class="py-2">Hora</th>
          </tr>
        </thead>

        <tbody>
    <tr v-if="entries.length === 0">
        <td colspan="10" class="py-6 text-center text-gray-500">
            Sin registros todavía.
        </td>
    </tr>

    <tr
        v-for="entry in entries"
        :key="entry.id"
        class="border-b last:border-b-0"
    >
        <td class="py-3">
            {{ entry.branch_product?.name ?? 'Sin producto' }}
        </td>

        <td class="py-3">
            {{ entry.scanned_code ?? '-' }}
        </td>

        <td class="py-3">
            {{ entry.counted_quantity }}
        </td>

        <td class="py-3">
            {{ entry.damaged_quantity }}
        </td>

        <td class="py-3">
            {{ entry.expired_quantity }}
        </td>

        <td class="py-3">
            {{ entry.product_batch?.lot_number ?? 'N/A' }}
        </td>

        <td class="py-3">
            {{
                entry.expiration_date
                    ?? entry.product_batch?.expiration_date
                    ?? 'N/A'
            }}
        </td>

        <td class="py-3">
            {{ entry.user?.name ?? 'Sin usuario' }}
        </td>

        <td class="py-3">
            {{
                new Date(entry.created_at).toLocaleDateString('es-MX')
            }}
        </td>

        <td class="py-3">
            {{
                new Date(entry.created_at).toLocaleTimeString('es-MX', {
                    hour: '2-digit',
                    minute: '2-digit'
                })
            }}
        </td>
    </tr>
</tbody>
      </table>
    </div>
  </div>
</template>