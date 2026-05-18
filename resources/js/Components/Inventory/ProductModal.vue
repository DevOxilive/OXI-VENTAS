<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    mode: String,
    product: Object,

    categoriesDB: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close'])

const form = useForm({
    name: '',
    description: '',
    price: '',
    category_id: '',
    active: true,
})

watch(
    () => props.product,
    (product) => {

        if (!product) return

        form.name = product.name ?? ''
        form.description = product.presentation ?? ''
        form.price = product.price ?? ''
        form.category_id = product.category_id ?? ''
        form.active = true

    },
    { immediate: true }
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

        form.put(
            route('inventory.products.update', props.product.id),
            {
                preserveScroll: true,
                onSuccess: () => emit('close')
            }
        )
    }
}
</script>

<template>

    <div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">

        <div class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">

            <!-- HEADER -->
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

            <!-- BODY -->
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="text-sm font-semibold text-slate-500">
                        Nombre
                    </label>

                    <input
                        v-model="form.name"
                        :disabled="mode === 'view'"
                        type="text"
                        class="w-full mt-2 rounded-2xl border-slate-200"
                    />
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-500">
                        Precio
                    </label>

                    <input
                        v-model="form.price"
                        :disabled="mode === 'view'"
                        type="number"
                        step="0.01"
                        class="w-full mt-2 rounded-2xl border-slate-200"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold text-slate-500">
                        Descripción
                    </label>

                    <textarea
                        v-model="form.description"
                        :disabled="mode === 'view'"
                        rows="4"
                        class="w-full mt-2 rounded-2xl border-slate-200"
                    />
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-500">
                        Categoría
                    </label>

                    <select
                        v-model="form.category_id"
                        :disabled="mode === 'view'"
                        class="w-full mt-2 rounded-2xl border-slate-200"
                    >
                        <option value="">
                            Selecciona categoría
                        </option>

                        <option
                            v-for="category in categoriesDB"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>

            </div>

            <!-- FOOTER -->
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
                    {{
                        mode === 'create'
                            ? 'Crear producto'
                            : 'Actualizar producto'
                    }}
                </button>

            </div>

        </div>

    </div>

</template>