<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { router, useForm, usePage } from "@inertiajs/vue3"

import AdminLayout from "@/Layouts/AdminLayout.vue"
import PageLayout from "@/Layouts/PageLayout.vue"
import { GlobalModal, confirmModalAction, getModalRequestOptions } from "@/Components/Modales"
import { GlobalTable } from "@/Components/Tables"
import { GlobalToolbar } from "@/Components/Toolbars"
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
let unsubscribeBranchChanged = null

const form = useForm({
  name: "",
  color: "#facc15",
  maps_url: "",
  attendance_latitude: "",
  attendance_longitude: "",
  attendance_geofence_radius_meters: 100,
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

const googleMapsPreviewUrl = computed(() => {
  const coordinates = selectedMapCoordinates.value

  if (coordinates) {
    return `https://maps.google.com/maps?q=${coordinates.latitude},${coordinates.longitude}&z=17&output=embed`
  }

  return `https://www.google.com/maps?q=${encodeURIComponent(form.name || "sucursal")}&output=embed`
})

const googleMapsOpenUrl = computed(() => {
  const registeredUrl = form.maps_url?.trim()

  if (registeredUrl) return registeredUrl

  const coordinates = selectedMapCoordinates.value

  if (coordinates) {
    return `https://www.google.com/maps/search/?api=1&query=${coordinates.latitude},${coordinates.longitude}`
  }

  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(form.name || "sucursal")}`
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
  form.maps_url = ""
  form.attendance_latitude = ""
  form.attendance_longitude = ""
  form.attendance_geofence_radius_meters = 100
}

function fillBranchForm(branch) {
  form.name = branch.name
  form.color = branch.color || "#facc15"
  form.maps_url = branch.maps_url || ""
  form.attendance_latitude = branch.attendance_latitude || ""
  form.attendance_longitude = branch.attendance_longitude || ""
  form.attendance_geofence_radius_meters = branch.attendance_geofence_radius_meters || 100
}

function openCreateModal() {
  selectedBranch.value = null
  modalMode.value = "create"
  resetForm()
  showCreateModal.value = true
}

function viewBranch(branch) {
  if (!canViewBranches.value) return

  selectedBranch.value = branch
  modalMode.value = "view"
  fillBranchForm(branch)
  showCreateModal.value = true
}

function editBranch(branch) {
  selectedBranch.value = branch
  modalMode.value = "edit"
  fillBranchForm(branch)
  showCreateModal.value = true
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
}

function clampGeofenceRadius() {
  const radius = Number(form.attendance_geofence_radius_meters || 100)

  if (!Number.isFinite(radius)) {
    form.attendance_geofence_radius_meters = 100
    return
  }

  form.attendance_geofence_radius_meters = Math.min(1000, Math.max(10, Math.round(radius)))
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

onMounted(() => {
  unsubscribeBranchChanged = subscribeRealtime(
    REALTIME_CHANNELS.systems,
    REALTIME_EVENTS.branchChanged,
    reloadBranches,
  )
})

onBeforeUnmount(() => {
  unsubscribeBranchChanged?.()
})
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        :icon="toolbarConfig.icon"
        :title="toolbarConfig.title"
        :subtitle="toolbarConfig.subtitle"
        :search="search"
        :search-placeholder="toolbarConfig.searchPlaceholder"
        :show-search="toolbarConfig.showSearch"
        :actions="toolbarConfig.actions"
        :show-records-per-page="toolbarConfig.showRecordsPerPage"
        :total-records="normalizedBranches.length"
        :filtered-records="filteredBranches.length"
        @update:search="search = $event"
        @action="handleToolbarAction"
      />
    </template>

    <GlobalTable
      :items="filteredBranches"
      :columns="branchTableConfig.columns"
      :actions="branchActions"
      :row-key="branchTableConfig.rowKey"
      :no-data-message="branchTableConfig.noDataMessage"
      :mobile-card-header-field="branchTableConfig.mobileCardHeaderField"
      @action="handleTableAction"
      @row-click="handleRowClick"
    />

    <GlobalModal
      v-if="showCreateModal"
      v-bind="modalConfig"
      @save="submit"
      @close="closeCreateModal"
    >
      <form @submit.prevent="submit">
        <FormPanel
          title="Datos de la sucursal"
          description="Captura el nombre y el color que identificara visualmente a la sucursal."
          :heading-border="true"
          body-class="space-y-5"
        >
          <InputField
            v-model="form.name"
            label="Nombre de sucursal"
            field="name"
            :readonly="isBranchFormReadonly"
            placeholder="Ej. Ajusco"
            :error="form.errors.name"
          />

          <ColorField
            v-model="form.color"
            label="Color"
            field="color"
            :disabled="isBranchFormReadonly"
            :error="form.errors.color"
          />

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

              <a
                :href="googleMapsOpenUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center rounded-xl border border-primary bg-background px-3 py-2 text-xs font-semibold text-primary transition hover:bg-secondary"
              >
                Abrir mapa
              </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-secondary bg-background">
              <iframe
                title="Mapa de ubicacion de la sucursal"
                :src="googleMapsPreviewUrl"
                class="h-56 w-full"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              />
            </div>

            <InputField
              v-model="form.maps_url"
              label="URL Google Maps"
              field="maps_url"
              validation-field="mapsUrl"
              :readonly="isBranchFormReadonly"
              :preserve-case="true"
              placeholder="Pega el enlace de la sucursal en Google Maps"
              :error="form.errors.maps_url || form.errors.attendance_latitude"
              @blur="syncAttendanceLocationFromMapsUrl"
            />

            <div class="flex flex-wrap items-center gap-2 text-xs text-text opacity-75">
              <span class="rounded-full bg-background px-3 py-1">
                Latitud: {{ form.attendance_latitude || "Sin punto" }}
              </span>

              <span class="rounded-full bg-background px-3 py-1">
                Longitud: {{ form.attendance_longitude || "Sin punto" }}
              </span>

              <button
                v-if="!isBranchFormReadonly && selectedMapCoordinates"
                type="button"
                class="rounded-full border border-secondary bg-background px-3 py-1 font-semibold text-primary"
                @click="clearAttendanceLocation"
              >
                Limpiar punto
              </button>
            </div>

            <div class="space-y-3">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <label for="branch-geofence-radius-range" class="text-xs font-semibold text-text">
                    Radio permitido para asistencias
                  </label>

                  <input
                    id="branch-geofence-radius-range"
                    v-model.number="form.attendance_geofence_radius_meters"
                    type="range"
                    min="10"
                    max="1000"
                    step="10"
                    :disabled="isBranchFormReadonly"
                    class="mt-3 w-full accent-primary"
                    @change="clampGeofenceRadius"
                  >
                </div>

                <div class="w-full sm:w-36">
                  <InputField
                    v-model="form.attendance_geofence_radius_meters"
                    label="Metros"
                    field="attendance-geofence-radius"
                    type="number"
                    min="10"
                    max="1000"
                    :readonly="isBranchFormReadonly"
                    :error="form.errors.attendance_geofence_radius_meters"
                    @blur="clampGeofenceRadius"
                  />
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
