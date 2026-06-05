<script setup>
import InputField from '@/Components/Forms/InputField.vue'

defineProps({
    form: Object,
    frontendErrors: Object,
    processing: Boolean,
    usesLot: Boolean,
    isSeasonal: Boolean,
    calculatedQuantity: Number,
    adjustmentText: String,
    quantityResultColor: String,
    toggleLot: Function,
    setAdjustmentType: Function,
    validateField: Function,
})
</script>

<template>
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <section class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-black text-slate-800">Información del lote</p>

                <div class="mt-4 space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-slate-800">¿Tiene lote?</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Actívalo si este registro debe manejar número de lote.
                                </p>
                            </div>

                            <button type="button" :disabled="processing"
                                class="relative inline-flex h-8 w-16 items-center rounded-full transition disabled:opacity-50 shrink-0"
                                :class="usesLot ? 'bg-[#1f1d2b]' : 'bg-slate-300'" @click="toggleLot">
                                <span class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                    :class="usesLot ? 'translate-x-9' : 'translate-x-1'" />

                                <span class="absolute text-[10px] font-black uppercase"
                                    :class="usesLot ? 'left-3 text-white' : 'right-3 text-slate-600'">
                                    {{ usesLot ? 'Sí' : 'No' }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <InputField v-if="usesLot" v-model="form.lot_number" label="Número de lote"
                        placeholder="Ej. LALA-001" field="lot_number" :readonly="processing"
                        :error="frontendErrors.lot_number" @validate="validateField" />

                    <InputField v-model="form.supplier" label="Proveedor" placeholder="Opcional" field="supplier"
                        :readonly="processing" :error="frontendErrors.supplier" @validate="validateField" />

                    <InputField v-model="form.received_at" label="Fecha de ingreso" field="received_at" type="date"
                        :readonly="processing" :error="frontendErrors.received_at" @validate="validateField" />

                    <InputField v-model="form.expiration_date" label="Fecha de caducidad" field="expiration_date"
                        type="date" :readonly="processing" :error="frontendErrors.expiration_date"
                        @validate="validateField" />

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <label class="block text-sm font-black text-slate-800 mb-1">
                            Nota del ajuste
                        </label>

                        <p class="text-xs text-slate-500 mb-3">
                            Esta nota quedará registrada en el historial de movimientos.
                        </p>

                        <textarea v-model="form.notes" :readonly="processing" rows="4" maxlength="500"
                            placeholder="Ej. Corrección por error de captura..."
                            class="w-full resize-none rounded-2xl border-slate-300 text-sm font-semibold focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500"
                            @input="validateField('notes')"></textarea>

                        <div class="flex items-center justify-between mt-1">
                            <p v-if="frontendErrors.notes" class="text-xs font-semibold text-red-600">
                                {{ frontendErrors.notes }}
                            </p>

                            <p class="ml-auto text-[11px] font-bold text-slate-400">
                                {{ form.notes.length }}/500
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-black text-slate-800">Configuración del lote</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">
                            Estado del lote
                        </label>

                        <select v-model="form.status" :disabled="processing"
                            class="w-full rounded-2xl border-slate-300 text-sm font-semibold focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500"
                            @change="validateField('status')">
                            <option value="ACTIVE">Activo</option>
                            <option value="INACTIVE">Inactivo</option>
                            <option value="SEASONAL">Temporada</option>
                        </select>

                        <p v-if="frontendErrors.status" class="text-xs font-semibold text-red-600 mt-1">
                            {{ frontendErrors.status }}
                        </p>
                    </div>

                    <div v-if="isSeasonal" class="space-y-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <InputField v-model="form.season_start_date" label="Inicio de temporada"
                            field="season_start_date" type="date" :readonly="processing"
                            :error="frontendErrors.season_start_date" @validate="validateField" />

                        <InputField v-model="form.season_end_date" label="Fin de temporada" field="season_end_date"
                            type="date" :readonly="processing" :error="frontendErrors.season_end_date"
                            @validate="validateField" />
                    </div>

                    <div v-else class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-sm font-semibold text-slate-700">
                            Selecciona “Temporada” para habilitar las fechas.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-black text-slate-800">Ajuste de cantidad</p>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-200 p-1">
                        <button type="button" :disabled="processing"
                            class="rounded-xl px-4 py-2 text-sm font-black transition disabled:opacity-50" :class="form.adjustment_type === 'ADD'
                                ? 'bg-white text-emerald-700 shadow'
                                : 'text-slate-500'" @click="setAdjustmentType('ADD')">
                            Agregar
                        </button>

                        <button type="button" :disabled="processing"
                            class="rounded-xl px-4 py-2 text-sm font-black transition disabled:opacity-50" :class="form.adjustment_type === 'REMOVE'
                                ? 'bg-white text-red-700 shadow'
                                : 'text-slate-500'" @click="setAdjustmentType('REMOVE')">
                            Eliminar
                        </button>
                    </div>

                    <InputField v-model="form.adjustment_amount" label="Cantidad" placeholder="Ej. 2"
                        field="adjustment_amount" type="number" :readonly="processing"
                        :error="frontendErrors.adjustment_amount" @validate="validateField('adjustment_amount')" />

                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs text-slate-500">Cantidad actual</p>
                        <p class="text-3xl font-black text-slate-900">{{ form.original_quantity }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs text-slate-500">Total después del ajuste</p>
                        <p class="text-3xl font-black" :class="quantityResultColor">
                            {{ calculatedQuantity }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-sm font-semibold">
                            {{ adjustmentText }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </section>
</template>