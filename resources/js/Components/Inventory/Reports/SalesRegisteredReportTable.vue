<script setup>
import { computed } from 'vue'
import { TableSurface } from '@/Components/Tables'

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: null,
    },
    summary: {
        type: Object,
        default: () => ({
            cash: 0,
            card: 0,
            credit: 0,
            credit_payments: 0,
            sold_total: 0,
            collected_total: 0,
        }),
    },
})

const emit = defineEmits(['page-change'])

const groups = computed(() => {
    const groupedRows = []
    const index = new Map()

    props.rows.forEach((sale) => {
        const date = sale.date_only || 'Sin fecha'

        if (!index.has(date)) {
            index.set(date, {
                date,
                rows: [],
            })
            groupedRows.push(index.get(date))
        }

        index.get(date).rows.push(sale)
    })

    return groupedRows
})

const summaryCards = computed(() => [
    { key: 'cash', label: 'Ventas en efectivo', value: props.summary?.cash ?? 0 },
    { key: 'card', label: 'Ventas con tarjeta', value: props.summary?.card ?? 0 },
    { key: 'credit', label: 'Ventas a credito', value: props.summary?.credit ?? 0 },
    { key: 'credit_payments', label: 'Abonos a credito', value: props.summary?.credit_payments ?? 0 },
    { key: 'sold_total', label: 'Total vendido', value: props.summary?.sold_total ?? 0, strong: true },
    { key: 'collected_total', label: 'Total cobrado', value: props.summary?.collected_total ?? 0, strong: true },
])

const pageLinks = computed(() => {
    const links = props.pagination?.links ?? []

    return links.filter((link) => /^\d+$/.test(normalizeLabel(link.label)))
})

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}

function operationLabel(sale) {
    return sale.operation_type === 'payment'
        ? `Abono ${sale.payment_method || 'Sin metodo'}`
        : `Venta ${sale.payment_method || 'Sin metodo'}`
}

function paymentAmount(sale, detail, target) {
    if (sale.operation_type === 'payment') {
        return target === 'abono' ? Number(detail.report_amount ?? detail.subtotal ?? 0) : 0
    }

    const method = String(sale.payment_method || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
    const amount = Number(detail.report_amount ?? detail.subtotal ?? 0)

    if (target === 'cash') {
        return method.includes('efectivo') ? amount : 0
    }

    if (target === 'card') {
        return (method.includes('tarjeta') || method.includes('debito') || (method.includes('credito') && !method.includes('empleado'))) ? amount : 0
    }

    if (target === 'credit') {
        return !method.includes('efectivo') && !method.includes('tarjeta') && !method.includes('debito') ? amount : 0
    }

    return 0
}

function normalizeLabel(label) {
    return String(label ?? '')
        .replace(/&laquo;|&raquo;/g, '')
        .replace(/<[^>]*>/g, '')
        .replace(/\s+/g, ' ')
        .trim()
}

function goToPage(link) {
    if (!link?.url) return
    emit('page-change', link.url)
}
</script>

<template>
    <TableSurface>
        <div class="max-h-[560px] max-w-full overflow-x-auto overflow-y-auto">
            <table class="min-w-[1180px] w-full border-collapse text-sm">
                <thead>
                    <tr class="sticky top-0 z-20 bg-primary text-white">
                        <th class="px-4 py-3 text-left font-black">Fecha</th>
                        <th class="px-4 py-3 text-left font-black">Usuario</th>
                        <th class="px-4 py-3 text-left font-black">Producto</th>
                        <th class="px-4 py-3 text-right font-black">Pza/Kg</th>
                        <th class="px-4 py-3 text-right font-black">Precio</th>
                        <th class="px-4 py-3 text-right font-black">Importe</th>
                        <th class="px-4 py-3 text-left font-black">Estado</th>
                        <th class="px-4 py-3 text-right font-black">Efectivo</th>
                        <th class="px-4 py-3 text-right font-black">Tarjeta</th>
                        <th class="px-4 py-3 text-right font-black">Credito</th>
                        <th class="px-4 py-3 text-right font-black">Abono</th>
                    </tr>
                </thead>

                <tbody>
                    <template
                        v-for="group in groups"
                        :key="group.date"
                    >
                        <tr class="bg-[#F2E5BD] text-[#8A6100]">
                            <td class="border-b border-primary/20 px-4 py-2 font-black">{{ group.date }}</td>
                            <td
                                class="border-b border-primary/20 px-4 py-2 font-black"
                                colspan="10"
                            >
                                Fecha de operacion: {{ group.date }}
                            </td>
                        </tr>

                        <template
                            v-for="sale in group.rows"
                            :key="sale.id"
                        >
                            <tr class="bg-[#F2DFE2] text-[#241719]">
                                <td class="border-b border-primary/20 px-4 py-2">{{ sale.date_only }}</td>
                                <td class="border-b border-primary/20 px-4 py-2 font-black">Numero de ticket:</td>
                                <td class="border-b border-primary/20 px-4 py-2 font-black">{{ sale.folio }}</td>
                                <td class="border-b border-primary/20 px-4 py-2">{{ operationLabel(sale) }}</td>
                                <td class="border-b border-primary/20 px-4 py-2">Sucursal:</td>
                                <td class="border-b border-primary/20 px-4 py-2">{{ sale.branch }}</td>
                                <td class="border-b border-primary/20 px-4 py-2">Caja:</td>
                                <td class="border-b border-primary/20 px-4 py-2">{{ sale.cash_box }}</td>
                                <td class="border-b border-primary/20 px-4 py-2 font-black">Importe total:</td>
                                <td
                                    class="border-b border-primary/20 px-4 py-2 text-right font-black"
                                    colspan="2"
                                >
                                    {{ money(sale.total) }}
                                </td>
                            </tr>

                            <tr
                                v-for="detail in sale.details || []"
                                :key="`${sale.id}-${detail.id}`"
                                class="odd:bg-background even:bg-secondary"
                            >
                                <td class="border-b border-secondary px-4 py-2">{{ sale.date_only }}</td>
                                <td class="border-b border-secondary px-4 py-2">{{ sale.seller }}</td>
                                <td class="border-b border-secondary px-4 py-2">{{ detail.product }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ detail.quantity_display }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ money(detail.unit_price) }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ money(detail.report_amount ?? detail.subtotal) }}</td>
                                <td class="border-b border-secondary px-4 py-2">{{ sale.status_label }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ money(paymentAmount(sale, detail, 'cash')) }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ money(paymentAmount(sale, detail, 'card')) }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ money(paymentAmount(sale, detail, 'credit')) }}</td>
                                <td class="border-b border-secondary px-4 py-2 text-right">{{ money(paymentAmount(sale, detail, 'abono')) }}</td>
                            </tr>
                        </template>
                    </template>

                    <tr v-if="!groups.length">
                        <td
                            colspan="11"
                            class="px-4 py-10 text-center text-text opacity-70"
                        >
                            No hay ventas registradas que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 border-t border-secondary bg-secondary p-4 md:grid-cols-3 xl:grid-cols-6">
            <article
                v-for="card in summaryCards"
                :key="card.key"
                class="rounded-lg border border-secondary bg-background px-4 py-3"
            >
                <p class="text-xs font-black uppercase tracking-wide text-text opacity-60">{{ card.label }}</p>
                <p
                    class="mt-1 text-lg font-black"
                    :class="card.strong ? 'text-primary' : 'text-text'"
                >
                    {{ money(card.value) }}
                </p>
            </article>
        </div>

        <footer
            v-if="pagination"
            class="flex flex-col gap-3 border-t border-secondary bg-secondary px-4 py-4 md:flex-row md:items-center md:justify-between"
        >
            <p class="text-center text-sm text-text opacity-70 md:text-left">
                Pagina {{ pagination?.current_page ?? 1 }} de {{ pagination?.last_page ?? 1 }}
                <span class="hidden md:inline"> - </span>
                <span class="block md:inline">Total: {{ pagination?.total ?? rows.length }} registros</span>
            </p>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <button
                    type="button"
                    :disabled="!pagination?.prev_page_url"
                    class="min-w-9 inline-flex items-center justify-center rounded-lg border border-secondary bg-background px-3 py-2 text-sm text-text transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40"
                    @click="goToPage({ url: pagination.prev_page_url })"
                >
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                </button>

                <button
                    v-for="link in pageLinks"
                    :key="`${normalizeLabel(link.label)}-${link.url ?? 'current'}`"
                    type="button"
                    :disabled="!link.url && !link.active"
                    class="min-w-9 rounded-lg border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="link.active
                        ? 'border-primary bg-primary text-white'
                        : 'border-secondary bg-background text-text hover:bg-secondary'"
                    @click="goToPage(link)"
                >
                    {{ normalizeLabel(link.label) }}
                </button>

                <button
                    type="button"
                    :disabled="!pagination?.next_page_url"
                    class="min-w-9 inline-flex items-center justify-center rounded-lg border border-secondary bg-background px-3 py-2 text-sm text-text transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40"
                    @click="goToPage({ url: pagination.next_page_url })"
                >
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </button>
            </div>
        </footer>
    </TableSurface>
</template>
