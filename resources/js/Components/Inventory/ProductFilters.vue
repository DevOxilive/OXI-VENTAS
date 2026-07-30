<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import { sanitizeToolbarSearch } from '@/Components/Toolbars/toolbarInputSanitizer'

defineProps({
  search: {
    type: String,
    default: '',
  },

  categoryFilter: {
    type: [String, Number],
    default: 'Todas',
  },

  categoriesDB: {
    type: Array,
    default: () => [],
  },

  branch: {
    type: Object,
    default: () => ({}),
  },

  recordsToShow: {
    type: Number,
    default: 10,
  },
  canCreate: {
  type: Boolean,
  default: false,
},
})

const emit = defineEmits([
  'update:search',
  'update:categoryFilter',
  'update:recordsToShow',
  'create',
  'export',
])

function handleSearchInput(value) {
  emit('update:search', sanitizeToolbarSearch(value))
}
</script>
<template>
  <section class="bg-transparent">

    <!-- ENCABEZADO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">

      <div>
        <h2 class="text-3xl font-bold text-text">
          Productos - {{ branch?.name }}
        </h2>

        <p class="text-sm text-text opacity-70">
          Administración de productos de la sucursal {{ branch?.name }}
        </p>
      </div>

      <div class="flex items-center gap-3">

        <span class="hidden text-sm text-text opacity-70 md:block">
          Mostrando {{ recordsToShow }} registros
        </span>

        <select :value="recordsToShow" @change="$emit('update:recordsToShow', Number($event.target.value))"
          class="rounded-2xl border border-secondary bg-background px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-primary">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
          <option :value="200">200</option>
        </select>
<button v-if="canCreate" @click="$emit('create')"
          class="rounded-2xl border border-primary bg-primary px-5 py-2.5 font-medium text-white shadow-sm transition hover:brightness-110">
          + Agregar
        </button>

      </div>
    </div>

    <!-- FILTROS -->
    <div class="grid grid-cols-1 md:grid-cols-[1.5fr_1fr] gap-4">

      <InputField
        :model-value="search"
        hide-label
        field="inventory-product-search"
        validation-field="toolbar_search"
        type="search"
        inputmode="search"
        autocomplete="off"
        placeholder="Buscar producto"
        :show-counter="false"
        @update:model-value="handleSearchInput"
        @keydown.enter.prevent="handleSearchInput($event.target.value)"
      />

      <select :value="categoryFilter" @change="$emit('update:categoryFilter', $event.target.value)"
        class="w-full rounded-2xl border border-secondary bg-background px-4 py-3 text-sm text-text outline-none focus:border-primary focus:ring-primary">
        <option value="Todas">Categoría</option>

        <option v-for="category in categoriesDB" :key="category.id" :value="category.id">
          {{ category.name }}
        </option>
      </select>

    </div>

  </section>
</template>
