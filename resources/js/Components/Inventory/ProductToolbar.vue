<script setup>
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

defineProps({
    total: {
        type: Number,
        default: 0
    },

    recordsToShow: {
        type: Number,
        default: 10
    }
})

defineEmits([
    'create',
    'update:recordsToShow'
])
</script><template>
  <div class="sticky top-0 z-30 bg-white px-6 pt-6 pb-5 border-b border-slate-200">
    <div class="mb-5">
      <h2 class="text-lg font-semibold text-slate-800">
        Gestión de Productos
      </h2>

      <p class="text-sm text-slate-500 mt-1">
        {{ total }} productos encontrados
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_auto_auto] gap-4 items-end">
      <div>
        <label class="block text-sm font-semibold text-slate-500 mb-2">
          Buscar producto
        </label>

        <input
          type="text"
          placeholder="Nombre, código o descripción..."
          class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        />
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-500 mb-2">
          Categoría
        </label>

        <select class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm bg-white">
          <option>Todas</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-500 mb-2">
          Subcategoría
        </label>

        <select disabled class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm bg-slate-100 text-slate-400">
          <option>Todas</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-500 mb-2">
          Mostrar
        </label>

        <select
          :value="recordsToShow"
          @change="$emit('update:recordsToShow', Number($event.target.value))"
          class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm bg-white"
        >
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
        </select>
      </div>

      <button
        v-if="can('productos.crear')"
        @click="$emit('create')"
        class="h-[42px] px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition shadow-sm whitespace-nowrap"
      >
        + Agregar
      </button>
    </div>
  </div>
</template>