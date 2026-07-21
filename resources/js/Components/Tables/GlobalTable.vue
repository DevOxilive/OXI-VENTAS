<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
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

const isMobileViewport = ref(false)
let mobileMediaQuery = null
let handleViewportChange = null

const hasPagination = computed(() => {
  if (!props.showPagination || !props.pagination || Array.isArray(props.pagination)) {
    return false
  }

  if (Array.isArray(props.pagination.links) && props.pagination.links.length > 0) {
    return true
  }

  return (
    Number(props.pagination.current_page ?? 0) > 0 &&
    Number(props.pagination.last_page ?? 0) > 1
  )
})

const paginationLinks = computed(() => {
  if (!hasPagination.value) return []

  if (Array.isArray(props.pagination?.links) && props.pagination.links.length > 0) {
    return props.pagination.links
  }

  const last = Number(props.pagination?.last_page ?? 1)
  const current = Number(props.pagination?.current_page ?? 1)

  return Array.from({ length: last }, (_, index) => {
    const page = index + 1

    return {
      url: buildPageUrl(page),
      label: String(page),
      page,
      active: page === current,
    }
  })
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

function buildPageUrl(page) {
  const baseUrl =
    props.pagination?.first_page_url ||
    props.pagination?.path ||
    (typeof window !== 'undefined' ? window.location.href : null)

  if (!baseUrl) return null

  try {
    const url = new URL(baseUrl, typeof window !== 'undefined' ? window.location.origin : undefined)
    url.searchParams.set('page', String(page))

    if (props.pagination?.per_page) {
      url.searchParams.set('per_page', String(props.pagination.per_page))
    }

    return url.toString()
  } catch {
    return null
  }
}

function normalizePaginationLabel(label) {
  return String(label ?? '')
    .replace(/&laquo;|&raquo;/g, '')
    .replace(/<[^>]*>/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

onMounted(() => {
  if (typeof window === 'undefined') return

  mobileMediaQuery = window.matchMedia('(max-width: 767px)')
  handleViewportChange = (event) => {
    isMobileViewport.value = event.matches
  }

  isMobileViewport.value = mobileMediaQuery.matches
  mobileMediaQuery.addEventListener('change', handleViewportChange)
})

onBeforeUnmount(() => {
  if (!mobileMediaQuery || !handleViewportChange) return

  mobileMediaQuery.removeEventListener('change', handleViewportChange)
})
</script>

<template>
  <section class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm">
    <div class="max-h-[560px] overflow-y-auto">
      <TableDesktop
        v-if="!isMobileViewport"
        :items="items"
        :columns="columns"
        :actions="actions"
        :row-key="rowKey"
        :no-data-message="noDataMessage"
        :loading="loading"
        :hover-effect="hoverEffect"
        :striped="striped"
        :selectable="selectable"
        :selected-items="selectedItems"
        @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)"
        @selection-change="$emit('selection-change', $event)"
        @update:selectedItems="$emit('update:selectedItems', $event)"
      />

      <TableMobile
        v-else
        :items="items"
        :columns="columns"
        :actions="actions"
        :row-key="rowKey"
        :no-data-message="noDataMessage"
        :loading="loading"
        :selectable="selectable"
        :selected-items="selectedItems"
        :mobile-card-header-field="mobileCardHeaderField"
        @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)"
        @selection-change="$emit('selection-change', $event)"
        @update:selectedItems="$emit('update:selectedItems', $event)"
      />
    </div>

    <footer
      v-if="hasPagination"
      class="flex flex-col gap-3 border-t border-secondary bg-secondary px-4 py-4 md:flex-row md:items-center md:justify-between"
    >
      <p class="text-center text-sm text-text opacity-70 md:text-left">
        Pagina {{ currentPage }} de {{ lastPage }}
        <span class="hidden md:inline"> - </span>
        <span class="block md:inline">Total: {{ totalRecords }} registros</span>
      </p>

      <div class="flex flex-wrap items-center justify-center gap-2">
        <button
          type="button"
          :disabled="!pagination?.prev_page_url"
          class="min-w-9 inline-flex items-center justify-center rounded-lg border border-secondary bg-background px-3 py-2 text-sm text-text transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40"
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
            ? 'border-primary bg-primary text-white'
            : 'border-secondary bg-background text-text hover:bg-secondary'"
          @click="goToPage(link)"
        >
          {{ normalizePaginationLabel(link.label) }}
        </button>

        <button
          type="button"
          :disabled="!pagination?.next_page_url"
          class="min-w-9 inline-flex items-center justify-center rounded-lg border border-secondary bg-background px-3 py-2 text-sm text-text transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40"
          @click="goToPage({ url: pagination.next_page_url })"
        >
          <span class="material-symbols-outlined text-[18px]">chevron_right</span>
        </button>
      </div>
    </footer>
  </section>
</template>
