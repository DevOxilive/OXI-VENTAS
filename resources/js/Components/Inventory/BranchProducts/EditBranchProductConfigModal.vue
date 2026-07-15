<script setup>
import { computed } from 'vue'

import FormPanel from '@/Components/Cards/FormPanel.vue'
import SectionHeading from '@/Components/Cards/SectionHeading.vue'
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
            <SectionHeading
                :description="`Stock actual: ${stockLabel}`"
                :bordered="true"
                spacing="md"
            >
                <template #title>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text opacity-70">
                            Producto
                        </p>

                        <h3 class="mt-1 font-black text-text">
                            {{ productName }}
                        </h3>
                    </div>
                </template>
            </SectionHeading>

            <div class="grid grid-cols-1 gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <FormPanel
                    title="Configuracion operativa"
                    description="Define el stock minimo, el estado general y las fechas de temporada del producto."
                    panel-class="bg-background shadow-sm"
                    body-class="space-y-4"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <InputField
                            v-model="form.min_stock"
                            :label="`Stock minimo (${unit})`"
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

                    <SectionHeading
                        title="Temporada general del producto"
                        description="Esta temporada aplica como regla general del producto. Los lotes pueden tener su propia temporada."
                        :bordered="true"
                        spacing="sm"
                    />

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
                        class="rounded-2xl border border-secondary bg-secondary px-4 py-3"
                    >
                        <p class="text-sm text-text opacity-80">
                            Selecciona estado <strong>Temporada</strong> para editar las fechas generales de temporada.
                        </p>
                    </div>
                </FormPanel>

                <FormPanel
                    title="Resumen operativo"
                    panel-class="bg-background shadow-sm"
                >
                    <div class="space-y-4 rounded-3xl border border-secondary bg-secondary p-4">
                        <div>
                            <p class="text-xs text-text opacity-70">
                                Stock minimo configurado
                            </p>

                            <p class="text-2xl font-black text-text">
                                {{ form.min_stock || 0 }} {{ unit }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-text opacity-70">
                                Estado general
                            </p>

                            <p class="text-lg font-black text-text">
                                {{ statusOptions.find(option => option.value === form.status)?.label ?? 'Sin estado' }}
                            </p>

                            <p class="mt-1 text-sm text-text opacity-70">
                                {{ statusHelpText }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-text opacity-70">
                                Temporada general
                            </p>

                            <p class="text-sm font-bold text-text">
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
                </FormPanel>
            </div>
        </section>
    </GlobalModal>
</template>
