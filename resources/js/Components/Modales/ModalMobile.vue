<script setup>
import ModalContent from './ModalContent.vue'
import ModalFooter from './ModalFooter.vue'
import ModalHeader from './ModalHeader.vue'
import { getModalBackdropStyle } from './modalClasses'

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    mode: {
        type: String,
        default: 'create',
    },
    totalErrors: {
        type: Number,
        default: 0,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    saveButtonText: {
        type: String,
        default: 'Guardar',
    },
    closeButtonText: {
        type: String,
        default: 'Cancelar',
    },
    columns: {
        type: [Number, String],
        default: 1,
    },
    scrollMode: {
        type: String,
        default: 'auto',
    },
    backdrop: {
        type: String,
        default: 'default',
    },
    showHeader: {
        type: Boolean,
        default: true,
    },
    showFooter: {
        type: Boolean,
        default: true,
    },
    showSave: {
        type: Boolean,
        default: true,
    },
    closeOnBackdrop: {
        type: Boolean,
        default: true,
    },
    contentClass: {
        type: [String, Array, Object],
        default: '',
    },
    panelClass: {
        type: [String, Array, Object],
        default: '',
    },
})

const emit = defineEmits(['save', 'close'])

function closeFromBackdrop(closeOnBackdrop) {
    if (!closeOnBackdrop) return

    emit('close')
}
</script>

<template>
    <div
        class="fixed inset-0 z-[9999] flex items-end justify-center md:hidden"
    >
        <div
            class="absolute inset-0"
            :style="getModalBackdropStyle(backdrop)"
            @click="closeFromBackdrop(closeOnBackdrop)"
        />

        <section
            class="relative flex max-h-[94dvh] w-full flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl"
            :class="panelClass"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <slot name="header" :close="() => $emit('close')" :title="title">
                <ModalHeader
                    v-if="showHeader"
                    :title="title"
                    :subtitle="subtitle"
                    :total-errors="totalErrors"
                    :mode="mode"
                    @close="$emit('close')"
                />
            </slot>

            <slot name="content">
                <ModalContent
                    :columns="1"
                    :scroll-mode="scrollMode"
                    :content-class="contentClass"
                >
                    <slot />
                </ModalContent>
            </slot>

            <slot name="footer" :save="() => $emit('save')" :close="() => $emit('close')">
                <ModalFooter
                    v-if="showFooter"
                    :processing="processing"
                    :save-button-text="saveButtonText"
                    :close-button-text="closeButtonText"
                    :mode="mode"
                    :show-save="showSave"
                    @save="$emit('save')"
                    @close="$emit('close')"
                />
            </slot>
        </section>
    </div>
</template>
