<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import ModalDesktop from './ModalDesktop.vue'
import ModalMobile from './ModalMobile.vue'

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    modeTitles: {
        type: Object,
        default: () => ({}),
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
    closeOnEsc: {
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
    alerts: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['save', 'close'])
const isDesktop = ref(
    typeof window !== 'undefined'
        ? window.matchMedia('(min-width: 768px)').matches
        : false,
)
let mediaQuery = null

const modalTitle = computed(() => {
    return props.title || props.modeTitles?.[props.mode] || ''
})

const layoutProps = computed(() => ({
    title: modalTitle.value,
    subtitle: props.subtitle,
    mode: props.mode,
    totalErrors: props.totalErrors,
    processing: props.processing,
    saveButtonText: props.saveButtonText,
    closeButtonText: props.closeButtonText,
    columns: props.columns,
    scrollMode: props.scrollMode,
    size: props.size,
    height: props.height,
    backdrop: props.backdrop,
    showHeader: props.showHeader,
    showFooter: props.showFooter,
    showSave: props.showSave,
    closeOnBackdrop: props.closeOnBackdrop,
    contentClass: props.contentClass,
    panelClass: props.panelClass,
}))

function closeModal() {
    emit('close')
}

function handleEsc(event) {
    if (props.closeOnEsc && event.key === 'Escape') {
        closeModal()
    }
}

function syncViewport() {
    isDesktop.value = mediaQuery?.matches ?? false
}

onMounted(() => {
    mediaQuery = window.matchMedia('(min-width: 768px)')
    syncViewport()
    mediaQuery.addEventListener('change', syncViewport)
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    mediaQuery?.removeEventListener('change', syncViewport)
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <Teleport to="body">
        <ModalDesktop
            v-if="isDesktop"
            v-bind="layoutProps"
            @save="$emit('save')"
            @close="closeModal"
        >
            <template v-if="$slots.header" #header="slotProps">
                <slot name="header" v-bind="slotProps" />
            </template>

            <template v-if="$slots.content" #content>
                <slot name="content" />
            </template>

            <template v-if="$slots.footer" #footer="slotProps">
                <slot name="footer" v-bind="slotProps" />
            </template>

            <slot />
        </ModalDesktop>

        <ModalMobile
            v-else
            v-bind="layoutProps"
            @save="$emit('save')"
            @close="closeModal"
        >
            <template v-if="$slots.header" #header="slotProps">
                <slot name="header" v-bind="slotProps" />
            </template>

            <template v-if="$slots.content" #content>
                <slot name="content" />
            </template>

            <template v-if="$slots.footer" #footer="slotProps">
                <slot name="footer" v-bind="slotProps" />
            </template>

            <slot />
        </ModalMobile>
    </Teleport>
</template>
