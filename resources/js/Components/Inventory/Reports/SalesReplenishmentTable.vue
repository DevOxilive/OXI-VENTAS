<script setup>
import { computed, reactive, ref } from 'vue'
import { TableSurface } from '@/Components/Tables'

const props = defineProps({
    report: {
        type: Object,
        default: () => ({ branches: [], sections: {} }),
    },
    activeTab: {
        type: String,
        default: 'pedido',
    },
})

const emit = defineEmits(['update-section-period'])
const manualValues = reactive({})
const manualRevision = ref(0)

const branches = computed(() => props.report?.branches ?? [])
const sections = computed(() => props.report?.sections?.[props.activeTab] ?? [])
const hasRows = computed(() => sections.value.some((section) => (section.rows ?? []).length > 0))
const isTransferTab = computed(() => props.activeTab === 'transferencias')
const isStoreOrderTab = computed(() => props.activeTab === 'pedido-tiendas')
const showBranchActionColumn = computed(() => !isStoreOrderTab.value)
const tableMinWidth = computed(() => {
    const baseWidth = isTransferTab.value ? 680 : 744
    const branchWidth = branches.value.length * (showBranchActionColumn.value ? 256 : 144)

    return `${baseWidth + branchWidth}px`
})
const emptyColspan = computed(() => {
    const baseColumns = isTransferTab.value ? 7 : 8
    const branchColumns = branches.value.length * (showBranchActionColumn.value ? 3 : 2)

    return baseColumns + branchColumns
})

const tableClasses = {
    scroll: 'max-w-full overflow-x-auto overflow-y-hidden',
    table: 'w-full table-fixed border-collapse text-xs xl:text-sm',
    headRow: 'border-b border-secondary bg-secondary text-text',
    sectionRow: 'border-y border-primary bg-secondary text-text',
    bodyRow: 'border-b border-secondary bg-background odd:bg-secondary transition-colors hover:bg-primary/10',
    headerCell: 'px-2 py-3 font-semibold',
    sectionCell: 'px-2 py-2 font-semibold',
    bodyCell: 'px-2 py-2 align-middle',
    input: 'h-9 w-full min-w-0 rounded-lg border border-secondary bg-background px-2 text-center text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary',
    primaryInput: 'h-9 w-full min-w-0 rounded-lg border border-secondary bg-background px-2 text-center text-sm font-bold text-primary outline-none focus:border-primary focus:ring-2 focus:ring-primary',
}

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}

function quantity(value) {
    const number = Number(value || 0)

    if (!Number.isFinite(number)) return '0'
    if (Math.abs(number - Math.round(number)) < 0.0001) return String(Math.round(number))

    return number.toLocaleString('es-MX', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    })
}

function metricFor(row, branchId, key) {
    return row.branches?.find((branch) => Number(branch.branch_id) === Number(branchId))?.[key] ?? 0
}

function branchFor(row, branchId) {
    return row.branches?.find((branch) => Number(branch.branch_id) === Number(branchId)) ?? null
}

function branchMetricKey() {
    if (props.activeTab === 'sin-movimiento') return 'stock'

    return 'suggested'
}

function branchMetricLabel() {
    if (props.activeTab === 'transferencias') return 'MOV'
    if (props.activeTab === 'sin-movimiento') return 'E'

    return 'PED'
}

function updateSectionPeriod(section, value) {
    if (!section?.key || !value) return

    emit('update-section-period', section.key, value)
}

function manualKey(row, key) {
    return `${row.id}:${key}`
}

function editableValue(row, key, fallback) {
    const keyName = manualKey(row, key)

    if (Object.prototype.hasOwnProperty.call(manualValues, keyName)) {
        return manualValues[keyName]
    }

    return typeof fallback === 'string' ? fallback : quantity(fallback)
}

function updateEditable(row, key, value) {
    manualValues[manualKey(row, key)] = value
    manualRevision.value += 1
}

function observationValue(row) {
    if (isTransferTab.value) {
        return transferObservation(row)
    }

    if (isStoreOrderTab.value) {
        return storeOrderObservation(row)
    }

    return editableValue(row, 'observation', '')
}

function branchTransferValue(row, branchId) {
    const branch = branchFor(row, branchId)
    const transferIn = Number(branch?.transfer_in || 0)
    const transferOut = Number(branch?.transfer_out || 0)

    if (transferOut > 0) {
        return `-${quantity(transferOut)}`
    }

    if (transferIn > 0) {
        return `+${quantity(transferIn)}`
    }

    return '0'
}

function editableBranchTransferValue(row, branchId) {
    return editableValue(row, `branch-${branchId}-transfer`, branchTransferValue(row, branchId))
}

function parseTransferQuantity(value) {
    const normalized = String(value ?? '')
        .replace(',', '.')
        .replace(/[^\d.-]/g, '')
    const number = Number(normalized)

    return Number.isFinite(number) ? number : 0
}

function transferUnit(row) {
    return row.unit || row.transfers?.[0]?.unit || 'pza'
}

function quantityWithUnit(value, unit) {
    return `${quantity(value)} ${unit}`
}

function branchDemand(row, branchId) {
    return Math.max(0, Number(metricFor(row, branchId, 'monthly_sales')) - Number(metricFor(row, branchId, 'stock')))
}

function storeOrderObservation(row) {
    manualRevision.value

    const requested = parseTransferQuantity(editableValue(row, 'total_suggested', row.total_suggested))

    if (requested <= 0) {
        return 'Sin observaciones'
    }

    const receivers = branches.value
        .map((branch) => ({
            branch: branch.name,
            demand: branchDemand(row, branch.id),
            assigned: 0,
        }))
        .filter((branch) => branch.demand > 0)
        .sort((a, b) => b.demand - a.demand)

    if (!receivers.length) {
        return 'Sin observaciones'
    }

    let pending = requested

    for (const receiver of receivers) {
        if (pending <= 0) break

        const assigned = Math.min(receiver.demand, pending)
        receiver.assigned = assigned
        pending -= assigned
    }

    const assignments = receivers.filter((receiver) => receiver.assigned > 0)

    if (!assignments.length) {
        return 'Sin observaciones'
    }

    const sameQuantity = assignments.every((receiver) => receiver.assigned === assignments[0].assigned)
    const includesAllBranches = assignments.length === branches.value.length

    if (sameQuantity && includesAllBranches) {
        return `${quantityWithUnit(assignments[0].assigned, transferUnit(row))} para cada tienda`
    }

    return assignments
        .map((receiver) => `${quantityWithUnit(receiver.assigned, transferUnit(row))} para ${receiver.branch}`)
        .join(', ')
}

function transferObservation(row) {
    manualRevision.value

    const unit = transferUnit(row)
    const donors = []
    const receivers = []

    for (const branch of branches.value) {
        const value = parseTransferQuantity(editableBranchTransferValue(row, branch.id))

        if (value < 0) {
            donors.push({
                branch: branch.name,
                available: Math.abs(value),
            })
        }

        if (value > 0) {
            receivers.push({
                branch: branch.name,
                needed: value,
            })
        }
    }

    if (!donors.length && !receivers.length) {
        return 'Sin observaciones'
    }

    const movements = []

    for (const receiver of receivers) {
        let pending = receiver.needed

        for (const donor of donors) {
            if (pending <= 0) break
            if (donor.available <= 0) continue

            const moved = Math.min(donor.available, pending)

            if (moved <= 0) continue

            movements.push(`${quantityWithUnit(moved, unit)} de ${donor.branch} a ${receiver.branch}`)
            donor.available -= moved
            pending -= moved
        }

        if (pending > 0) {
            movements.push(`${quantityWithUnit(pending, unit)} para ${receiver.branch} sin sucursal origen`)
        }
    }

    for (const donor of donors) {
        if (donor.available > 0) {
            movements.push(`${quantityWithUnit(donor.available, unit)} de ${donor.branch} sin destino`)
        }
    }

    return movements.length ? movements.join(', ') : 'Sin observaciones'
}

function branchStockClasses(row, branchId) {
    const branch = branchFor(row, branchId)

    if (branch?.stock_status === 'shortage') {
        return 'bg-warning/35 text-text'
    }

    if (props.activeTab === 'transferencias' && Number(branch?.transfer_out || 0) > 0) {
        return 'bg-secondary text-text'
    }

    return 'bg-secondary text-text'
}

function branchTransferClasses(row, branchId) {
    const branch = branchFor(row, branchId)

    if (props.activeTab !== 'transferencias') {
        return 'bg-warning/20'
    }

    const transferValue = parseTransferQuantity(editableBranchTransferValue(row, branchId))

    if (transferValue > 0) {
        return 'bg-warning/35 text-text'
    }

    if (transferValue < 0 || Number(branch?.transfer_out || 0) > 0) {
        return 'bg-secondary text-text'
    }

    return 'bg-background text-text opacity-70'
}
</script>

<template>
    <TableSurface>
        <div :class="tableClasses.scroll">
            <table
                :class="tableClasses.table"
                :style="{ minWidth: tableMinWidth }"
            >
                <thead>
                    <tr :class="tableClasses.headRow">
                        <th :class="[tableClasses.headerCell, 'w-36 min-w-36 px-3 text-left']">CODIGO DE BARRAS</th>
                        <th v-if="!isTransferTab" :class="[tableClasses.headerCell, 'w-16 min-w-16 text-center']">P</th>
                        <th :class="[tableClasses.headerCell, 'w-16 min-w-16 text-center']">V</th>
                        <th :class="[tableClasses.headerCell, 'w-16 min-w-16 text-center']">E</th>
                        <th :class="[tableClasses.headerCell, 'w-72 min-w-72 px-3 text-left']">PRODUCTO</th>
                        <template v-for="branch in branches" :key="`head-${branch.id}`">
                            <th :class="[tableClasses.headerCell, 'w-20 min-w-20 text-center']">E {{ branch.name }}</th>
                            <th :class="[tableClasses.headerCell, 'w-16 min-w-16 text-center']">MES</th>
                            <th v-if="showBranchActionColumn" :class="[tableClasses.headerCell, 'w-20 min-w-20 text-center']">{{ branchMetricLabel() }}</th>
                        </template>
                        <th :class="[tableClasses.headerCell, 'w-20 min-w-20 text-center']">P. P.</th>
                        <th :class="[tableClasses.headerCell, 'w-20 min-w-20 text-center']">P.C.</th>
                        <th :class="[tableClasses.headerCell, 'w-56 min-w-56 px-3 text-left']">OBSERVACIONES</th>
                    </tr>
                </thead>

                <tbody v-if="hasRows">
                    <template v-for="section in sections" :key="section.label">
                        <tr :class="tableClasses.sectionRow">
                            <th :class="[tableClasses.sectionCell, 'w-36 min-w-36 px-3 text-center']">CODIGO DE BARRAS</th>
                            <th v-if="!isTransferTab" :class="[tableClasses.sectionCell, 'w-16 min-w-16 text-center']">P</th>
                            <th :class="[tableClasses.sectionCell, 'w-16 min-w-16 text-center']">V</th>
                            <th :class="[tableClasses.sectionCell, 'w-16 min-w-16 text-center']">E</th>
                            <th :class="[tableClasses.sectionCell, 'w-72 min-w-72 px-3 text-left']">
                                <div class="flex flex-col gap-2">
                                    <button
                                        type="button"
                                        class="text-left text-sm font-bold uppercase text-primary underline-offset-4 hover:underline"
                                        title="Cambiar periodo de esta seccion"
                                    >
                                        {{ section.label }}
                                    </button>

                                    <label class="flex items-center gap-2 text-xs font-semibold normal-case text-text opacity-80">
                                        <span>Desde</span>
                                        <input
                                            type="date"
                                            :value="section.period_from"
                                            :max="section.period_to"
                                            class="h-8 min-w-0 rounded-lg border border-secondary bg-background px-2 text-xs font-bold text-text outline-none focus:border-primary"
                                            @change="updateSectionPeriod(section, $event.target.value)"
                                        />
                                    </label>
                                </div>
                            </th>
                            <template v-for="branch in branches" :key="`section-${section.label}-${branch.id}`">
                                <th :class="[tableClasses.sectionCell, 'w-20 min-w-20 text-center']">E {{ branch.name }}</th>
                                <th :class="[tableClasses.sectionCell, 'w-16 min-w-16 text-center']">MES</th>
                                <th v-if="showBranchActionColumn" :class="[tableClasses.sectionCell, 'w-20 min-w-20 text-center']">{{ branchMetricLabel() }}</th>
                            </template>
                            <th :class="[tableClasses.sectionCell, 'w-20 min-w-20 text-center']">P. P.</th>
                            <th :class="[tableClasses.sectionCell, 'w-20 min-w-20 text-center']">P.C.</th>
                            <th :class="[tableClasses.sectionCell, 'w-56 min-w-56 px-3 text-left']">OBSERVACIONES</th>
                        </tr>

                        <tr
                            v-for="row in section.rows"
                            :key="row.id"
                            :class="tableClasses.bodyRow"
                        >
                            <td
                                :class="[tableClasses.bodyCell, 'w-36 min-w-36 max-w-36 truncate px-3 font-medium']"
                                :title="row.code"
                            >
                                {{ row.code }}
                            </td>
                            <td v-if="!isTransferTab" class="w-16 min-w-16 px-1 py-1 text-center font-semibold text-primary">
                                <input
                                    :value="editableValue(row, 'total_suggested', row.total_suggested)"
                                    type="text"
                                    inputmode="decimal"
                                    :class="tableClasses.primaryInput"
                                    @input="updateEditable(row, 'total_suggested', $event.target.value)"
                                />
                            </td>
                            <td class="w-16 min-w-16 px-1 py-1 text-center">
                                <span class="inline-flex h-9 w-full items-center justify-center px-2 text-sm font-semibold text-text">
                                    {{ quantity(row.total_sold) }}
                                </span>
                            </td>
                            <td class="w-16 min-w-16 px-1 py-1 text-center">
                                <span class="inline-flex h-9 w-full items-center justify-center px-2 text-sm font-semibold text-text">
                                    {{ quantity(row.total_stock) }}
                                </span>
                            </td>
                            <td :class="[tableClasses.bodyCell, 'w-72 min-w-72 max-w-72 px-3']">
                                <p class="truncate font-semibold text-text" :title="row.product">{{ row.product }}</p>
                                <p class="truncate text-xs text-text opacity-65" :title="`${row.department} / ${row.category}`">{{ row.department }} / {{ row.category }}</p>
                            </td>
                            <template v-for="branch in branches" :key="`row-${row.id}-${branch.id}`">
                                <td class="w-20 min-w-20 px-2 py-2 text-center font-semibold" :class="branchStockClasses(row, branch.id)">
                                    {{ quantity(metricFor(row, branch.id, 'stock')) }}
                                </td>
                                <td class="w-16 min-w-16 bg-warning/15 px-2 py-2 text-center font-semibold">
                                    {{ quantity(metricFor(row, branch.id, 'monthly_sales')) }}
                                </td>
                                <td v-if="showBranchActionColumn" class="w-20 min-w-20 px-1 py-1 text-center" :class="branchTransferClasses(row, branch.id)">
                                    <span
                                        v-if="isTransferTab"
                                        class="sr-only"
                                    >
                                        {{ branchTransferValue(row, branch.id) }}
                                    </span>
                                    <input
                                        :value="isTransferTab ? editableBranchTransferValue(row, branch.id) : editableValue(row, `branch-${branch.id}-${branchMetricKey()}`, metricFor(row, branch.id, branchMetricKey()))"
                                        type="text"
                                        inputmode="decimal"
                                        :class="tableClasses.input"
                                        @input="updateEditable(row, isTransferTab ? `branch-${branch.id}-transfer` : `branch-${branch.id}-${branchMetricKey()}`, $event.target.value)"
                                    />
                                </td>
                            </template>
                            <td class="w-20 min-w-20 truncate px-2 py-2 text-right" :title="money(row.sale_price)">{{ money(row.sale_price) }}</td>
                            <td class="w-20 min-w-20 truncate px-2 py-2 text-right" :title="money(row.cost)">{{ money(row.cost) }}</td>
                            <td class="w-56 min-w-56 px-2 py-1">
                                <p
                                    v-if="isTransferTab || isStoreOrderTab"
                                    class="px-2 py-2 text-sm font-semibold leading-snug text-text"
                                >
                                    {{ observationValue(row) }}
                                </p>
                                <input
                                    v-else
                                    :value="observationValue(row)"
                                    type="text"
                                    :class="[tableClasses.input, 'text-left']"
                                    @input="updateEditable(row, 'observation', $event.target.value)"
                                />
                            </td>
                        </tr>
                    </template>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td :colspan="emptyColspan" class="px-4 py-12 text-center text-text opacity-70">
                            No hay productos para esta seccion con los filtros seleccionados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </TableSurface>
</template>
