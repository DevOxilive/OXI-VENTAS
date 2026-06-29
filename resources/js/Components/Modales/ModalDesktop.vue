<script setup>
import ModalContent from './ModalContent.vue'
import ModalFooter from './ModalFooter.vue'
import ModalHeader from './ModalHeader.vue'
import {
    getModalBackdropStyle,
    getModalPanelStyle,
} from './modalClasses'

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
        default: 2,
    },
    scrollMode: {
        type: String,
        default: 'auto',
    },
    size: {
        type: String,
        default: 'xl',
    },
    height: {
        type: String,
        default: 'full',
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
        class="fixed inset-0 z-[9999] hidden overflow-y-auto p-4 md:flex md:items-center md:justify-center md:p-6"
    >
        <div
            class="absolute inset-0"
            :style="getModalBackdropStyle(backdrop)"
            @click="closeFromBackdrop(closeOnBackdrop)"
        />

        <section
            class="relative my-4 flex w-full flex-col overflow-hidden overscroll-contain rounded-3xl bg-white shadow-2xl"
            :class="panelClass"
            :style="getModalPanelStyle({ size, height })"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            @click.stop
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

            <main
                v-if="$slots.content"
                class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain bg-white touch-pan-y"
                @click.stop
                @wheel.stop
                @touchmove.stop
            >
                <slot name="content" />
            </main>

            <template v-else>
                <ModalContent
                    :columns="columns"
                    :scroll-mode="scrollMode"
                    :content-class="contentClass"
                >
                    <slot />
                </ModalContent>
            </template>

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
