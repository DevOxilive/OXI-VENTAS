<script setup>
import { computed } from 'vue'

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
    frontendErrors: {
        type: Object,
        default: () => ({}),
    },
})

const categories = ['Oxígeno medicinal', 'Equipo médico', 'Accesorios', 'Refacciones', 'Consumibles']
const branches = ['Sucursal Centro', 'Sucursal Norte', 'Sucursal Sur']
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
                        <SelectField label="Sucursal" field="branch" v-model="product.branch" :options="branches"
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
        </div>
    </GlobalModal>
</template>
