<script setup>
import { ref } from 'vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    eyebrow: {
        type: String,
        default: 'Notificaciones',
    },
    title: {
        type: String,
        default: 'Avisos',
    },
    subtitle: {
        type: String,
        default: '',
    },
    items: {
        type: Array,
        default: () => [],
    },
    emptyTitle: {
        type: String,
        default: 'Todo tranquilo',
    },
    emptyDescription: {
        type: String,
        default: 'No hay avisos pendientes.',
    },
    emptyIcon: {
        type: String,
        default: 'task_alt',
    },
})

const emit = defineEmits(['close', 'select', 'dismiss'])
const dragState = ref({ key: null, startX: 0, currentX: 0, dragging: false })
const suppressedSelectKey = ref(null)

function toneClasses(tone) {
    const tones = {
        amber: 'bg-amber-100 text-amber-700',
        green: 'bg-emerald-100 text-emerald-700',
        red: 'bg-red-100 text-red-700',
        blue: 'bg-blue-100 text-blue-700',
        slate: 'bg-secondary text-text',
    }

    return tones[tone] || tones.slate
}

function notificationKey(item) {
    return item.key || item.id
}

function startDrag(event, item) {
    dragState.value = {
        key: notificationKey(item),
        startX: event.clientX ?? 0,
        currentX: event.clientX ?? 0,
        dragging: true,
    }
}

function moveDrag(event, item) {
    if (!dragState.value.dragging || dragState.value.key !== notificationKey(item)) return

    dragState.value = {
        ...dragState.value,
        currentX: Math.max(event.clientX ?? 0, dragState.value.startX),
    }
}

function endDrag(item) {
    if (!dragState.value.dragging || dragState.value.key !== notificationKey(item)) return

    const distance = dragDistance(item)
    dragState.value = { key: null, startX: 0, currentX: 0, dragging: false }

    if (distance >= 90) {
        suppressedSelectKey.value = notificationKey(item)
        emit('dismiss', item)
    }
}

function dragDistance(item) {
    if (dragState.value.key !== notificationKey(item)) return 0

    return Math.min(130, Math.max(0, dragState.value.currentX - dragState.value.startX))
}

function dragStyle(item) {
    const distance = dragDistance(item)

    return {
        transform: `translateX(${distance}px)`,
    }
}

function selectNotification(item) {
    if (suppressedSelectKey.value === notificationKey(item)) {
        suppressedSelectKey.value = null
        return
    }

    emit('select', item)
}
</script>

<template>
    <GlobalModal
        v-if="open"
        size="md"
        height="auto"
        :columns="1"
        :show-header="false"
        :show-footer="false"
        :show-save="false"
        @close="$emit('close')"
    >
        <template #header="{ close }">
            <header class="border-b border-secondary bg-primary px-5 py-4 text-white">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/80">
                            {{ eyebrow }}
                        </p>
                        <h2 class="mt-1 text-lg font-black">
                            {{ title }}
                        </h2>
                        <p v-if="subtitle" class="mt-1 text-xs text-white/80">
                            {{ subtitle }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20"
                        aria-label="Cerrar notificaciones"
                        @click="close"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            </header>
        </template>

        <div v-if="items.length" class="max-h-[360px] space-y-2 overflow-y-auto bg-secondary p-3">
            <article
                v-for="item in items"
                :key="item.key || item.id"
                class="relative overflow-hidden rounded-2xl"
            >
                <div class="absolute inset-y-0 left-0 flex w-28 items-center justify-center rounded-2xl bg-primary text-xs font-black text-white">
                    Borrar
                </div>

                <div
                    role="button"
                    tabindex="0"
                    class="relative w-full touch-pan-y rounded-2xl border border-secondary bg-background p-3 text-left shadow-sm transition hover:border-primary"
                    :style="dragStyle(item)"
                    @click="selectNotification(item)"
                    @keydown.enter.prevent="selectNotification(item)"
                    @keydown.space.prevent="selectNotification(item)"
                    @pointerdown="startDrag($event, item)"
                    @pointermove="moveDrag($event, item)"
                    @pointerup="endDrag(item)"
                    @pointercancel="endDrag(item)"
                    @lostpointercapture="endDrag(item)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-text">
                                {{ item.title }}
                            </p>
                            <p v-if="item.description" class="mt-1 text-xs font-semibold text-text opacity-70">
                                {{ item.description }}
                            </p>
                        </div>

                        <span
                            v-if="item.badge"
                            class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-black"
                            :class="toneClasses(item.tone)"
                        >
                            {{ item.badge }}
                        </span>
                    </div>

                    <div v-if="item.meta" class="mt-3 rounded-xl bg-secondary px-3 py-2 text-xs font-semibold text-text">
                        {{ item.meta }}
                    </div>

                    <div class="mt-3 flex justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-xl border border-primary/25 bg-secondary px-3 py-1.5 text-xs font-black text-primary transition hover:border-primary"
                            @click.stop="$emit('dismiss', item)"
                        >
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                            Borrar notificación
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="bg-secondary px-5 py-8 text-center">
            <span class="material-symbols-outlined text-4xl text-accent">
                {{ emptyIcon }}
            </span>
            <p class="mt-2 text-sm font-bold text-text">
                {{ emptyTitle }}
            </p>
            <p class="mt-1 text-xs text-text opacity-70">
                {{ emptyDescription }}
            </p>
        </div>
    </GlobalModal>
</template>
