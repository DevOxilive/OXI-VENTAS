<script setup>
import { computed, watch } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { getProductModalConfig } from '@/config/ModalConfigs/productModalConfig'

const emit = defineEmits(['close', 'save', 'validate'])

const props = defineProps({
    modo: {
        type: String,
        default: 'create',
    },
    product: {
        type: Object,
        required: true,
    },
    branch: {
        type: Object,
        default: () => ({}),
    },
    branches: {
        type: Array,
        default: () => [],
    },
    frontendErrors: {
        type: Object,
        default: () => ({}),
    },
})

const categories = ['Oxígeno medicinal', 'Equipo médico', 'Accesorios', 'Refacciones', 'Consumibles']
const statuses = ['Disponible', 'Stock bajo', 'Agotado', 'Inactivo']
const units = ['Pieza', 'Caja', 'Litro', 'Metro', 'Servicio']

const isReadOnly = computed(() => props.modo === 'view')

const totalErrors = computed(() => {
    return Object.values(props.frontendErrors || {}).filter(Boolean).length
})

const modalConfig = computed(() => getProductModalConfig({
    mode: props.modo,
    totalErrors: totalErrors.value,
    processing: Boolean(props.product?.processing),
}))

function closeModal() {
    emit('close')
}

function validate(field) {
    emit('validate', field)
}

const currentBranchId = computed(() => Number(props.branch?.id || 0))

function ensureBranchIds() {
    if (!Array.isArray(props.product.branch_ids)) {
        props.product.branch_ids = []
    }
}

function ensureCurrentBranchSelected() {
    ensureBranchIds()

    if (!currentBranchId.value) return

    const exists = props.product.branch_ids.some((branchId) => Number(branchId) === currentBranchId.value)

    if (!exists) {
        props.product.branch_ids = [...props.product.branch_ids, currentBranchId.value]
    }
}

function isCurrentBranch(branchId) {
    return Number(branchId) === currentBranchId.value
}

function isSelectedBranch(branchId) {
    ensureBranchIds()
    return props.product.branch_ids.some((selectedId) => Number(selectedId) === Number(branchId))
}

function toggleBranch(branchId) {
    if (isReadOnly.value || isCurrentBranch(branchId)) return

    ensureBranchIds()

    if (isSelectedBranch(branchId)) {
        props.product.branch_ids = props.product.branch_ids.filter((selectedId) => Number(selectedId) !== Number(branchId))
        return
    }

    props.product.branch_ids = [...props.product.branch_ids, Number(branchId)]
}

function toggleAllBranches() {
    const availableBranchIds = props.branches.map((branchItem) => Number(branchItem.id))

    if (props.product.branch_ids.length === availableBranchIds.length) {
        props.product.branch_ids = currentBranchId.value ? [currentBranchId.value] : []
        return
    }

    props.product.branch_ids = availableBranchIds
    ensureCurrentBranchSelected()
}

watch(
    () => [props.modo, props.branch?.id],
    ([modo]) => {
        if (modo !== 'create') return
        ensureCurrentBranchSelected()
    },
    { immediate: true }
)
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="$emit('save')"
        @close="closeModal"
    >
        <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <section>
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Datos del producto
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                        <InputField label="Código interno" field="code" v-model="product.code" :readonly="isReadOnly"
                            :error="frontendErrors.code || product.errors?.code" @validate="validate('code')" />

                        <InputField label="Código de barras" field="barcode" v-model="product.barcode" :readonly="isReadOnly"
                            :error="frontendErrors.barcode || product.errors?.barcode" @validate="validate('barcode')" />

                        <InputField label="Nombre del producto" field="name" v-model="product.name" :readonly="isReadOnly"
                            :error="frontendErrors.name || product.errors?.name" @validate="validate('name')" />

                        <SelectField label="Categoría" field="category" v-model="product.category" :options="categories"
                            :disabled="isReadOnly" :error="frontendErrors.category || product.errors?.category"
                            @validate="validate('category')" />

                        <SelectField label="Unidad de medida" field="unit" v-model="product.unit" :options="units"
                            :disabled="isReadOnly" :error="frontendErrors.unit || product.errors?.unit"
                            @validate="validate('unit')" />
                    </div>
                </section>

                <section>
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Stock y ubicacion
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                        <SelectField label="Sucursal" field="branch" v-model="product.branch" :options="branches.map(branchItem => branchItem.name)"
                            :disabled="isReadOnly" :error="frontendErrors.branch || product.errors?.branch"
                            @validate="validate('branch')" />

                        <SelectField label="Estatus" field="status" v-model="product.status" :options="statuses"
                            :disabled="isReadOnly" :error="frontendErrors.status || product.errors?.status"
                            @validate="validate('status')" />

                        <InputField label="Stock actual" field="stock" type="number" v-model="product.stock"
                            :readonly="isReadOnly" :error="frontendErrors.stock || product.errors?.stock"
                            @validate="validate('stock')" />

                        <InputField label="Stock mínimo" field="minStock" type="number" v-model="product.minStock"
                            :readonly="isReadOnly" :error="frontendErrors.minStock || product.errors?.minStock"
                            @validate="validate('minStock')" />

                        <InputField label="Lote" field="lot" v-model="product.lot" :readonly="isReadOnly"
                            :error="frontendErrors.lot || product.errors?.lot" @validate="validate('lot')" />

                        <InputField label="Fecha de caducidad" field="expirationDate" type="date"
                            v-model="product.expirationDate" :readonly="isReadOnly"
                            :error="frontendErrors.expirationDate || product.errors?.expirationDate"
                            @validate="validate('expirationDate')" />
                    </div>
                </section>

                <section>
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Costos y control
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                        <InputField label="Precio de compra" field="purchasePrice" type="number"
                            v-model="product.purchasePrice" :readonly="isReadOnly"
                            :error="frontendErrors.purchasePrice || product.errors?.purchasePrice"
                            @validate="validate('purchasePrice')" />

                        <InputField label="Precio de venta" field="salePrice" type="number" v-model="product.salePrice"
                            :readonly="isReadOnly" :error="frontendErrors.salePrice || product.errors?.salePrice"
                            @validate="validate('salePrice')" />

                        <InputField label="Proveedor" field="supplier" v-model="product.supplier" :readonly="isReadOnly"
                            :error="frontendErrors.supplier || product.errors?.supplier" @validate="validate('supplier')" />

                        <InputField label="Responsable" field="responsible" v-model="product.responsible"
                            :readonly="isReadOnly" :error="frontendErrors.responsible || product.errors?.responsible"
                            @validate="validate('responsible')" />

                        <InputField label="Observaciones" field="notes" v-model="product.notes" :readonly="isReadOnly"
                            :error="frontendErrors.notes || product.errors?.notes" @validate="validate('notes')" />
                    </div>
                </section>
            </div>

            <section v-if="modo !== 'view'" class="mt-6 rounded-[28px] border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Sucursales asignadas
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            La sucursal actual queda marcada automáticamente y no se puede quitar.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-[1px] hover:border-slate-300 hover:bg-slate-100"
                        @click="toggleAllBranches"
                    >
                        {{ product.branch_ids?.length === branches.length ? 'Quitar extras' : 'Seleccionar todas' }}
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="branchItem in branches"
                        :key="branchItem.id"
                        type="button"
                        class="group flex items-center gap-3 rounded-[24px] border px-4 py-4 text-left transition"
                        :class="
                            isCurrentBranch(branchItem.id)
                                ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-300/50'
                                : isSelectedBranch(branchItem.id)
                                    ? 'border-slate-300 bg-white shadow-sm hover:border-slate-400 hover:shadow-md'
                                    : 'border-slate-200 bg-slate-50/80 hover:border-slate-300 hover:bg-white'
                        "
                        @click="toggleBranch(branchItem.id)"
                    >
                        <span class="checkbox-wrapper-30 shrink-0" :class="{ 'is-disabled': isCurrentBranch(branchItem.id) }">
                            <span class="checkbox" :style="{ '--size': 1.15 }">
                                <input
                                    type="checkbox"
                                    :checked="isSelectedBranch(branchItem.id)"
                                    :disabled="isCurrentBranch(branchItem.id)"
                                    @click.stop="toggleBranch(branchItem.id)"
                                />
                                <svg viewBox="0 0 22 22" aria-hidden="true">
                                    <path
                                        fill="none"
                                        stroke="currentColor"
                                        d="M5.5,11.3L9,14.8L20.2,3.3l0,0c-0.5-1-1.5-1.8-2.7-1.8h-13c-1.7,0-3,1.3-3,3v13c0,1.7,1.3,3,3,3h13 c1.7,0,3-1.3,3-3v-13c0-0.4-0.1-0.8-0.3-1.2"
                                    />
                                </svg>
                            </span>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">
                                {{ branchItem.name }}
                            </p>
                            <p
                                class="mt-1 text-xs"
                                :class="isCurrentBranch(branchItem.id) ? 'text-slate-300' : 'text-slate-500'"
                            >
                                {{
                                    isCurrentBranch(branchItem.id)
                                        ? 'Sucursal actual'
                                        : isSelectedBranch(branchItem.id)
                                            ? 'Disponible para este registro'
                                            : 'Haz clic para agregar'
                                }}
                            </p>
                        </div>

                        <span
                            v-if="isCurrentBranch(branchItem.id)"
                            class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white"
                        >
                            Fija
                        </span>
                    </button>
                </div>
            </section>
        </div>
    </GlobalModal>
</template>

<style scoped>
.checkbox-wrapper-30 .checkbox {
    --bg: #fff;
    --brdr: #d1d6ee;
    --brdr-actv: #111827;
    --brdr-hovr: #94a3b8;
    --dur: calc((var(--size, 2) / 2) * 0.6s);
    display: inline-block;
    position: relative;
    width: calc(var(--size, 1) * 22px);
}

.checkbox-wrapper-30 .checkbox::after {
    content: '';
    display: block;
    padding-top: 100%;
    width: 100%;
}

.checkbox-wrapper-30 .checkbox > * {
    position: absolute;
}

.checkbox-wrapper-30 .checkbox input {
    -webkit-appearance: none;
    -moz-appearance: none;
    -webkit-tap-highlight-color: transparent;
    background-color: var(--bg);
    border: calc(var(--newBrdr, var(--size, 1)) * 1px) solid;
    border-radius: calc(var(--size, 1) * 4px);
    color: var(--newBrdrClr, var(--brdr));
    cursor: pointer;
    margin: 0;
    outline: none;
    padding: 0;
    transition: all calc(var(--dur) / 3) linear;
}

.checkbox-wrapper-30 .checkbox input:hover,
.checkbox-wrapper-30 .checkbox input:checked {
    --newBrdr: calc(var(--size, 1) * 2);
}

.checkbox-wrapper-30 .checkbox input:hover {
    --newBrdrClr: var(--brdr-hovr);
}

.checkbox-wrapper-30 .checkbox input:checked {
    --newBrdrClr: var(--brdr-actv);
    transition-delay: calc(var(--dur) / 1.3);
}

.checkbox-wrapper-30 .checkbox input:checked + svg {
    --dashArray: 16 93;
    --dashOffset: 109;
}

.checkbox-wrapper-30 .checkbox svg {
    fill: none;
    left: 0;
    pointer-events: none;
    stroke: currentColor;
    stroke-dasharray: var(--dashArray, 93);
    stroke-dashoffset: var(--dashOffset, 94);
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2px;
    top: 0;
    transition: stroke-dasharray var(--dur), stroke-dashoffset var(--dur);
}

.checkbox-wrapper-30 .checkbox svg,
.checkbox-wrapper-30 .checkbox input {
    display: block;
    height: 100%;
    width: 100%;
}

.checkbox-wrapper-30.is-disabled .checkbox {
    --bg: rgba(255, 255, 255, 0.12);
    --brdr: rgba(255, 255, 255, 0.45);
    --brdr-actv: #ffffff;
    --brdr-hovr: rgba(255, 255, 255, 0.6);
}

.checkbox-wrapper-30.is-disabled .checkbox input {
    cursor: default;
}
</style>
