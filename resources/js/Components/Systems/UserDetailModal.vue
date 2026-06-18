<script setup>
import { onMounted, onBeforeUnmount } from "vue";

import GeneralModalHeader from "@/Components/Forms/GeneralModalHeader.vue";
import GeneralModalContent from "@/Components/Forms/GeneralModalContent.vue";
import GeneralModalFooter from "../Forms/GeneralModalFooter.vue";
const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["close"]);

function closeModal() {
    emit("close");
}

function handleEsc(event) {
    if (event.key === "Escape") closeModal();
}

onMounted(() => {
    window.addEventListener("keydown", handleEsc);
});

onBeforeUnmount(() => {
    window.removeEventListener("keydown", handleEsc);
});
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center p-0 md:p-4">
        <div class="absolute inset-0" @click="closeModal"></div>

        <section
            class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[90vh] md:w-[92%] md:max-w-4xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader title="Detalle del usuario" subtitle="Información de acceso, rol, sucursales y permisos"
                :total-errors="0" mode="view" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Datos del usuario
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <p>
                            <strong>ID:</strong>
                            {{ user.id }}
                        </p>

                        <p>
                            <strong>Nombre:</strong>
                            {{ user.name || "—" }}
                        </p>

                        <p>
                            <strong>Correo:</strong>
                            {{ user.email || "—" }}
                        </p>

                        <p>
                            <strong>Empleado ID:</strong>
                            {{ user.employee_id || "—" }}
                        </p>

                        <p>
                            <strong>Rol:</strong>
                            {{ user.role?.name || "Sin rol" }}
                        </p>

                        <div v-if="user.role?.name === 'Ventas'" class="sm:col-span-2">
                            <strong>Sucursales:</strong>

                            <div v-if="user.branches?.length" class="flex flex-wrap gap-2 mt-2">
                                <span v-for="branch in user.branches" :key="branch.id"
                                    class="inline-block px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs border border-green-300">
                                    {{ branch.name }}
                                </span>
                            </div>

                            <p v-else class="text-sm text-slate-500 mt-2">
                                No tiene sucursales asignadas.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Permisos activados
                    </h3>

                    <div v-if="user.permissions?.length" class="flex flex-wrap gap-2">
                        <span v-for="permission in user.permissions" :key="permission.id"
                            class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs border border-slate-300">
                            {{ permission.name }}
                        </span>
                    </div>

                    <p v-else class="text-sm text-gray-500">
                        Este usuario no tiene permisos activados.
                    </p>
                </section>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="closeModal"
                        class="px-5 py-3 rounded-2xl bg-slate-900 text-white font-semibold hover:bg-slate-700 transition">
                        Cerrar
                    </button>
                </div>
            </GeneralModalContent>
        </section>
    </div>
</template>