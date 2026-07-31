<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import AuditReportMultiSelect from './AuditReportMultiSelect.vue'

const props = defineProps({
    audits: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    pagination: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'export', 'remove', 'page-change'])

const resultOptions = [
    { id: 'matched', name: 'Coincidentes' },
    { id: 'missing', name: 'Faltantes' },
    { id: 'surplus', name: 'Sobrantes' },
    { id: 'not_found', name: 'No encontrados' },
]

const lotDetailOptions = [
    { value: '0', label: 'Sin detalle de lotes' },
    { value: '1', label: 'Incluir detalle de lotes' },
]

const columns = [
    { key: 'audit', label: 'Auditoría', mobileLabel: 'Auditoría', mobileDisplay: true, minWidth: '240px' },
    { key: 'user_ids', label: 'Usuarios', mobileLabel: 'Usuarios', mobileDisplay: true, minWidth: '230px' },
    { key: 'category_ids', label: 'Categorías', mobileLabel: 'Categorías', mobileDisplay: true, minWidth: '230px' },
    { key: 'results', label: 'Resultados', mobileLabel: 'Resultados', mobileDisplay: true, minWidth: '210px' },
    { key: 'include_lots', label: 'Detalle de lotes', mobileLabel: 'Lotes', mobileDisplay: true, minWidth: '240px' },
]

const actions = [
    { id: 'excel', label: 'Exportar Excel', icon: 'table_view', variant: 'green', permission: 'reports.audits.export.excel' },
    { id: 'pdf', label: 'Exportar PDF', icon: 'picture_as_pdf', variant: 'red', permission: 'reports.audits.export.pdf' },
    { id: 'remove', label: 'Quitar de la tabla', icon: 'delete', variant: 'red' },
]

function selection(auditId, key) {
    return props.modelValue[String(auditId)]?.[key] || []
}

function updateSelection(auditId, key, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [String(auditId)]: {
            user_ids: [],
            category_ids: [],
            results: [],
            include_lots: false,
            ...(props.modelValue[String(auditId)] || {}),
            [key]: value,
        },
    })
}

function handleAction({ action, row }) {
    if (action === 'excel' || action === 'pdf') {
        emit('export', { type: action, auditId: row.id })
        return
    }

    if (action === 'remove') emit('remove', row.id)
}
</script>

<template>
    <GlobalTable
        :items="audits"
        :columns="columns"
        :actions="actions"
        :pagination="pagination"
        mobile-card-header-field="name"
        no-data-message="No hay auditorías para los filtros seleccionados."
        @action="handleAction"
        @page-change="emit('page-change', $event)"
    >
        <template #cell-audit="{ row }">
            <div class="min-w-56">
                <strong class="block text-text">{{ row.name }}</strong>
                <span class="mt-1 block text-xs text-text opacity-60">{{ row.folio }}</span>
            </div>
        </template>

        <template #cell-user_ids="{ row }">
            <AuditReportMultiSelect
                :model-value="selection(row.id, 'user_ids')"
                :options="row.participants || []"
                empty-label="Todos los participantes"
                @update:model-value="updateSelection(row.id, 'user_ids', $event)"
            />
        </template>

        <template #cell-category_ids="{ row }">
            <AuditReportMultiSelect
                :model-value="selection(row.id, 'category_ids')"
                :options="categories"
                empty-label="Todas las categorías"
                @update:model-value="updateSelection(row.id, 'category_ids', $event)"
            />
        </template>

        <template #cell-results="{ row }">
            <AuditReportMultiSelect
                :model-value="selection(row.id, 'results')"
                :options="resultOptions"
                empty-label="Reporte general"
                @update:model-value="updateSelection(row.id, 'results', $event)"
            />
        </template>

        <template #cell-include_lots="{ row }">
            <SelectField
                hide-label
                :model-value="selection(row.id, 'include_lots') ? '1' : '0'"
                :options="lotDetailOptions"
                @update:model-value="updateSelection(row.id, 'include_lots', $event === '1')"
            />
        </template>
    </GlobalTable>
</template>
