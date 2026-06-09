<script setup>
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import { useProductMovementsModal } from '@/Composables/Inventory/useProductMovementsModal'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close'])

const {
    productName,
    unit,
    currentStock,
    totalMovements,
    movementGroups,

    groupClass,
    groupIconClass,
    quantityLabel,
    quantityClass,
    reasonLabel,
    formatNumber,
    formatDateTime,
    userLabel,
    hasNotes,
    movementBatches,
    batchName,
    batchQuantityLabel,
    stockTransition,

    closeModal,
} = useProductMovementsModal(props, emit)
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 backdrop-blur-sm md:items-center">
        <section
            class="flex h-[100dvh] w-full flex-col overflow-hidden bg-white shadow-2xl md:h-auto md:max-h-[92vh] md:max-w-7xl md:rounded-3xl">
            <GeneralModalHeader title="Historial" subtitle="Movimientos clasificados por tipo de operación." mode="view"
                @close="closeModal" />

            <GeneralModalContent :columns="1">
                <div class="flex min-h-0 flex-col gap-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Producto
                                </p>

                                <h3 class="truncate text-lg font-bold text-slate-800">
                                    {{ productName }}
                                </h3>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs text-slate-400">
                                        Stock actual
                                    </p>

                                    <p class="font-bold text-slate-800">
                                        {{ formatNumber(currentStock) }} {{ unit }}
                                    </p>
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs text-slate-400">
                                        Movimientos
                                    </p>

                                    <p class="font-bold text-slate-800">
                                        {{ totalMovements }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="min-h-0 flex-1 overflow-y-auto pr-1">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                            <section v-for="group in movementGroups" :key="group.key"
                                class="flex min-h-[260px] flex-col rounded-2xl border p-3"
                                :class="groupClass(group.key)">
                                <header class="mb-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="material-symbols-outlined flex h-9 w-9 items-center justify-center rounded-xl text-[20px]"
                                            :class="groupIconClass(group.key)">
                                            {{ group.icon }}
                                        </span>

                                        <div>
                                            <h4 class="font-black text-slate-800">
                                                {{ group.title }}
                                            </h4>

                                            <p class="text-xs font-semibold text-slate-500">
                                                {{ group.items.length }} movimientos
                                            </p>
                                        </div>
                                    </div>
                                </header>

                                <div v-if="group.items.length" class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                                    <article v-for="movement in group.items" :key="movement.id"
                                        class="rounded-xl border border-white/70 bg-white p-3 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-xl font-black leading-none"
                                                    :class="quantityClass(movement)">
                                                    {{ quantityLabel(movement) }}
                                                </p>

                                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                                    {{ reasonLabel(movement.reason, movement) }} </p>
                                            </div>

                                            <p class="text-right text-xs font-semibold text-slate-400">
                                                {{ formatDateTime(movement.created_at) }}
                                            </p>
                                        </div>

                                        <div class="mt-3 space-y-2 text-xs">
                                            <div>
                                                <p class="font-semibold uppercase tracking-wide text-slate-400">
                                                    Responsable
                                                </p>

                                                <p class="font-medium text-slate-700">
                                                    {{ userLabel(movement) }}
                                                </p>
                                            </div>

                                            <div v-if="stockTransition(movement)">
                                                <p class="font-semibold uppercase tracking-wide text-slate-400">
                                                    Stock
                                                </p>

                                                <p class="font-medium text-slate-700">
                                                    {{ stockTransition(movement) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div v-if="hasNotes(movement)" class="mt-3 rounded-lg bg-slate-50 px-3 py-2">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                Notas
                                            </p>

                                            <p class="text-xs font-medium text-slate-700">
                                                {{ movement.notes }}
                                            </p>
                                        </div>

                                        <div v-if="movementBatches(movement).length" class="mt-3 space-y-2">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                Lotes
                                            </p>

                                            <div class="space-y-1.5">
                                                <div v-for="movementBatch in movementBatches(movement)"
                                                    :key="movementBatch.id ?? `${movement.id}-${batchName(movementBatch)}`"
                                                    class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-2 py-1.5">
                                                    <span class="truncate text-xs font-semibold text-slate-600">
                                                        {{ batchName(movementBatch) }}
                                                    </span>

                                                    <span class="shrink-0 text-xs font-black text-slate-700">
                                                        {{ batchQuantityLabel(movementBatch, movement) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <div v-else
                                    class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-white/80 bg-white/50 p-4 text-center">
                                    <p class="text-sm font-semibold text-slate-400">
                                        {{ group.empty }}
                                    </p>
                                </div>
                            </section>
                        </div>
                    </section>
                </div>
            </GeneralModalContent>

            <GeneralModalFooter mode="view" @close="closeModal" />
        </section>
    </div>
</template>