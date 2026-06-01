<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'

defineProps({
    form: {
        type: Object,
        required: true,
    },
    products: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'submit',
    'close',
])

function closeModal() {
    emit('close')
}

function handleEscape(event) {
    if (event.key === 'Escape') {
        closeModal()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-3xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader title="Agregar producto al inventario"
                subtitle="Registra un producto dentro de la sucursal seleccionada"
                :total-errors="Object.keys(form.errors || {}).length" mode="create" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <form id="inventoryForm"
                    class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm grid grid-cols-1 md:grid-cols-2 gap-5"
                    @submit.prevent="$emit('submit')">
                    <SelectField v-model="form.product_id" label="Producto" :options="products.map(product => ({
                        label: product.name,
                        value: product.id,
                    }))" />

                    <SelectField v-model="form.branch_id" label="Sucursal" :options="branches.map(branch => ({
                        label: branch.name,
                        value: branch.id,
                    }))" />

                    <InputField v-model="form.price" label="Precio" type="number" />

                    <InputField v-model="form.stock" label="Stock inicial" type="number" />

                    <div class="md:col-span-2">
                        <InputField v-model="form.min_stock" label="Stock mínimo" type="number" />
                    </div>
                </form>
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" save-button-text="Guardar" mode="create"
                @save="$emit('submit')" @close="closeModal" />
        </div>
    </div>
</template>