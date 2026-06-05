<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'

import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'

import { useEditBranchProductConfig } from '@/Composables/Inventory/useEditBranchProductConfig'

const emit = defineEmits(['close'])

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const productRef = computed(() => props.product)

const {
    form,
    frontendErrors,
    statusOptions,
    totalErrors,
    saveButtonText,
    productName,
    unit,
    stockLabel,
    isSeasonal,
    statusHelpText,
    validateField,
    saveConfig,
} = useEditBranchProductConfig(productRef)

function closeModal() {
    if (form.processing) return

    emit('close')
}

function submitConfig() {
    saveConfig(props.product.id, () => {
        closeModal()
    })
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

        <div class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[92vh] md:w-[94%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop>
            <GeneralModalHeader title="Configurar producto"
                subtitle="Define las reglas generales del producto en esta sucursal." :total-errors="totalErrors"
                mode="edit" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section class="w-full max-w-5xl mx-auto rounded-3xl border border-slate-200 bg-white overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            Producto
                        </p>

                        <h3 class="font-black text-slate-900 mt-1">
                            {{ productName }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            Stock actual: {{ stockLabel }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-6">
                            <section class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <InputField v-model="form.min_stock" :label="`Stock mínimo (${unit})`"
                                        field="min_stock" type="number" :readonly="form.processing"
                                        :error="frontendErrors.min_stock || form.errors.min_stock"
                                        @validate="validateField" />

                                    <SelectField v-model="form.status" label="Estado operativo" field="status"
                                        :options="statusOptions" :disabled="form.processing"
                                        :error="frontendErrors.status || form.errors.status"
                                        @validate="validateField" />
                                </div>

                                <div class="border-t border-slate-200 pt-4">
                                    <p class="text-sm font-black text-slate-800">
                                        Temporada general del producto
                                    </p>

                                    <p class="text-xs text-slate-500 mt-1">
                                        Esta temporada aplica como regla general del producto. Los lotes pueden tener su
                                        propia temporada.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <InputField v-model="form.season_start_date" label="Inicio de temporada"
                                        field="season_start_date" type="date" :readonly="form.processing || !isSeasonal"
                                        :error="frontendErrors.season_start_date || form.errors.season_start_date"
                                        @validate="validateField" />

                                    <InputField v-model="form.season_end_date" label="Fin de temporada"
                                        field="season_end_date" type="date" :readonly="form.processing || !isSeasonal"
                                        :error="frontendErrors.season_end_date || form.errors.season_end_date"
                                        @validate="validateField" />
                                </div>

                                <div v-if="!isSeasonal"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-sm text-slate-600">
                                        Selecciona estado <strong>Temporada</strong> para editar las fechas generales de
                                        temporada.
                                    </p>
                                </div>
                            </section>

                            <section class="space-y-4">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-sm font-black text-slate-800">
                                        Resumen operativo
                                    </p>

                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Stock mínimo configurado
                                            </p>

                                            <p class="text-2xl font-black text-slate-900">
                                                {{ form.min_stock || 0 }} {{ unit }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Estado general
                                            </p>

                                            <p class="text-lg font-black text-slate-900">
                                                {{statusOptions.find(option => option.value === form.status)?.label ??
                                                    'Sin estado'}}
                                            </p>

                                            <p class="text-sm text-slate-500 mt-1">
                                                {{ statusHelpText }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Temporada general
                                            </p>

                                            <p class="text-sm font-bold text-slate-800">
                                                <span v-if="isSeasonal">
                                                    {{ form.season_start_date || 'Sin inicio' }}
                                                    -
                                                    {{ form.season_end_date || 'Sin fin' }}
                                                </span>

                                                <span v-else>
                                                    No aplica
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" :save-button-text="saveButtonText"
                close-button-text="Cancelar" mode="edit" @save="submitConfig" @close="closeModal" />
        </div>
    </div>
</template>