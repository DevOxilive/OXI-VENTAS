<script setup>
defineProps({
    branch: Object,
    selectedCount: {
        type: Number,
        default: 0,
    },
})

defineEmits(['save'])
</script>

<template>
    <section class="rounded-2xl border border-secondary bg-background p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium text-text opacity-70">
                    Inventario / Reporte de compra
                </p>

                <h1 class="mt-1 text-2xl font-bold text-text">
                    Lista de abastecimiento - {{ branch?.name }}
                </h1>

                <p class="mt-1 text-sm text-text opacity-70">
                    Selecciona productos, captura cantidades manuales y guarda un borrador.
                </p>
            </div>

            <button type="button" :disabled="!selectedCount" @click="$emit('save')"
                class="inline-flex items-center justify-center rounded-xl border border-primary bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40">
                Guardar borrador
            </button>
        </div>
    </section>
</template>
