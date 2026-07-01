<script setup>
import { computed } from 'vue'
import TableDesktop from './TableDesktop.vue'
import TableMobile from './TableMobile.vue'

const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  columns: {
    type: Array,
    required: true,
  },
  actions: {
    type: Array,
    default: () => [],
  },
  rowKey: {
    type: String,
    default: 'id',
  },
  noDataMessage: {
    type: String,
    default: 'No se encontraron registros',
  },
  loading: Boolean,
  hoverEffect: {
    type: Boolean,
    default: true,
  },
  striped: Boolean,
  selectable: Boolean,
  selectedItems: Object,
  mobileCardHeaderField: {
    type: String,
    required: true,
  },

  pagination: {
    type: [Object, Array],
    default: null,
  },
  showPagination: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits([
  'action',
  'row-click',
  'selection-change',
  'update:selectedItems',
  'page-change',
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

const pageNumberLinks = computed(() => {
  return paginationLinks.value.filter((link) => {
    const label = normalizePaginationLabel(link.label)
    return /^\d+$/.test(label)
  })
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

function goToPage(link) {
  if (!link?.url) return
  emit('page-change', link.url)
}

function normalizePaginationLabel(label) {
  return String(label ?? '')
    .replace(/&laquo;|&raquo;/g, '')
    .replace(/<[^>]*>/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}
</script>

<template>
  <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
    <div class="max-h-[560px] overflow-y-auto">
      <TableDesktop :items="items" :columns="columns" :actions="actions" :row-key="rowKey"
        :no-data-message="noDataMessage" :loading="loading" :hover-effect="hoverEffect" :striped="striped"
        :selectable="selectable" :selected-items="selectedItems" @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)" @selection-change="$emit('selection-change', $event)"
        @update:selectedItems="$emit('update:selectedItems', $event)" />

      <TableMobile :items="items" :columns="columns" :actions="actions" :row-key="rowKey"
        :no-data-message="noDataMessage" :loading="loading" :mobile-card-header-field="mobileCardHeaderField"
        @action="$emit('action', $event)" @row-click="$emit('row-click', $event)" />
    </div>
    <footer v-if="hasPagination"
      class="border-t border-slate-200 bg-slate-50 px-4 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <p class="text-sm text-slate-500 text-center md:text-left">
        Página {{ currentPage }} de {{ lastPage }}
        <span class="hidden md:inline"> · </span>
        <span class="block md:inline">Total: {{ totalRecords }} registros</span>
      </p>

      <div class="flex flex-wrap items-center justify-center gap-2">
        <button
          type="button"
          :disabled="!pagination?.prev_page_url"
          class="min-w-9 px-3 py-2 rounded-lg text-sm border transition inline-flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed bg-white text-slate-700 border-slate-200 hover:bg-slate-100"
          @click="goToPage({ url: pagination.prev_page_url })"
        >
          <span class="material-symbols-outlined text-[18px]">chevron_left</span>
        </button>

        <button
          v-for="link in pageNumberLinks"
          :key="`${normalizePaginationLabel(link.label)}-${link.url ?? 'current'}`"
          type="button"
          :disabled="!link.url && !link.active"
          class="min-w-9 px-3 py-2 rounded-lg text-sm border transition disabled:opacity-40 disabled:cursor-not-allowed"
          :class="link.active
            ? 'bg-slate-900 text-white border-slate-900'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
          @click="goToPage(link)"
        >
          {{ normalizePaginationLabel(link.label) }}
        </button>

        <button
          type="button"
          :disabled="!pagination?.next_page_url"
          class="min-w-9 px-3 py-2 rounded-lg text-sm border transition inline-flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed bg-white text-slate-700 border-slate-200 hover:bg-slate-100"
          @click="goToPage({ url: pagination.next_page_url })"
        >
          <span class="material-symbols-outlined text-[18px]">chevron_right</span>
        </button>
      </div>
    </footer>
  </section>
</template>
