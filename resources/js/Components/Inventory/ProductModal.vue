<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const props = defineProps({
    mode: String,
    product: Object,

    categoriesDB: {
        type: Array,
        default: () => []
    },

    subcategoriesDB: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close'])

const form = useForm({
    name: '',
    description: '',
    cost: '',
    sale_price: '',
    category_id: '',
    subcategory_id: '',

    barcode: '',
    barcode_type: 'EAN13',
    base_quantity: 1,

    initial_stock: 0,
    minimum_stock: 0,
    maximum_stock: 0,

    active: true,
})

const visibleSubcategories = computed(() => {
    if (!form.category_id) return []

    return props.subcategoriesDB.filter((subcategory) => {
        return String(subcategory.category_id) === String(form.category_id)
    })
})

watch(
    () => props.product,
    (product) => {
        form.reset()

        if (!product) return

        form.name = product.name ?? ''
        form.description = product.presentation ?? ''
        form.cost = product.cost ?? ''
        form.sale_price = product.price ?? ''
        form.category_id = product.category_id ?? ''
        form.subcategory_id = product.subcategory_id ?? ''

        form.barcode = product.barcode ?? ''
        form.barcode_type = product.barcode_type ?? 'EAN13'
        form.base_quantity = product.base_quantity ?? 1

        form.initial_stock = product.stock ?? 0
        form.minimum_stock = product.minimum_stock ?? 0
        form.maximum_stock = product.maximum_stock ?? 0

        form.active = true
    },
    { immediate: true }
)

watch(
    () => form.category_id,
    () => {
        if (
            form.subcategory_id &&
            !visibleSubcategories.value.some(
                subcategory => String(subcategory.id) === String(form.subcategory_id)
            )
        ) {
            form.subcategory_id = ''
        }
    }
)

function submit() {
    if (props.mode === 'create') {
        form.post(route('inventory.products.store'), {
            preserveScroll: true,
            onSuccess: () => emit('close')
        })

        return
    }

    if (props.mode === 'edit') {
        form.put(route('inventory.products.update', props.product.id), {
            preserveScroll: true,
            onSuccess: () => emit('close')
        })
    }
}
</script>

<template>
    <div class="fixed inset-0 bg-black/40 z-[9999] flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden">

            <div class="flex items-center justify-between px-6 py-5 border-b">
                <h2 class="text-2xl font-bold text-slate-800">
                    {{
                        mode === 'create'
                            ? 'Nuevo producto'
                            : mode === 'edit'
                                ? 'Editar producto'
                                : 'Ver producto'
                    }}
                </h2>

                <button
                    @click="$emit('close')"
                    class="text-slate-400 hover:text-slate-700 text-2xl"
                >
                    ×
                </button>
            </div>

            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Nombre</label>
                        <input
                            v-model="form.name"
                            :disabled="mode === 'view'"
                            type="text"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Categoría</label>
                        <select
                            v-model="form.category_id"
                            :disabled="mode === 'view'"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        >
                            <option value="">Selecciona categoría</option>
                            <option
                                v-for="category in categoriesDB"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Subcategoría</label>
                        <select
                            v-model="form.subcategory_id"
                            :disabled="mode === 'view' || !form.category_id"
                            class="w-full mt-2 rounded-2xl border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        >
                            <option value="">Selecciona subcategoría</option>
                            <option
                                v-for="subcategory in visibleSubcategories"
                                :key="subcategory.id"
                                :value="subcategory.id"
                            >
                                {{ subcategory.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Costo</label>
                        <input
                            v-model="form.cost"
                            :disabled="mode === 'view'"
                            type="number"
                            step="0.01"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Precio de venta</label>
                        <input
                            v-model="form.sale_price"
                            :disabled="mode === 'view'"
                            type="number"
                              autofocus
                            step="0.01"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Código de barras</label>
                        <input
                            v-model="form.barcode"
                            :disabled="mode === 'view'"
                            type="text"
                            placeholder="Escanea o escribe el código"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>


                    <div>
                        <label class="text-sm font-semibold text-slate-500">Cantidad base</label>
                        <input
                            v-model="form.base_quantity"
                            :disabled="mode === 'view'"
                            type="number"
                            min="1"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Stock inicial</label>
                        <input
                            v-model="form.initial_stock"
                            :disabled="mode === 'view'"
                            type="number"
                            min="0"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Stock mínimo</label>
                        <input
                            v-model="form.minimum_stock"
                            :disabled="mode === 'view'"
                            type="number"
                            min="0"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-500">Stock máximo</label>
                        <input
                            v-model="form.maximum_stock"
                            :disabled="mode === 'view'"
                            type="number"
                            min="0"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                    <div class="md:col-span-3">
                        <label class="text-sm font-semibold text-slate-500">Descripción</label>
                        <textarea
                            v-model="form.description"
                            :disabled="mode === 'view'"
                            rows="4"
                            class="w-full mt-2 rounded-2xl border-slate-200"
                        />
                    </div>

                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-5 border-t">
                <button
                    @click="$emit('close')"
                    class="px-5 py-3 rounded-2xl bg-slate-200 text-slate-700 font-semibold"
                >
                    Cancelar
                </button>

                <button
                    v-if="mode !== 'view'"
                    @click="submit"
                    class="px-5 py-3 rounded-2xl bg-slate-900 text-white font-semibold"
                >
                    {{ mode === 'create' ? 'Crear producto' : 'Actualizar producto' }}
                </button>
            </div>

        </div>
    </div>
</template>