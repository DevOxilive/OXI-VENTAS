<script setup>
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

defineEmits([
  'update:search',
  'update:categoryFilter',
  'update:recordsToShow',
  'create',
  'export',
])
</script>
<template>
  <section class="bg-transparent">

    <!-- ENCABEZADO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">

      <div>
        <h2 class="text-3xl font-bold text-slate-800">
          Productos - {{ branch?.name }}
        </h2>

        <p class="text-sm text-slate-500">
          Administración de productos de la sucursal {{ branch?.name }}
        </p>
      </div>

      <div class="flex items-center gap-3">

        <span class="text-sm text-slate-500 hidden md:block">
          Mostrando {{ recordsToShow }} registros
        </span>

        <select :value="recordsToShow" @change="$emit('update:recordsToShow', Number($event.target.value))"
          class="rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
          <option :value="200">200</option>
        </select>
<button v-if="canCreate" @click="$emit('create')"
          class="px-5 py-2.5 rounded-2xl bg-black text-white font-medium shadow-sm hover:bg-slate-800 transition">
          + Agregar
        </button>

      </div>
    </div>

    <!-- FILTROS -->
    <div class="grid grid-cols-1 md:grid-cols-[1.5fr_1fr] gap-4">

      <input :value="search" @input="$emit('update:search', $event.target.value)"
        @keydown.enter.prevent="$emit('update:search', $event.target.value)" type="text" inputmode="search"
        autocomplete="off" placeholder="Buscar producto"
        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-slate-500" />

      <select :value="categoryFilter" @change="$emit('update:categoryFilter', $event.target.value)"
        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-slate-500">
        <option value="Todas">Categoría</option>

        <option v-for="category in categoriesDB" :key="category.id" :value="category.id">
          {{ category.name }}
        </option>
      </select>

    </div>

  </section>
</template>