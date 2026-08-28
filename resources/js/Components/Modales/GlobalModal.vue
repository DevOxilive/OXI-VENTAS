<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

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
    sections: {
        type: Array,
        default: () => [],
    },
    activeSection: {
        type: [Number, String],
        default: null,
    },
    trapFocus: {
        type: Boolean,
        default: true,
    },
    restoreFocus: {
        type: Boolean,
        default: true,
    },
    autoFocusSelector: {
        type: String,
        default: '[data-modal-autofocus]',
    },
})

const emit = defineEmits(['save', 'close', 'select-section'])
const isDesktop = ref(
    typeof window !== 'undefined'
        ? window.matchMedia('(min-width: 768px)').matches
        : false,
)
const layoutRef = ref(null)
const previouslyFocusedElement = ref(null)
let mediaQuery = null

const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable="true"]',
].join(',')

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
    sections: props.sections,
    activeSection: props.activeSection,
}))

function closeModal() {
    emit('close')
}

function getPanelElement() {
    return layoutRef.value?.getPanelElement?.() ?? null
}

function isVisible(element) {
    return Boolean(
        element
        && !element.hidden
        && element.getAttribute?.('aria-hidden') !== 'true'
        && element.getClientRects().length,
    )
}

function getFocusableElements(panel) {
    if (!panel) return []

    return Array.from(panel.querySelectorAll(focusableSelector)).filter(isVisible)
}

function isTopmostModal(panel) {
    if (!panel) return false

    const visiblePanels = Array.from(
        document.querySelectorAll('[data-global-modal-panel]'),
    ).filter(isVisible)

    return visiblePanels.at(-1) === panel
}

function resolveAutoFocusElement(panel) {
    if (!panel || !props.autoFocusSelector) return null

    let target = null

    try {
        target = Array.from(panel.querySelectorAll(props.autoFocusSelector)).find(isVisible) ?? null
    } catch {
        target = null
    }

    if (!target) return null
    if (target.matches?.(focusableSelector)) return target

    return Array.from(target.querySelectorAll(focusableSelector)).find(isVisible) ?? null
}

async function focusModalTarget({ fallbackToPanel = true } = {}) {
    await nextTick()

    const panel = getPanelElement()
    if (!panel || !isTopmostModal(panel)) return

    const target = resolveAutoFocusElement(panel)
    if (target) {
        target.focus({ preventScroll: true })
        return
    }

    if (fallbackToPanel && !panel.contains(document.activeElement)) {
        panel.focus({ preventScroll: true })
    }
}

function trapTabKey(event) {
    if (!props.trapFocus || event.key !== 'Tab') return

    const panel = getPanelElement()
    if (!panel || !isTopmostModal(panel)) return

    const focusableElements = getFocusableElements(panel)
    if (!focusableElements.length) {
        event.preventDefault()
        panel.focus({ preventScroll: true })
        return
    }

    const firstElement = focusableElements[0]
    const lastElement = focusableElements.at(-1)
    const activeElement = document.activeElement

    if (!panel.contains(activeElement)) {
        event.preventDefault()
        const destination = event.shiftKey ? lastElement : firstElement
        destination.focus()
        return
    }

    if (event.shiftKey && (activeElement === firstElement || activeElement === panel)) {
        event.preventDefault()
        lastElement.focus()
        return
    }

    if (!event.shiftKey && (activeElement === lastElement || activeElement === panel)) {
        event.preventDefault()
        firstElement.focus()
    }
}

function handleEsc(event) {
    trapTabKey(event)

    if (
        props.closeOnEsc
        && event.key === 'Escape'
        && isTopmostModal(getPanelElement())
    ) {
        closeModal()
    }
}

function syncViewport() {
    isDesktop.value = mediaQuery?.matches ?? false
}

onMounted(() => {
    previouslyFocusedElement.value = document.activeElement
    mediaQuery = window.matchMedia('(min-width: 768px)')
    syncViewport()
    mediaQuery.addEventListener('change', syncViewport)
    window.addEventListener('keydown', handleEsc)
    focusModalTarget()
})

onBeforeUnmount(() => {
    mediaQuery?.removeEventListener('change', syncViewport)
    window.removeEventListener('keydown', handleEsc)

    if (props.restoreFocus && previouslyFocusedElement.value?.isConnected) {
        previouslyFocusedElement.value.focus?.({ preventScroll: true })
    }
})

watch(
    () => props.activeSection,
    () => focusModalTarget({ fallbackToPanel: false }),
    { flush: 'post' },
)

watch(
    isDesktop,
    () => focusModalTarget(),
    { flush: 'post' },
)
</script>

<template>
    <Teleport to="body">
        <ModalDesktop
            v-if="isDesktop"
            ref="layoutRef"
            v-bind="layoutProps"
            @save="$emit('save')"
            @close="closeModal"
            @select-section="$emit('select-section', $event)"
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
            ref="layoutRef"
            v-bind="layoutProps"
            @save="$emit('save')"
            @close="closeModal"
            @select-section="$emit('select-section', $event)"
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
