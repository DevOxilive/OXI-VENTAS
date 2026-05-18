<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'

defineProps({
    product: Object,
    frontendErrors: {
        type: Object,
        default: () => ({})
    },
    readonly: {
        type: Boolean,
        default: false
    }
})

defineEmits(['validate'])

const categories = ['Oxígeno medicinal', 'Equipo médico', 'Accesorios', 'Refacciones', 'Consumibles']
const branches = ['Sucursal Centro', 'Sucursal Norte', 'Sucursal Sur']
const statuses = ['Disponible', 'Stock bajo', 'Agotado', 'Inactivo']
const units = ['Pieza', 'Caja', 'Litro', 'Metro', 'Servicio']
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Datos del producto
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Código interno" field="code" v-model="product.code" :readonly="readonly"
                        :error="frontendErrors.code || product.errors?.code" @validate="$emit('validate', 'code')" />

                    <InputField label="Código de barras" field="barcode" v-model="product.barcode" :readonly="readonly"
                        :error="frontendErrors.barcode || product.errors?.barcode"
                        @validate="$emit('validate', 'barcode')" />

                    <InputField label="Nombre del producto" field="name" v-model="product.name" :readonly="readonly"
                        :error="frontendErrors.name || product.errors?.name" @validate="$emit('validate', 'name')" />

                    <SelectField label="Categoría" field="category" v-model="product.category" :options="categories"
                        :disabled="readonly" :error="frontendErrors.category || product.errors?.category"
                        @validate="$emit('validate', 'category')" />

                    <SelectField label="Unidad de medida" field="unit" v-model="product.unit" :options="units"
                        :disabled="readonly" :error="frontendErrors.unit || product.errors?.unit"
                        @validate="$emit('validate', 'unit')" />
                </div>
            </section>

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Stock y ubicación
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <SelectField label="Sucursal" field="branch" v-model="product.branch" :options="branches"
                        :disabled="readonly" :error="frontendErrors.branch || product.errors?.branch"
                        @validate="$emit('validate', 'branch')" />

                    <SelectField label="Estatus" field="status" v-model="product.status" :options="statuses"
                        :disabled="readonly" :error="frontendErrors.status || product.errors?.status"
                        @validate="$emit('validate', 'status')" />

                    <InputField label="Stock actual" field="stock" type="number" v-model="product.stock"
                        :readonly="readonly" :error="frontendErrors.stock || product.errors?.stock"
                        @validate="$emit('validate', 'stock')" />

                    <InputField label="Stock mínimo" field="minStock" type="number" v-model="product.minStock"
                        :readonly="readonly" :error="frontendErrors.minStock || product.errors?.minStock"
                        @validate="$emit('validate', 'minStock')" />

                    <InputField label="Lote" field="lot" v-model="product.lot" :readonly="readonly"
                        :error="frontendErrors.lot || product.errors?.lot" @validate="$emit('validate', 'lot')" />

                    <InputField label="Fecha de caducidad" field="expirationDate" type="date"
                        v-model="product.expirationDate" :readonly="readonly"
                        :error="frontendErrors.expirationDate || product.errors?.expirationDate"
                        @validate="$emit('validate', 'expirationDate')" />
                </div>
            </section>

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Costos y control
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Precio de compra" field="purchasePrice" type="number"
                        v-model="product.purchasePrice" :readonly="readonly"
                        :error="frontendErrors.purchasePrice || product.errors?.purchasePrice"
                        @validate="$emit('validate', 'purchasePrice')" />

                    <InputField label="Precio de venta" field="salePrice" type="number" v-model="product.salePrice"
                        :readonly="readonly" :error="frontendErrors.salePrice || product.errors?.salePrice"
                        @validate="$emit('validate', 'salePrice')" />

                    <InputField label="Proveedor" field="supplier" v-model="product.supplier" :readonly="readonly"
                        :error="frontendErrors.supplier || product.errors?.supplier"
                        @validate="$emit('validate', 'supplier')" />

                    <InputField label="Responsable" field="responsible" v-model="product.responsible"
                        :readonly="readonly" :error="frontendErrors.responsible || product.errors?.responsible"
                        @validate="$emit('validate', 'responsible')" />

                    <InputField label="Observaciones" field="notes" v-model="product.notes" :readonly="readonly"
                        :error="frontendErrors.notes || product.errors?.notes" @validate="$emit('validate', 'notes')" />
                </div>
            </section>

        </div>
    </div>
</template>