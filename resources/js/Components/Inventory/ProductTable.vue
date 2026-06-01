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
</script><template>
  <div class="hidden md:block w-full bg-white overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-5 py-4 font-semibold">
              Código barras
            </th>

            <th class="text-left px-5 py-4 font-semibold">
              Imagen
            </th>

            <th class="text-left px-5 py-4 font-semibold">
              Producto
            </th>

            <th class="text-left px-5 py-4 font-semibold">
              Categoría
            </th>

            <th class="text-left px-5 py-4 font-semibold">
              Unidad de medida
            </th>

            <th class="text-left px-5 py-4 font-semibold">
              Precio inicial
            </th>

            <th class="text-left px-5 py-4 font-semibold">
              Precio venta
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
            <!-- CÓDIGO BARRAS -->
            <td class="px-5 py-4 text-slate-500 whitespace-nowrap">
              {{ product.barcode || product.barcodes?.[0] || "Sin código" }}
            </td>

            <!-- IMAGEN -->
<td class="px-5 py-4">
  <span
    v-if="product.image"
    class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold"
  >
    ✓ Con imagen
  </span>

  <span
    v-else
    class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold"
  >
    ✕ Sin imagen
  </span>
</td>

<!-- PRODUCTO -->
            <!-- PRODUCTO -->
            <td class="px-5 py-4">
              <div class="font-semibold text-slate-800">
                {{ product.name }}
              </div>

              <div
                v-if="product.presentation"
                class="text-xs text-slate-400 mt-1"
              >
                {{ product.presentation }}
              </div>
            </td>

            <!-- CATEGORÍA -->
            <td class="px-5 py-4">
              <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs">
                {{ product.category_name || product.category || "Sin categoría" }}
              </span>
            </td>

            <!-- UNIDAD DE MEDIDA -->
            <td class="px-5 py-4 text-slate-600">
              {{ product.unit || product.unit_name || "Sin unidad" }}
            </td>

            <!-- PRECIO INICIAL -->
            <td class="px-5 py-4 font-semibold text-slate-800 whitespace-nowrap">
              ${{ product.cost || product.initial_price || "0.00" }}
            </td>

            <!-- PRECIO VENTA -->
            <td class="px-5 py-4 font-semibold text-slate-800 whitespace-nowrap">
              ${{ product.price || product.sale_price || product.salePrice || "0.00" }}
            </td>

            <!-- ACCIONES -->
            <td class="px-5 py-4">
              <div class="flex items-center justify-center gap-2">
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

          <tr v-if="products.length === 0">
            <td colspan="8" class="text-center py-16 text-slate-400">
              No se encontraron productos
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>