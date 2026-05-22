<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

defineProps({
    form: Object,
    product: Object,
    frontendErrors: Object,
    typeOptions: Array,
    reasonOptions: Array,
    currentStock: Number,
    projectedStock: Number,
    recentMovements: {
        type: Array,
        default: () => [],
    },
    totalBatchQuantity: {
        type: Number,
        default: 0,
    },
    totalManualBatchQuantity: {
        type: Number,
        default: 0,
    },
})

function movementTypeLabel(type) {
    return {
        IN: 'Entrada',
        OUT: 'Salida',
        ADJUSTMENT: 'Ajuste manual',
    }[type] || type
}

function movementReasonLabel(reason) {
    return {
        PURCHASE: 'Compra',
        SALE: 'Venta',
        DAMAGED: 'Producto dañado',
        STOLEN: 'Producto robado',
        EXPIRED: 'Producto caducado',
        TRANSFER: 'Transferencia',
        MANUAL: 'Ajuste manual',
    }[reason] || reason
}

defineEmits([
    'validate',
    'add-batch',
    'remove-batch',
    'add-manual-batch',
    'remove-manual-batch',
])
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 xl:gap-5">
            <section class="xl:col-span-2 space-y-3">
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Producto
                </h3>

                <div class="space-y-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            Producto seleccionado
                        </p>

                        <p class="mt-1 text-sm font-black text-slate-900 truncate">
                            {{ product.name }}
                        </p>

                        <p class="text-xs text-slate-500 truncate">
                            {{ product.code || 'Sin código registrado' }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">
                                Stock actual
                            </span>

                            <span class="text-lg font-black text-slate-900">
                                {{ currentStock }}
                            </span>
                        </div>

                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-xs text-slate-500">
                                Stock final
                            </span>

                            <span class="text-lg font-black"
                                :class="projectedStock < 0 ? 'text-red-600' : 'text-emerald-600'">
                                {{ projectedStock }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="xl:col-span-3">
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Movimiento de inventario
                </h3>

                <form id="adjustStockForm" class="space-y-6" @submit.prevent>
                    <div class="grid grid-cols-1 gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <SelectField label="Tipo de movimiento" field="type" v-model="form.type" :options="typeOptions"
                            :error="frontendErrors.type || form.errors.type" @validate="$emit('validate', 'type')" />

                        <SelectField label="Motivo" field="reason" v-model="form.reason" :options="reasonOptions"
                            :error="frontendErrors.reason || form.errors.reason"
                            @validate="$emit('validate', 'reason')" />

                        <InputField label="Cantidad" field="quantity" type="number" v-model="form.quantity"
                            :error="frontendErrors.quantity || form.errors.quantity"
                            @validate="$emit('validate', 'quantity')" />

                        <TextareaField label="Notas" field="notes" v-model="form.notes"
                            :error="frontendErrors.notes || form.errors.notes" @validate="$emit('validate', 'notes')" />
                    </div>

                    <div v-if="frontendErrors.stock || form.errors.stock"
                        class="bg-red-50 text-red-700 border border-red-200 rounded-2xl px-4 py-3 text-sm">
                        {{ frontendErrors.stock || form.errors.stock }}
                    </div>
                </form>
            </section>

            <section class="xl:col-span-4">
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Lotes y caducidad
                </h3>

                <div v-if="form.type === 'IN'" class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h3 class="font-black text-slate-900">
                                Lotes y caducidad
                            </h3>

                            <p class="text-sm text-slate-500">
                                Registra uno o varios lotes para esta entrada.
                            </p>
                        </div>

                        <button type="button"
                            class="px-4 py-2 rounded-xl bg-[#1f1d2b] text-white text-sm font-bold hover:bg-[#2d2a3d] transition"
                            @click="$emit('add-batch')">
                            + Agregar lote
                        </button>
                    </div>

                    <div v-if="product.batches?.length" class="mb-5">
                        <p class="text-sm font-bold text-slate-700 mb-2">
                            Lotes actuales del producto
                        </p>

                        <div class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto pr-1">
                            <div v-for="batch in product.batches" :key="batch.id"
                                class="rounded-2xl border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-black text-sm text-slate-800">
                                            {{ batch.lot_number || 'Sin lote' }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            Caduca: {{ batch.expiration_date || 'Sin fecha' }}
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-xs text-slate-500">
                                            Disponible
                                        </p>

                                        <p class="text-xl font-black text-slate-900">
                                            {{ batch.quantity }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!form.batches?.length"
                        class="mb-5 rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                        <p class="text-sm font-bold text-slate-600">
                            Sin lotes registrados
                        </p>

                        <p class="text-xs text-slate-400 mt-1">
                            Este producto aún no tiene lotes en esta sucursal. Puedes agregar uno nuevo con el botón
                            superior.
                        </p>
                    </div>

                    <div v-if="form.batches?.length" class="space-y-4">
                        <div v-for="(batch, index) in form.batches" :key="index"
                            class="bg-white border border-slate-200 rounded-2xl p-4">
                            <div class="flex items-center justify-between mb-4">
                                <p class="font-bold text-slate-800">
                                    Lote #{{ index + 1 }}
                                </p>

                                <button type="button" class="text-sm font-bold text-red-500 hover:text-red-700"
                                    @click="$emit('remove-batch', index)">
                                    Quitar
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2 gap-3">
                                <InputField v-model="batch.lot_number" label="Número de lote"
                                    placeholder="Ej. LALA-001" />

                                <InputField v-model="batch.expiration_date" label="Fecha de caducidad" type="date" />

                                <InputField v-model="batch.quantity" label="Cantidad" type="number" />

                                <InputField v-model="batch.supplier" label="Proveedor" placeholder="Ej. Lala" />
                            </div>

                            <p v-if="frontendErrors[`batch_${index}`]" class="text-sm text-red-600 font-semibold mt-3">
                                {{ frontendErrors[`batch_${index}`] }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-between bg-white border border-slate-200 rounded-2xl px-4 py-3">
                            <span class="text-sm font-bold text-slate-600">
                                Total registrado en lotes
                            </span>

                            <span class="text-sm font-black" :class="Number(totalBatchQuantity) === Number(form.quantity)
                                ? 'text-emerald-600'
                                : 'text-red-600'">
                                {{ totalBatchQuantity }} / {{ form.quantity || 0 }}
                            </span>
                        </div>

                        <p v-if="frontendErrors.batches" class="text-sm text-red-600 font-semibold">
                            {{ frontendErrors.batches }}
                        </p>
                    </div>
                </div>

                <div v-if="form.type === 'OUT'" class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="mb-4">
                        <h3 class="font-black text-slate-900">
                            Selección de lote
                        </h3>

                        <p class="text-sm text-slate-500">
                            Puedes dejar que el sistema sugiera por FEFO o confirmar manualmente los lotes
                            afectados.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 2xl:grid-cols-2 gap-3 mb-5">
                        <button type="button" class="rounded-2xl border px-4 py-3 text-left transition" :class="form.batch_allocation_method === 'FEFO_AUTO'
                            ? 'border-[#1f1d2b] bg-[#1f1d2b] text-white'
                            : 'border-slate-200 bg-white text-slate-700'"
                            @click="form.batch_allocation_method = 'FEFO_AUTO'; form.manual_batches = []">
                            <p class="font-black text-sm">
                                FEFO automático
                            </p>

                            <p class="text-xs opacity-70">
                                Descuenta primero del lote más próximo a caducar como sugerencia del sistema.
                            </p>
                        </button>

                        <button type="button" class="rounded-2xl border px-4 py-3 text-left transition" :class="form.batch_allocation_method === 'MANUAL'
                            ? 'border-[#1f1d2b] bg-[#1f1d2b] text-white'
                            : 'border-slate-200 bg-white text-slate-700'"
                            @click="form.batch_allocation_method = 'MANUAL'">
                            <p class="font-black text-sm">
                                Selección manual
                            </p>

                            <p class="text-xs opacity-70">
                                Confirma exactamente qué lote se está afectando.
                            </p>
                        </button>
                    </div>

                    <div>
                        <p class="text-sm font-bold text-slate-700 mb-2">
                            Lotes disponibles
                        </p>

                        <div v-if="product.batches?.length"
                            class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto pr-1">
                            <button v-for="batch in product.batches" :key="batch.id" type="button"
                                class="rounded-2xl border border-slate-200 bg-white p-4 text-left transition" :class="form.batch_allocation_method === 'MANUAL'
                                    ? 'hover:border-[#1f1d2b]'
                                    : 'cursor-default'" @click="form.batch_allocation_method === 'MANUAL'
                                        ? $emit('add-manual-batch', batch)
                                        : null">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-black text-slate-800">
                                            {{ batch.lot_number || 'Sin lote' }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            Caduca: {{ batch.expiration_date || 'Sin fecha' }}
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-xs text-slate-500">
                                            Disponible
                                        </p>

                                        <p class="text-2xl font-black text-slate-900">
                                            {{ batch.quantity }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="form.batch_allocation_method === 'FEFO_AUTO'"
                                    class="mt-3 flex items-center justify-between">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-black text-emerald-700">
                                        FEFO sugerido
                                    </span>

                                    <span class="text-xs text-slate-400">
                                        Ordenado por caducidad
                                    </span>
                                </div>

                                <div v-if="form.batch_allocation_method === 'MANUAL'"
                                    class="mt-3 text-xs font-bold text-[#1f1d2b]">
                                    Click para usar este lote
                                </div>
                            </button>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                            <p class="text-sm font-bold text-slate-600">
                                Sin lotes disponibles
                            </p>
                        </div>
                    </div>

                    <div v-if="form.batch_allocation_method === 'MANUAL'" class="space-y-4 mt-5">
                        <div v-if="form.manual_batches?.length" class="space-y-3">
                            <p class="text-sm font-bold text-slate-700">
                                Lotes seleccionados
                            </p>

                            <div v-for="(batch, index) in form.manual_batches" :key="batch.product_batch_id || index"
                                class="bg-white border border-slate-200 rounded-2xl p-4">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <div>
                                        <p class="font-black text-slate-800">
                                            {{ batch.lot_number || 'Sin lote' }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            Disponible: {{ batch.available_quantity }}
                                        </p>
                                    </div>

                                    <button type="button" class="text-sm font-bold text-red-500"
                                        @click="$emit('remove-manual-batch', index)">
                                        Quitar
                                    </button>
                                </div>

                                <InputField v-model="batch.quantity" label="Cantidad a descontar" type="number" />

                                <p v-if="frontendErrors[`manual_batch_${index}`]"
                                    class="text-sm text-red-600 font-semibold mt-2">
                                    {{ frontendErrors[`manual_batch_${index}`] }}
                                </p>
                            </div>

                            <div
                                class="flex items-center justify-between bg-white border border-slate-200 rounded-2xl px-4 py-3">
                                <span class="text-sm font-bold text-slate-600">
                                    Total seleccionado
                                </span>

                                <span class="text-sm font-black" :class="Number(totalManualBatchQuantity) === Number(form.quantity)
                                    ? 'text-emerald-600'
                                    : 'text-red-600'">
                                    {{ totalManualBatchQuantity }} / {{ form.quantity || 0 }}
                                </span>
                            </div>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                            <p class="text-sm font-bold text-slate-600">
                                Selecciona uno o más lotes
                            </p>

                            <p class="text-xs text-slate-400 mt-1">
                                Haz click sobre un lote disponible para agregarlo.
                            </p>
                        </div>

                        <p v-if="frontendErrors.manual_batches" class="text-sm text-red-600 font-semibold">
                            {{ frontendErrors.manual_batches }}
                        </p>
                    </div>
                </div>
            </section>

            <aside class="xl:col-span-3 border-t xl:border-t-0 xl:border-l border-slate-200 pt-5 xl:pt-0 xl:pl-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-base text-slate-900">
                            Movimientos recientes
                        </h3>

                        <p class="text-sm text-slate-500">
                            Últimos movimientos registrados para este producto
                        </p>
                    </div>
                </div>

                <div v-if="recentMovements.length" class="space-y-3">
                    <div v-for="movement in recentMovements" :key="movement.id"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-black px-2 py-1 rounded-full" :class="{
                                'bg-emerald-100 text-emerald-700': movement.type === 'IN',
                                'bg-red-100 text-red-700': movement.type === 'OUT',
                                'bg-blue-100 text-blue-700': movement.type === 'ADJUSTMENT',
                            }">
                                {{ movementTypeLabel(movement.type) }}
                            </span>

                            <span class="text-xs text-slate-400">
                                #{{ movement.id }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm font-black text-slate-900">
                            {{ movementReasonLabel(movement.reason) }}
                        </p>

                        <p class="text-xs text-slate-500 mt-1">
                            Movimiento {{ movementTypeLabel(movement.type).toLowerCase() }} por {{ movement.quantity }}
                            unidad(es).
                        </p>

                        <p class="text-xs text-slate-500 mt-1">
                            Stock: {{ movement.previous_stock }} → {{ movement.new_stock }}
                        </p>

                        <p class="text-xs text-slate-400 mt-2 truncate">
                            {{ movement.user?.name || 'Usuario no disponible' }}
                        </p>
                    </div>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <p class="text-sm font-semibold text-slate-600">
                        Sin movimientos recientes
                    </p>

                    <p class="text-xs text-slate-400 mt-1">
                        Cuando ajustes el stock, aparecerán aquí.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>