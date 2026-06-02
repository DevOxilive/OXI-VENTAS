<script setup>
import { computed, reactive, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const frontendErrors = reactive({})

const form = useForm({
    min_stock: props.product.minStock ?? 0,
    status: props.product.administrativeStatus ?? 'active',
})

const statusOptions = [
    { label: 'Activo', value: 'active' },
    { label: 'Inactivo', value: 'inactive' },
    { label: 'Temporada', value: 'seasonal' },
]

const totalErrors = computed(() => {
    return Object.values(frontendErrors).filter(Boolean).length +
        Object.values(form.errors || {}).filter(Boolean).length
})

const saveButtonText = computed(() => {
    return form.processing
        ? 'Guardando...'
        : 'Guardar configuración'
})

watch(
    () => props.product,
    (product) => {
        form.min_stock = product.minStock ?? 0
        form.status = product.administrativeStatus ?? 'active'
    },
    { immediate: true }
)

function validateField(field) {
    frontendErrors[field] = ''

    if (field === 'min_stock' && Number(form.min_stock) < 0) {
        frontendErrors.min_stock = 'El stock mínimo no puede ser menor a cero.'
    }

    if (field === 'status' && !form.status) {
        frontendErrors.status = 'Selecciona un estado operativo.'
    }
}

function validateForm() {
    validateField('min_stock')
    validateField('status')

    return !Object.values(frontendErrors).some(Boolean)
}

function closeModal() {
    emit('close')
}

function saveConfig() {
    if (!validateForm()) return

    form.patch(route('inventario.branch-inventory.update-config', props.product.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    })
}
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-auto md:max-h-[94vh] md:w-[96%] md:max-w-3xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader title="Configurar producto"
                subtitle="Ajusta el stock mínimo y el estado operativo en esta sucursal" :total-errors="totalErrors"
                mode="edit" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <div class="mb-5 border-b pb-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            Producto
                        </p>

                        <h3 class="mt-1 text-lg font-black text-slate-900">
                            {{ product.name }}
                        </h3>

                        <p class="text-sm text-slate-500">
                            Stock actual: {{ product.stockLabel ?? product.stock }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <InputField v-model="form.min_stock" :label="`Stock mínimo (${product.unit ?? 'piezas'})`"
                            field="min_stock" type="number" :readonly="form.processing"
                            :error="frontendErrors.min_stock || form.errors.min_stock" @validate="validateField" />

                        <SelectField v-model="form.status" label="Estado operativo" field="status"
                            :options="statusOptions" :disabled="form.processing"
                            :error="frontendErrors.status || form.errors.status" @validate="validateField" />
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" :save-button-text="saveButtonText" mode="edit"
                @save="saveConfig" @close="closeModal" />
        </div>
    </div>
</template>