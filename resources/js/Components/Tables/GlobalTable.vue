<script setup>
import { computed } from 'vue'
import TableDesktop from './TableDesktop.vue'
import TableMobile from './TableMobile.vue'

const props = defineProps({
  items: {
    type: Array,
    required: true
  },
  columns: {
    type: Array,
    required: true
  },
  actions: {
    type: Array,
    default: () => []
  },
  filters: Array,
  rowKey: {
    type: String,
    default: 'id'
  },
  noDataMessage: {
    type: String,
    default: 'No se encontraron registros'
  },
  loading: Boolean,
  hoverEffect: {
    type: Boolean,
    default: true
  },
  striped: Boolean,
  selectable: Boolean,
  selectedItems: Object,
  mobileCardHeaderField: {
    type: String,
    required: true
  },

  pagination: {
    type: [Object, Array],
    default: null
  },
  recordsPerPage: {
    type: Number,
    default: 50
  },
  recordsPerPageOptions: {
    type: Array,
    default: () => [10, 25, 50, 100]
  },
  showRecordsPerPage: {
    type: Boolean,
    default: true
  },
  showPagination: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits([
  'action',
  'row-click',
  'selection-change',
  'update:selectedItems',
  'update:recordsPerPage',
  'page-change'
])

const hasPagination = computed(() => {
  return (
    props.showPagination &&
    props.pagination &&
    !Array.isArray(props.pagination) &&
    Array.isArray(props.pagination.links) &&
    props.pagination.links.length > 0
  )
})

const paginationLinks = computed(() => {
  if (!hasPagination.value) return []
  return props.pagination.links
})

const totalRecords = computed(() => {
  if (!props.pagination || Array.isArray(props.pagination)) {
    return props.items.length
  }

  return props.pagination.total ?? props.items.length
})

const currentPage = computed(() => {
  if (!props.pagination || Array.isArray(props.pagination)) return 1
  return props.pagination.current_page ?? 1
})

const lastPage = computed(() => {
  if (!props.pagination || Array.isArray(props.pagination)) return 1
  return props.pagination.last_page ?? 1
})

function updateRecordsPerPage(event) {
  emit('update:recordsPerPage', Number(event.target.value))
}

function goToPage(link) {
  if (!link?.url) return
  emit('page-change', link.url)
}
</script>

<template>
  <div>
    <TableDesktop :items="items" :columns="columns" :actions="actions" :filters="filters" :row-key="rowKey"
      :no-data-message="noDataMessage" :loading="loading" :hover-effect="hoverEffect" :striped="striped"
      :selectable="selectable" :selected-items="selectedItems" @action="$emit('action', $event)"
      @row-click="$emit('row-click', $event)" @selection-change="$emit('selection-change', $event)"
      @update:selectedItems="$emit('update:selectedItems', $event)" />

    <TableMobile :items="items" :columns="columns" :actions="actions" :filters="filters" :row-key="rowKey"
      :no-data-message="noDataMessage" :loading="loading" :mobile-card-header-field="mobileCardHeaderField"
      @action="$emit('action', $event)" @row-click="$emit('row-click', $event)" />

    <div v-if="showRecordsPerPage || hasPagination"
      class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 px-2 py-5">
      <div class="flex items-center gap-3 text-sm text-slate-600">
        <span>Mostrar</span>

        <select v-if="showRecordsPerPage" :value="recordsPerPage"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500"
          @change="updateRecordsPerPage">
          <option v-for="option in recordsPerPageOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>

        <span>registros</span>

        <span class="hidden md:inline text-slate-400">|</span>

        <span class="text-slate-500">
          Total: {{ totalRecords }}
        </span>
      </div>

      <div v-if="hasPagination" class="flex flex-col md:flex-row md:items-center gap-3">
        <div class="text-sm text-slate-500 text-center md:text-right">
          Página {{ currentPage }} de {{ lastPage }}
        </div>

        <div class="flex flex-wrap items-center justify-center gap-2">
          <button v-for="link in paginationLinks" :key="link.label" type="button" :disabled="!link.url"
            class="min-w-9 px-3 py-2 rounded-lg text-sm border transition disabled:opacity-40 disabled:cursor-not-allowed"
            :class="link.active
              ? 'bg-slate-900 text-white border-slate-900'
              : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'" @click="goToPage(link)"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>