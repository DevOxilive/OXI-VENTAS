<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

defineOptions({
    layout: AdminLayout
})

const branchFilter = ref('')

const filteredBranchProducts = computed(() => {

    if (!branchFilter.value) {
        return branchProducts.value
    }

    return branchProducts.value.filter(item =>
        item.branch_id == branchFilter.value
    )
})

const props = defineProps({
    branchProductsDB: Array,
    productsDB: Array,
    branchesDB: Array,
})

const branchProducts = computed(() => props.branchProductsDB ?? [])
const products = computed(() => props.productsDB ?? [])
const branches = computed(() => props.branchesDB ?? [])

const showModal = ref(false)

const form = useForm({
    branch_id: '',
    product_id: '',
    price: '',
    stock: '',
    min_stock: '',
})

const openModal = () => {
    form.reset()
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submit = () => {
    form.post(route('inventario.branch-inventory.store'), {
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
                    Inventario por sucursal
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Gestión de productos asignados a sucursales
                </p>
            </div>

            <button @click="openModal"
                class="bg-[#1f1d2b] hover:bg-[#2a2738] text-white px-5 py-3 rounded-xl text-sm font-medium transition">
                Asignar producto
            </button>

        </div>


        <div class="flex items-center gap-3 mb-5">

            <select v-model="branchFilter"
                class="rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]">
                <option value="">
                    Todas las sucursales
                </option>

                <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                    {{ branch.name }}
                </option>

            </select>

        </div>

        <!-- TABLE -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-slate-200">

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
                                Precio
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Stock
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Stock mínimo
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Estado
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr v-for="item in filteredBranchProducts"   :key="item.id"
                            class="border-t border-slate-100 hover:bg-slate-50 transition">

                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-800">
                                    {{ item.product?.name }}
                                </div>
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ item.branch?.name }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                ${{ item.price }}
                            </td>

                            <td class="px-5 py-4">

                                <span class="px-3 py-1 rounded-full text-xs font-medium" :class="item.stock <= item.min_stock
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-green-100 text-green-700'">
                                    {{ item.stock }}
                                </span>

                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ item.min_stock }}
                            </td>

                            <td class="px-5 py-4">

                                <span class="px-3 py-1 rounded-full text-xs font-medium" :class="item.active
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-slate-200 text-slate-600'">
                                    {{ item.active ? 'Activo' : 'Inactivo' }}
                                </span>

                            </td>

                        </tr>

                        <tr v-if="branchProducts.length === 0">

                            <td colspan="6" class="text-center py-10 text-slate-400">
                                No hay productos asignados.
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
                            Asignar producto
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Configura inventario por sucursal
                        </p>

                    </div>

                    <button @click="closeModal" class="text-slate-400 hover:text-slate-700 transition">
                        ✕
                    </button>

                </div>

                <!-- FORM -->
                <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <!-- PRODUCT -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Producto
                        </label>

                        <select v-model="form.product_id"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]">

                            <option value="">
                                Selecciona un producto
                            </option>

                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }}
                            </option>

                        </select>

                    </div>

                    <!-- BRANCH -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Sucursal
                        </label>

                        <select v-model="form.branch_id"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]">

                            <option value="">
                                Selecciona una sucursal
                            </option>

                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>

                        </select>

                    </div>

                    <!-- PRICE -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Precio
                        </label>

                        <input v-model="form.price" type="number" step="0.01"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]" />

                    </div>

                    <!-- STOCK -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Stock inicial
                        </label>

                        <input v-model="form.stock" type="number"
                            class="w-full rounded-xl border-slate-300 focus:ring-[#1f1d2b] focus:border-[#1f1d2b]" />

                    </div>

                    <!-- MIN STOCK -->
                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Stock mínimo
                        </label>

                        <input v-model="form.min_stock" type="number"
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
                            Guardar
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</template>