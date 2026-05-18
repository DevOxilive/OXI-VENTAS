<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import ProductData from '@/Components/Inventory/ProductData.vue'

const emit = defineEmits(['close', 'save', 'validate'])

const props = defineProps({
    modo: {
        type: String,
        default: 'create'
    },
    product: {
        type: Object,
        required: true
    },
    frontendErrors: {
        type: Object,
        default: () => ({})
    }
})

const isReadOnly = computed(() => props.modo === 'view')

const totalErrors = computed(() => {
    return Object.values(props.frontendErrors || {}).filter(Boolean).length
})

const saveButtonText = computed(() => {
    if (props.product?.processing) return 'Procesando...'

    if (props.modo === 'create') return 'Guardar producto'
    if (props.modo === 'edit') return 'Actualizar producto'

    return 'Cerrar'
})

function closeModal() {
    emit('close')
}

function handleEsc(event) {
    if (event.key === 'Escape') {
        closeModal()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :modo="modo" :total-errors="totalErrors" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <ProductData :product="product" :frontend-errors="frontendErrors" :readonly="isReadOnly"
                    @validate="$emit('validate', $event)" />
            </GeneralModalContent>

            <GeneralModalFooter :employee="product" :modo="modo" :save-button-text="saveButtonText"
                @save="$emit('save')" @close="closeModal" />
        </div>
    </div>
</template>