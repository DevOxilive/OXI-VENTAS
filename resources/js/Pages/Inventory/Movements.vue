<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    branchProductsDB: Array,
    movementsDB: Array,
})

const branchProducts = computed(() => props.branchProductsDB ?? [])
const movements = computed(() => props.movementsDB ?? [])

const showModal = ref(false)

const typeOptions = [
    { label: 'Entrada', value: 'IN' },
    { label: 'Salida', value: 'OUT' },
    { label: 'Ajuste', value: 'ADJUSTMENT' },
]

const reasonOptions = [
    { label: 'Compra', value: 'PURCHASE' },
    { label: 'Venta', value: 'SALE' },
    { label: 'Producto dañado', value: 'DAMAGED' },
    { label: 'Producto robado', value: 'STOLEN' },
    { label: 'Producto caducado', value: 'EXPIRED' },
    { label: 'Transferencia', value: 'TRANSFER' },
    { label: 'Ajuste manual', value: 'MANUAL' },
]
const movementLabels = {
    PURCHASE: 'Compra',
    SALE: 'Venta',
    DAMAGED: 'Producto dañado',
    STOLEN: 'Producto robado',
    EXPIRED: 'Producto caducado',
    TRANSFER: 'Transferencia',
    MANUAL: 'Ajuste manual',

    IN: 'Entrada',
    OUT: 'Salida',
    ADJUSTMENT: 'Ajuste manual',
}

function translateMovement(value) {
    return movementLabels[value] || value
}
const form = useForm({
    branch_product_id: '',
    type: 'OUT',
    reason: '',
    quantity: '',
    notes: '',
})

const openModal = () => {
    form.reset()
    form.type = 'OUT'
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submit = () => {
    form.post(route('inventario.stock-movements.store'), {
        preserveScroll: true,

        onSuccess: () => {
            closeModal()
        }
    })
}
</script>

<template>

    <div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">

        <!-- HEADER -->
        <div class="flex items-center justify-between mb-6">

            <div>

                <h1 class="text-2xl font-bold text-[#1f1d2b]">
                    Movimientos de inventario
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Entradas, salidas, robos, daños y caducidades
                </p>

            </div>

            <button @click="openModal"
                class="bg-[#1f1d2b] hover:bg-[#2a2738] text-white px-5 py-3 rounded-xl text-sm font-medium transition">
                Nuevo movimiento
            </button>

        </div>

        <!-- TABLE -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">

            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-slate-100">

                        <tr class="text-left text-sm text-slate-600">

                            <th class="px-5 py-4 font-semibold">
                                Producto
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Sucursal
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Tipo
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Motivo
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Cantidad
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Stock anterior
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Nuevo stock
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Fecha
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr v-for="movement in movements" :key="movement.id"
                            class="border-t border-slate-100 hover:bg-slate-50 transition">

                            <td class="px-5 py-4 font-medium text-slate-800">
                                {{ movement.branch_product?.product?.name }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.branch_product?.branch?.name }}
                            </td>

                            <td class="px-5 py-4">

                                <span class="px-3 py-1 rounded-full text-xs font-medium" :class="movement.type === 'IN'
                                    ? 'bg-green-100 text-green-700'
                                    : movement.type === 'OUT'
                                        ? 'bg-red-100 text-red-700'
                                        : 'bg-yellow-100 text-yellow-700'">
                                    {{ translateMovement(movement.type) }}
                                </span>

                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.reason }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.quantity }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.previous_stock }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.new_stock }}
                            </td>

                            <td class="px-5 py-4 text-slate-500 text-sm">
                                {{ movement.created_at }}
                            </td>

                        </tr>

                        <tr v-if="movements.length === 0">

                            <td colspan="8" class="text-center py-10 text-slate-400">
                                No hay movimientos registrados.
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- MODAL -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">

            <div class="bg-white rounded-3xl w-full max-w-2xl p-6">

                <!-- HEADER -->
                <div class="flex items-center justify-between mb-6">

                    <div>

                        <h2 class="text-xl font-bold text-[#1f1d2b]">
                            Nuevo movimiento
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Registrar movimiento de inventario
                        </p>

                    </div>

                    <button @click="closeModal" class="text-slate-400 hover:text-slate-700 transition">
                        ✕
                    </button>

                </div>

                <!-- FORM -->
                <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <!-- PRODUCT -->
                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Producto / Sucursal
                        </label>

                        <select v-model="form.branch_product_id"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]">

                            <option value="">
                                Selecciona un producto
                            </option>

                            <option v-for="item in branchProducts" :key="item.id" :value="item.id">
                                {{ item.product?.name }} - {{ item.branch?.name }}
                            </option>

                        </select>

                    </div>

                    <!-- TYPE -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Tipo
                        </label>

                        <select v-model="form.type"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]">

                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>

                        </select>

                    </div>

                    <!-- REASON -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Motivo
                        </label>

                        <select v-model="form.reason"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]">

                            <option value="">
                                Selecciona un motivo
                            </option>

                            <option v-for="option in reasonOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>

                        </select>

                    </div>

                    <!-- QUANTITY -->
                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Cantidad
                        </label>

                        <input v-model="form.quantity" type="number" min="1"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]" />

                    </div>

                    <!-- NOTES -->
                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Observaciones
                        </label>

                        <textarea v-model="form.notes" rows="4"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]" />

                    </div>

                    <!-- FOOTER -->
                    <div class="md:col-span-2 flex justify-end gap-3 pt-4">

                        <button type="button" @click="closeModal"
                            class="px-5 py-3 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                            Cancelar
                        </button>

                        <button type="submit" :disabled="form.processing"
                            class="bg-[#1f1d2b] hover:bg-[#2a2738] text-white px-5 py-3 rounded-xl transition">
                            Guardar movimiento
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</template>