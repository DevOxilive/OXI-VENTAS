<script setup>
import { usePermissions } from "@/Composables/usePermissions";
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue";

const { can } = usePermissions();

defineProps({
  products: {
    type: Array,
    default: () => [],
  },
});

defineEmits(["view", "edit", "delete"]);

function stockColor(product) {
  if (product.stock <= product.minimum_stock) {
    return "bg-red-500";
  }

  if (product.stock >= product.maximum_stock) {
    return "bg-blue-500";
  }

  return "bg-green-600";
}
</script>

<template>
  <div class="hidden md:block bg-white rounded-2xl shadow-sm overflow-hidden">
    <!-- HEADER -->
    <div class="flex items-center justify-between p-5 border-b">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          Gestión de Productos
        </h2>

        <p class="text-sm text-slate-500 mt-1">
          {{ products.length }} productos encontrados
        </p>
      </div>
    </div>

    <!-- TABLA -->
    <div class="overflow-x-auto">
  <table class="w-full min-w-[1050px] text-sm">
       <thead>
    <tr class="border-b bg-slate-50">

        <th class="text-left px-5 py-4 font-semibold">
            Código barras
        </th>

        <th class="text-left px-5 py-4 font-semibold">
            Producto
        </th>

        <th class="text-left px-5 py-4 font-semibold">
            Categoría
        </th>

        <th class="text-left px-5 py-4 font-semibold">
            Stock
        </th>

        <th class="text-left px-5 py-4 font-semibold">
            Precio
        </th>

        <th class="text-left px-5 py-4 font-semibold">
            Fecha ingreso
        </th>

        <th class="text-center px-5 py-4 font-semibold">
            Acciones
        </th>

    </tr>
</thead>

        <tbody>
          <tr
            v-for="product in products"
            :key="product.id"
            class="border-b hover:bg-slate-50 transition"
          >
          <!-- CODIGO -->
<td class="px-5 py-4 text-slate-500 whitespace-nowrap">
    {{ product.barcode || 'Sin código' }}
</td>

            <!-- PRODUCTO -->
            <td class="px-5 py-4">
              <div class="flex items-center gap-3">
                <img
                  v-if="product.image"
                  :src="product.image"
                  class="w-12 h-12 rounded-xl object-cover border"
                />

                <div
                  v-else
                  class="w-12 h-12 rounded-xl border bg-slate-100 flex items-center justify-center text-slate-400"
                >
                  <span class="material-symbols-outlined text-[22px]">
                    image
                  </span>
                </div>

                <div>
                  <div class="font-semibold text-slate-800">
                    {{ product.name }}
                  </div>

                  <div
                    v-if="product.presentation"
                    class="text-xs text-slate-400 mt-1"
                  >
                    {{ product.presentation }}
                  </div>
                </div>
              </div>
            </td>

            <!-- CATEGORIA -->
            <td class="px-5 py-4">
              <span
                class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs"
              >
                {{ product.category_name }}
              </span>
            </td>

            <!-- STOCK -->
            <td class="px-5 py-4">
              <span
                class="text-white text-xs font-semibold px-3 py-1 rounded-full"
                :class="stockColor(product)"
              >
                {{ product.stock }}
              </span>
            </td>

            <!-- PRECIO -->
            <td class="px-5 py-4 font-semibold text-slate-800">
              ${{ product.price }}
            </td>
            <!-- fecha -->
     
<td class="px-5 py-4 text-slate-600 whitespace-nowrap">
    {{ product.entry_date || 'Sin fecha' }}
</td>

            <td class="px-5 py-4">
              <div class="flex items-center gap-2">
                <ActionIconButton
                  v-if="can('inventario.ver')"
                  icon="visibility"
                  title="Ver producto"
                  variant="blue"
                  @click.stop="$emit('view', product)"
                />

                <ActionIconButton
                  v-if="can('inventario.editar')"
                  icon="edit"
                  title="Editar producto"
                  variant="amber"
                  @click.stop="$emit('edit', product)"
                />

                <ActionIconButton
                  v-if="can('inventario.eliminar')"
                  icon="delete"
                  title="Eliminar producto"
                  variant="red"
                  @click.stop="$emit('delete', product)"
                />
              </div>
            </td>
          </tr>

          <!-- VACIO -->
          <tr v-if="products.length === 0">
            <td colspan="7" class="text-center py-16 text-slate-400">
              No se encontraron productos
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>