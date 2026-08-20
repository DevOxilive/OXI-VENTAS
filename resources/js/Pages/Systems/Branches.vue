<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { router, useForm, usePage } from "@inertiajs/vue3"

import AdminLayout from "@/Layouts/AdminLayout.vue"
import PageLayout from "@/Layouts/PageLayout.vue"
import { GlobalModal, confirmModalAction, getModalRequestOptions } from "@/Components/Modales"
import { GlobalTable } from "@/Components/Tables"
import { GlobalToolbar } from "@/Components/Toolbars"
import { ToastAlert } from "@/Components/Modales/UniversalActionModal"
import FormPanel from "@/Components/Cards/FormPanel.vue"
import ColorField from "@/Components/Forms/ColorField.vue"
import InputField from "@/Components/Forms/InputField.vue"
import { usePermissions } from "@/Composables/usePermissions"
import { getBranchModalConfig } from "@/config/ModalConfigs/branchModalConfig"
import { branchTableConfig } from "@/config/TableConfigs/branchTableConfig"
import { getBranchToolbarConfig } from "@/config/ToolbarConfigs/branchToolbarConfig"
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribeRealtime } from "@/realtime"

const props = defineProps({
  branches: {
    type: Array,
    default: () => [],
  },
  capabilities: {
    type: Object,
    default: () => ({}),
  },
  googleMapsApiKey: {
    type: String,
    default: "",
  },
  googleMapsMapId: {
    type: String,
    default: "",
  },
})

defineOptions({
  layout: AdminLayout,
})

const { can } = usePermissions()
const page = usePage()

const search = ref("")
const selectedBranch = ref(null)
const modalMode = ref("create")
const showCreateModal = ref(false)
const mapContainer = ref(null)
const isSearchingAddress = ref(false)
const locationSearchError = ref("")
let unsubscribeBranchChanged = null
let googleMapsPromise = null
let googleMap = null
let googleMarker = null
let googleCircle = null
let isSyncingGoogleMap = false
let addressSearchTimeout = null

const defaultMapCenter = { lat: 19.432608, lng: -99.133209 }
const defaultGoogleMapsMapId = "DEMO_MAP_ID"

const form = useForm({
  name: "",
  color: "#facc15",
  street: "",
  external_number: "",
  internal_number: "",
  postal_code: "",
  neighborhood: "",
  municipality: "",
  address_state: "",
  maps_url: "",
  attendance_latitude: "",
  attendance_longitude: "",
  attendance_geofence_radius_meters: 100,
  record_version: "",
})

const canViewBranches = computed(() => {
  return Boolean(props.capabilities?.viewBranches) && can("branches.view")
})

const isBranchFormReadonly = computed(() => {
  return modalMode.value === "view" || (modalMode.value === "edit" && !can("branches.update"))
})

const selectedMapCoordinates = computed(() => {
  if (form.attendance_latitude === "" || form.attendance_longitude === "") {
    return null
  }

  const latitude = Number(form.attendance_latitude)
  const longitude = Number(form.attendance_longitude)

  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
    return null
  }

  return { latitude, longitude }
})

const fullAddress = computed(() => {
  return [
    form.street,
    form.external_number,
    form.internal_number,
    form.neighborhood,
    form.municipality,
    form.address_state,
    form.postal_code,
  ].filter(Boolean).join(", ")
})

const googleMapsOpenUrl = computed(() => {
  const registeredUrl = form.maps_url?.trim()

  if (registeredUrl) return registeredUrl

  const coordinates = selectedMapCoordinates.value

  if (coordinates) {
    return `https://www.google.com/maps/search/?api=1&query=${coordinates.latitude},${coordinates.longitude}`
  }

  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress.value || form.name || "sucursal")}`
})

const toolbarConfig = computed(() =>
  getBranchToolbarConfig({
    canCreate: can("branches.create"),
  }),
)

const normalizedBranches = computed(() =>
  props.branches.map((branch) => ({
    ...branch,
    color: branch.color || "",
  })),
)

const filteredBranches = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) {
    return normalizedBranches.value
  }

  return normalizedBranches.value.filter((branch) => {
    return [branch.name, branch.slug, branch.color]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term))
  })
})

const modalConfig = computed(() =>
  getBranchModalConfig({
    mode: modalMode.value,
    totalErrors: Object.keys(form.errors || {}).length,
    processing: Boolean(form.processing),
  }),
)

const branchActions = computed(() =>
  branchTableConfig.actions.map((action) => ({
    ...action,
    hidden: (row) => {
      if (typeof action.hidden === "function" && action.hidden(row)) {
        return true
      }

      if (action.id === "view") {
        return !canViewBranches.value
      }

      if (Array.isArray(action.permission)) {
        return !action.permission.some((permission) => can(permission))
      }

      if (action.permission) {
        return !can(action.permission)
      }

      return false
    },
  })),
)

function resetForm() {
  form.reset()
  form.clearErrors()
  form.color = "#facc15"
  form.street = ""
  form.external_number = ""
  form.internal_number = ""
  form.postal_code = ""
  form.neighborhood = ""
  form.municipality = ""
  form.address_state = ""
  form.maps_url = ""
  form.attendance_latitude = ""
  form.attendance_longitude = ""
  form.attendance_geofence_radius_meters = 100
  form.record_version = ""
}

function fillBranchForm(branch) {
  form.name = branch.name
  form.color = branch.color || "#facc15"
  form.street = branch.street || ""
  form.external_number = branch.external_number || ""
  form.internal_number = branch.internal_number || ""
  form.postal_code = branch.postal_code || ""
  form.neighborhood = branch.neighborhood || ""
  form.municipality = branch.municipality || ""
  form.address_state = branch.address_state || ""
  form.maps_url = branch.maps_url || ""
  form.attendance_latitude = branch.attendance_latitude || ""
  form.attendance_longitude = branch.attendance_longitude || ""
  form.attendance_geofence_radius_meters = branch.attendance_geofence_radius_meters || 100
  form.record_version = branch.updated_at || ""
}

function openCreateModal() {
  selectedBranch.value = null
  modalMode.value = "create"
  resetForm()
  showCreateModal.value = true
  initializeGoogleMap()
}

function viewBranch(branch) {
  if (!canViewBranches.value) return

  selectedBranch.value = branch
  modalMode.value = "view"
  fillBranchForm(branch)
  showCreateModal.value = true
  initializeGoogleMap()
}

function editBranch(branch) {
  selectedBranch.value = branch
  modalMode.value = "edit"
  fillBranchForm(branch)
  showCreateModal.value = true
  initializeGoogleMap()
}

function updateAttendanceLocation(latitude, longitude) {
  form.attendance_latitude = Number(latitude).toFixed(7)
  form.attendance_longitude = Number(longitude).toFixed(7)
  form.maps_url = `https://www.google.com/maps/search/?api=1&query=${form.attendance_latitude},${form.attendance_longitude}`
  form.clearErrors("attendance_latitude")
  form.clearErrors("attendance_longitude")

  if (!form.attendance_geofence_radius_meters) {
    form.attendance_geofence_radius_meters = 100
  }
}

function applyGeocodedAddressFields(fields = {}) {
  const supportedFields = [
    "street",
    "external_number",
    "postal_code",
    "neighborhood",
    "municipality",
    "address_state",
  ]

  supportedFields.forEach((field) => {
    if (typeof fields[field] === "string" && fields[field].trim()) {
      form[field] = fields[field]
      form.clearErrors(field)
    }
  })
}

async function selectAttendanceLocation(latitude, longitude) {
  updateAttendanceLocation(latitude, longitude)
  syncGoogleMapFromForm()
  isSearchingAddress.value = true
  locationSearchError.value = ""

  try {
    const { data } = await window.axios.post(route("branches.geocode"), {
      latitude,
      longitude,
    })

    updateAttendanceLocation(data.latitude, data.longitude)
    form.maps_url = data.maps_url
    applyGeocodedAddressFields(data.address_fields)
    syncGoogleMapFromForm()
  } catch (error) {
    locationSearchError.value = error.response?.data?.message
      || "Se guardó el punto, pero no fue posible completar la dirección automáticamente."
  } finally {
    isSearchingAddress.value = false
  }
}

async function copyGoogleMapsLocation() {
  const location = googleMapsOpenUrl.value

  try {
    await navigator.clipboard.writeText(location)
  } catch {
    const copyTarget = document.createElement("textarea")
    copyTarget.value = location
    copyTarget.setAttribute("readonly", "")
    copyTarget.className = "fixed -left-[9999px]"
    document.body.appendChild(copyTarget)
    copyTarget.select()
    document.execCommand("copy")
    document.body.removeChild(copyTarget)
  }

  ToastAlert({ title: "Ubicación copiada" })
}

function loadGoogleMaps() {
  if (window.google?.maps) return Promise.resolve(window.google)
  if (googleMapsPromise) return googleMapsPromise

  googleMapsPromise = new Promise((resolve, reject) => {
    if (!props.googleMapsApiKey) {
      reject(new Error("No hay una llave de Google Maps disponible."))
      return
    }

    const script = document.createElement("script")
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(props.googleMapsApiKey)}&libraries=places,marker&v=weekly&loading=async`
    script.async = true
    script.defer = true
    script.onload = () => resolve(window.google)
    script.onerror = () => reject(new Error("No fue posible cargar Google Maps."))
    document.head.appendChild(script)
  })

  return googleMapsPromise
}

async function initializeGoogleMap() {
  if (!showCreateModal.value) return

  await nextTick()

  if (!mapContainer.value || googleMap) {
    syncGoogleMapFromForm()
    return
  }

  try {
    const google = await loadGoogleMaps()
    const coordinates = selectedMapCoordinates.value
    const center = coordinates
      ? { lat: coordinates.latitude, lng: coordinates.longitude }
      : defaultMapCenter

    googleMap = new google.maps.Map(mapContainer.value, {
      center,
      zoom: coordinates ? 17 : 11,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
      mapId: props.googleMapsMapId || defaultGoogleMapsMapId,
    })

    googleMarker = new google.maps.marker.AdvancedMarkerElement({
      map: googleMap,
      position: coordinates ? center : null,
      gmpDraggable: !isBranchFormReadonly.value,
    })

    googleCircle = new google.maps.Circle({
      map: googleMap,
      center,
      radius: Number(form.attendance_geofence_radius_meters || 100),
      editable: !isBranchFormReadonly.value,
      fillColor: "#dc1428",
      fillOpacity: 0.14,
      strokeColor: "#dc1428",
      strokeOpacity: 0.8,
      strokeWeight: 2,
      visible: Boolean(coordinates),
    })

    googleMap.addListener("click", (event) => {
      if (isBranchFormReadonly.value) return
      selectAttendanceLocation(event.latLng.lat(), event.latLng.lng())
    })

    googleMarker.addListener("dragend", (event) => {
      selectAttendanceLocation(event.latLng.lat(), event.latLng.lng())
    })

    googleCircle.addListener("radius_changed", () => {
      if (isBranchFormReadonly.value || isSyncingGoogleMap) return
      form.attendance_geofence_radius_meters = Math.round(googleCircle.getRadius())
      clampGeofenceRadius()
    })

    syncGoogleMapFromForm()
  } catch (error) {
    locationSearchError.value = error.message || "No fue posible cargar el mapa."
  }
}

function syncGoogleMapFromForm() {
  if (!googleMap || !googleMarker || !googleCircle) return

  const coordinates = selectedMapCoordinates.value
  const radius = Number(form.attendance_geofence_radius_meters || 100)

  isSyncingGoogleMap = true

  try {
    googleMarker.gmpDraggable = !isBranchFormReadonly.value
    googleCircle.setEditable(!isBranchFormReadonly.value)

    if (!coordinates) {
      googleMarker.map = null
      googleCircle.setVisible(false)
      return
    }

    const position = { lat: coordinates.latitude, lng: coordinates.longitude }
    googleMarker.map = googleMap
    googleMarker.position = position
    googleCircle.setCenter(position)
    googleCircle.setRadius(radius)
    googleCircle.setVisible(true)
    googleMap.setCenter(position)
    googleMap.setZoom(17)
  } finally {
    isSyncingGoogleMap = false
  }
}

function syncGoogleMapRadius() {
  if (!googleCircle) return

  isSyncingGoogleMap = true
  googleCircle.setRadius(Number(form.attendance_geofence_radius_meters || 100))
  isSyncingGoogleMap = false
}

function scheduleAddressSearch() {
  if (isBranchFormReadonly.value || !fullAddress.value) return

  clearTimeout(addressSearchTimeout)
  addressSearchTimeout = setTimeout(() => {
    searchAddressLocation({ silent: true })
  }, 700)
}

function handleBranchFormInput(event) {
  const addressFields = [
    "street",
    "external_number",
    "internal_number",
    "postal_code",
    "neighborhood",
    "municipality",
    "address_state",
  ]

  if (addressFields.includes(event.target?.name)) {
    scheduleAddressSearch()
  }
}

async function searchAddressLocation({ silent = false } = {}) {
  if (isBranchFormReadonly.value) return

  if (!fullAddress.value) {
    if (!silent) {
      locationSearchError.value = "Captura la direccion de la sucursal antes de buscarla."
    }
    return
  }

  const address = fullAddress.value
  isSearchingAddress.value = true
  locationSearchError.value = ""

  try {
    const { data } = await window.axios.post(route("branches.geocode"), {
      address,
    })

    if (address !== fullAddress.value) return

    updateAttendanceLocation(data.latitude, data.longitude)
    form.maps_url = data.maps_url
    applyGeocodedAddressFields(data.address_fields)
    await initializeGoogleMap()
    syncGoogleMapFromForm()
  } catch (error) {
    if (!silent) {
      locationSearchError.value = error.response?.data?.message
        || "No fue posible encontrar esa direccion. Marca el punto directamente en el mapa."
    }
  } finally {
    isSearchingAddress.value = false
  }
}

function parseGoogleMapsCoordinates(value = "") {
  const text = decodeURIComponent(String(value).trim())

  const patterns = [
    /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
    /[?&](?:q|query|ll)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
    /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
    /^(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)$/,
  ]

  for (const pattern of patterns) {
    const match = text.match(pattern)

    if (!match) continue

    const latitude = Number(match[1])
    const longitude = Number(match[2])

    if (
      Number.isFinite(latitude) &&
      Number.isFinite(longitude) &&
      latitude >= -90 &&
      latitude <= 90 &&
      longitude >= -180 &&
      longitude <= 180
    ) {
      return { latitude, longitude }
    }
  }

  return null
}

function syncAttendanceLocationFromMapsUrl() {
  if (isBranchFormReadonly.value) return

  if (!form.maps_url?.trim()) {
    form.attendance_latitude = ""
    form.attendance_longitude = ""
    form.clearErrors("attendance_latitude")
    form.clearErrors("attendance_longitude")
    return
  }

  const coordinates = parseGoogleMapsCoordinates(form.maps_url)

  if (!coordinates) {
    return
  }

  form.clearErrors("attendance_latitude")
  form.clearErrors("attendance_longitude")
  form.attendance_latitude = coordinates.latitude.toFixed(7)
  form.attendance_longitude = coordinates.longitude.toFixed(7)

  if (!form.attendance_geofence_radius_meters) {
    form.attendance_geofence_radius_meters = 100
  }
}

function clearAttendanceLocation() {
  if (isBranchFormReadonly.value) return

  form.maps_url = ""
  form.attendance_latitude = ""
  form.attendance_longitude = ""
  form.clearErrors("attendance_latitude")
  form.clearErrors("attendance_longitude")
  syncGoogleMapFromForm()
}

function clampGeofenceRadius() {
  const radius = Number(form.attendance_geofence_radius_meters || 100)

  if (!Number.isFinite(radius)) {
    form.attendance_geofence_radius_meters = 100
    return
  }

  form.attendance_geofence_radius_meters = Math.min(1000, Math.max(10, Math.round(radius)))
  syncGoogleMapRadius()
}

async function deleteBranch(branch) {
  const result = await confirmModalAction({
    mode: "delete",
    entityName: "sucursal",
    title: "Eliminar sucursal",
    message: `¿Deseas eliminar ${branch.name}?`,
    confirmText: "Sí, eliminar",
  })

  if (!result.isConfirmed) return

  form.record_version = branch.updated_at || ""
  form.delete(route("branches.destroy", branch.id), getModalRequestOptions({
    mode: "delete",
    entityName: "Sucursal",
    successTitle: "Sucursal eliminada correctamente",
    errorTitle: "Error al eliminar sucursal",
    errorMessage: "No fue posible eliminar la sucursal.",
  }))
}

function closeCreateModal() {
  showCreateModal.value = false
  form.clearErrors()
  googleMap = null
  googleMarker = null
  googleCircle = null
  isSyncingGoogleMap = false
}

function reloadBranches(event = null) {
  if (
    event?.action === "deleted" &&
    Number(event.branchId) === Number(selectedBranch.value?.id)
  ) {
    closeCreateModal()
    selectedBranch.value = null
  }

  refreshRealtimeProps(page, ["branches", "capabilities"], {
    onSuccess: () => {
      if (!selectedBranch.value?.id || modalMode.value === "create") return
      if (modalMode.value !== "view") return

      const updatedBranch = normalizedBranches.value.find((branch) => {
        return branch.id === selectedBranch.value.id
      })

      if (!updatedBranch) return

      selectedBranch.value = updatedBranch
      fillBranchForm(updatedBranch)
    },
  })
}

function submit() {
  if (modalMode.value === "view") {
    closeCreateModal()
    return
  }

  if (modalMode.value === "edit") {
    form.put(route("branches.update", selectedBranch.value.id), getModalRequestOptions({
      mode: "edit",
      entityName: "Sucursal",
      close: closeCreateModal,
      successTitle: "Sucursal actualizada correctamente",
      errorTitle: "Error al actualizar sucursal",
      errorMessage: "No fue posible actualizar la sucursal.",
    }))

    return
  }

  form.post(route("branches.store"), getModalRequestOptions({
    mode: "create",
    entityName: "Sucursal",
    close: closeCreateModal,
    successTitle: "Sucursal creada correctamente",
    errorTitle: "Error al crear sucursal",
    errorMessage: "Revisa los datos capturados.",
    onSuccess: () => {
      form.reset("name")
      form.color = "#facc15"
      form.street = ""
      form.external_number = ""
      form.internal_number = ""
      form.postal_code = ""
      form.neighborhood = ""
      form.municipality = ""
      form.address_state = ""
      form.maps_url = ""
      form.attendance_latitude = ""
      form.attendance_longitude = ""
      form.attendance_geofence_radius_meters = 100
    },
  }))
}

function handleToolbarAction(action) {
  if (action === "create") {
    openCreateModal()
  }
}

function handleTableAction({ action, row }) {
  if (action === "view" && canViewBranches.value) {
    viewBranch(row)
  }

  if (action === "edit" && can("branches.update")) {
    editBranch(row)
  }

  if (action === "delete" && can("branches.delete")) {
    deleteBranch(row)
  }
}

function handleRowClick(branch) {
  if (canViewBranches.value) {
    viewBranch(branch)
  }
}

watch(canViewBranches, (canView) => {
  if (!canView && modalMode.value === "view") {
    closeCreateModal()
  }
})

watch(
  () => form.maps_url,
  () => {
    syncAttendanceLocationFromMapsUrl()
  },
)

watch(selectedMapCoordinates, () => {
  syncGoogleMapFromForm()
})

watch(
  () => form.attendance_geofence_radius_meters,
  () => {
    syncGoogleMapRadius()
  },
)

onMounted(() => {
  unsubscribeBranchChanged = subscribeRealtime(
    REALTIME_CHANNELS.systems,
    REALTIME_EVENTS.branchChanged,
    reloadBranches,
  )
})

onBeforeUnmount(() => {
  clearTimeout(addressSearchTimeout)
  unsubscribeBranchChanged?.()
})
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar :icon="toolbarConfig.icon" :title="toolbarConfig.title" :subtitle="toolbarConfig.subtitle"
        :search="search" :search-placeholder="toolbarConfig.searchPlaceholder" :show-search="toolbarConfig.showSearch"
        :actions="toolbarConfig.actions" :show-records-per-page="toolbarConfig.showRecordsPerPage"
        :total-records="normalizedBranches.length" :filtered-records="filteredBranches.length"
        @update:search="search = $event" @action="handleToolbarAction" />
    </template>

    <GlobalTable :items="filteredBranches" :columns="branchTableConfig.columns" :actions="branchActions"
      :row-key="branchTableConfig.rowKey" :no-data-message="branchTableConfig.noDataMessage"
      :mobile-card-header-field="branchTableConfig.mobileCardHeaderField" @action="handleTableAction"
      @row-click="handleRowClick" />

    <GlobalModal v-if="showCreateModal" v-bind="modalConfig" @save="submit" @close="closeCreateModal">
      <form @submit.prevent="submit" @input="handleBranchFormInput">
        <FormPanel title="Datos de la sucursal"
          description="Captura el nombre y el color que identificara visualmente a la sucursal." :heading-border="true"
          body-class="space-y-5">
          <InputField v-model="form.name" label="Nombre de sucursal" field="name" :readonly="isBranchFormReadonly"
            placeholder="Ej. Ajusco" :error="form.errors.name" />

          <ColorField v-model="form.color" label="Color" field="color" :disabled="isBranchFormReadonly"
            :error="form.errors.color" />

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <InputField v-model="form.street" label="Calle" field="street" :readonly="isBranchFormReadonly"
              :error="form.errors.street" />

            <InputField v-model="form.external_number" label="Numero exterior" field="external_number"
              validation-field="externalNumber" :readonly="isBranchFormReadonly" :error="form.errors.external_number" />

            <InputField v-model="form.internal_number" label="Numero interior" field="internal_number"
              validation-field="internalNumber" :readonly="isBranchFormReadonly" :error="form.errors.internal_number" />

            <InputField v-model="form.postal_code" label="Codigo postal" field="postal_code"
              validation-field="postalCode" :readonly="isBranchFormReadonly" :error="form.errors.postal_code" />

            <InputField v-model="form.neighborhood" label="Colonia" field="neighborhood"
              :readonly="isBranchFormReadonly" :error="form.errors.neighborhood" />

            <InputField v-model="form.municipality" label="Municipio" field="municipality"
              :readonly="isBranchFormReadonly" :error="form.errors.municipality" />

            <InputField v-model="form.address_state" label="Estado" field="address_state"
              validation-field="addressState" :readonly="isBranchFormReadonly" :error="form.errors.address_state" />
          </div>

          <div class="space-y-4 rounded-2xl border border-secondary bg-secondary p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="text-sm font-semibold text-text">
                  Ubicacion para asistencias
                </p>

                <p class="mt-1 text-xs text-text opacity-70">
                  Marca la sucursal desde Google Maps y usa ese punto para validar el radio de entrada.
                </p>
              </div>

              <a :href="googleMapsOpenUrl" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center justify-center rounded-xl border border-primary bg-background px-3 py-2 text-xs font-semibold text-primary transition hover:bg-secondary">
                Abrir mapa
              </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-secondary bg-background">
              <div ref="mapContainer" class="h-72 w-full" />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
              <div class="flex-1">
                <p class="text-xs text-text opacity-70">
                  Completa los datos de direccion y busca el punto. Tambien puedes seleccionarlo directamente en el
                  mapa.
                </p>

                <p v-if="locationSearchError" class="mt-2 text-xs font-medium text-danger">
                  {{ locationSearchError }}
                </p>
              </div>

              <button v-if="!isBranchFormReadonly" type="button"
                class="inline-flex min-h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isSearchingAddress || !fullAddress" @click="searchAddressLocation">
                {{ isSearchingAddress ? "Buscando..." : "Buscar ubicacion" }}
              </button>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
              <div class="flex-1">
                <InputField v-model="form.maps_url" label="Enlace de Google Maps" field="maps_url" :readonly="true"
                  :preserve-case="true" placeholder="Se genera al seleccionar la ubicacion"
                  :error="form.errors.maps_url || form.errors.attendance_latitude" />
              </div>

              <div class="flex gap-2">
                <button type="button"
                  class="inline-flex min-h-10 items-center justify-center rounded-xl border border-secondary bg-background px-3 text-xs font-semibold text-text transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="!selectedMapCoordinates" @click="copyGoogleMapsLocation">
                  Copiar ubicación
                </button>

                <a :href="googleMapsOpenUrl" target="_blank" rel="noopener noreferrer"
                  class="inline-flex min-h-10 items-center justify-center rounded-xl border border-primary bg-background px-3 text-xs font-semibold text-primary transition hover:bg-secondary">
                  Abrir Google Maps
                </a>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-text opacity-75">
              <span class="rounded-full bg-background px-3 py-1">
                Latitud: {{ form.attendance_latitude || "Sin punto" }}
              </span>

              <span class="rounded-full bg-background px-3 py-1">
                Longitud: {{ form.attendance_longitude || "Sin punto" }}
              </span>

              <button v-if="!isBranchFormReadonly && selectedMapCoordinates" type="button"
                class="rounded-full border border-secondary bg-background px-3 py-1 font-semibold text-primary"
                @click="clearAttendanceLocation">
                Limpiar punto
              </button>
            </div>

            <div class="space-y-3">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <label for="branch-geofence-radius-range" class="text-xs font-semibold text-text">
                    Radio permitido para asistencias
                  </label>

                  <input id="branch-geofence-radius-range" v-model.number="form.attendance_geofence_radius_meters"
                    type="range" min="10" max="1000" step="10" :disabled="isBranchFormReadonly"
                    class="mt-3 w-full accent-primary" @input="clampGeofenceRadius" @change="clampGeofenceRadius">
                </div>

                <div class="w-full sm:w-36">
                  <InputField v-model="form.attendance_geofence_radius_meters" label="Metros"
                    field="attendance-geofence-radius" type="number" min="10" max="1000"
                    :readonly="isBranchFormReadonly" :error="form.errors.attendance_geofence_radius_meters"
                    @input="clampGeofenceRadius" @blur="clampGeofenceRadius" />
                </div>
              </div>

              <p class="text-xs text-text opacity-70">
                Por defecto se usan 100 metros. El radio maximo permitido es de 1000 metros.
              </p>
            </div>
          </div>
        </FormPanel>
      </form>
    </GlobalModal>
  </PageLayout>
</template>
