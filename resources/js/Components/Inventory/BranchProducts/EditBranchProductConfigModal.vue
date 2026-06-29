<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { useEditBranchProductConfig } from '@/Composables/Inventory/useEditBranchProductConfig'
import { getBranchProductConfigModalConfig } from '@/config/ModalConfigs/branchProductConfigModalConfig'

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
    productName,
    unit,
    stockLabel,
    isSeasonal,
    statusHelpText,
    validateField,
    saveConfig,
} = useEditBranchProductConfig(productRef)

const modalConfig = computed(() => getBranchProductConfigModalConfig({
    totalErrors: totalErrors.value,
    processing: Boolean(form.processing),
}))

function closeModal() {
    if (form.processing) return

    emit('close')
}

function submitConfig() {
    saveConfig(props.product.id, () => {
        closeModal()
    })
}
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="submitConfig"
        @close="closeModal"
    >
        <section class="min-h-0 w-full">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Producto
                </p>

                <h3 class="mt-1 font-black text-slate-900">
                    {{ productName }}
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    Stock actual: {{ stockLabel }}
                </p>
            </div>

            <div class="p-5">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <section class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <InputField
                                v-model="form.min_stock"
                                :label="`Stock mínimo (${unit})`"
                                field="min_stock"
                                type="number"
                                :readonly="form.processing"
                                :error="frontendErrors.min_stock || form.errors.min_stock"
                                @validate="validateField"
                            />

                            <SelectField
                                v-model="form.status"
                                label="Estado operativo"
                                field="status"
                                :options="statusOptions"
                                :disabled="form.processing"
                                :error="frontendErrors.status || form.errors.status"
                                @validate="validateField"
                            />
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm font-black text-slate-800">
                                Temporada general del producto
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Esta temporada aplica como regla general del producto. Los lotes pueden tener su propia temporada.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <InputField
                                v-model="form.season_start_date"
                                label="Inicio de temporada"
                                field="season_start_date"
                                type="date"
                                :readonly="form.processing || !isSeasonal"
                                :error="frontendErrors.season_start_date || form.errors.season_start_date"
                                @validate="validateField"
                            />

                            <InputField
                                v-model="form.season_end_date"
                                label="Fin de temporada"
                                field="season_end_date"
                                type="date"
                                :readonly="form.processing || !isSeasonal"
                                :error="frontendErrors.season_end_date || form.errors.season_end_date"
                                @validate="validateField"
                            />
                        </div>

                        <div
                            v-if="!isSeasonal"
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                        >
                            <p class="text-sm text-slate-600">
                                Selecciona estado <strong>Temporada</strong> para editar las fechas generales de temporada.
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
                                        {{ statusOptions.find(option => option.value === form.status)?.label ?? 'Sin estado' }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
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
    </GlobalModal>
</template>
