<script setup>
import { computed } from 'vue'

const props = defineProps({
    checked: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    badge: {
        type: String,
        default: '',
    },
    highlighted: {
        type: Boolean,
        default: false,
    },
    darkChecked: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'surface',
    },
})

const emit = defineEmits(['toggle'])

const resolvedVariant = computed(() => {
    return props.darkChecked ? 'solid' : props.variant
})

const isSolidChecked = computed(() => {
    return resolvedVariant.value === 'solid' && props.checked
})

const cardClasses = computed(() => {
    const variants = {
        surface: props.checked
            ? 'border-primary bg-background shadow-sm hover:border-primary hover:shadow-md'
            : 'border-secondary bg-secondary hover:border-primary hover:bg-background',
        soft: props.checked
            ? 'border-primary bg-secondary shadow-sm'
            : 'border-secondary bg-background hover:border-primary hover:bg-secondary',
        solid: props.checked
            ? 'border-primary bg-secondary shadow-sm'
            : 'border-secondary bg-secondary hover:border-primary hover:bg-background',
    }

    return variants[resolvedVariant.value] || variants.surface
})

const descriptionClasses = computed(() => {
    if (resolvedVariant.value === 'solid') {
        return 'text-text opacity-75'
    }

    return 'text-text opacity-70'
})

const badgeClasses = computed(() => {
    if (isSolidChecked.value) {
        return 'bg-background text-text'
    }

    return 'bg-secondary text-text'
})

const checkboxClasses = computed(() => {
    return {
        'is-checked': props.checked,
        'is-disabled': props.disabled,
    }
})

const checkboxStyle = computed(() => ({
    '--box-size': props.compact ? '20px' : '22px',
    '--box-bg': props.checked ? 'var(--primary)' : 'transparent',
    '--box-border': props.checked ? 'var(--primary)' : 'color-mix(in srgb, var(--text) 22%, transparent)',
    '--box-ring': props.checked ? 'color-mix(in srgb, var(--primary) 28%, transparent)' : 'transparent',
    '--check-color': props.checked ? 'var(--background)' : 'transparent',
}))
</script>

<template>
    <button
        type="button"
        class="group flex items-start gap-3 rounded-[24px] border text-left transition"
        :class="[
            compact ? 'px-4 py-3' : 'px-4 py-4',
            cardClasses,
            disabled ? 'cursor-default' : '',
            highlighted && resolvedVariant !== 'solid' ? 'border-accent bg-secondary' : '',
        ]"
        @click="!disabled && emit('toggle')"
    >
        <span class="checkbox-wrapper-30 mt-0.5 shrink-0" :class="checkboxClasses">
            <span class="checkbox" :style="checkboxStyle">
                <input
                    class="sr-only"
                    type="checkbox"
                    :checked="checked"
                    :disabled="disabled"
                    tabindex="-1"
                    @click.stop="!disabled && emit('toggle')"
                >
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path
                        fill="none"
                        d="M3.5 8.5 6.5 11.5 12.5 4.75"
                    />
                </svg>
            </span>
        </span>

        <div class="min-w-0 flex-1">
            <p class="break-words text-sm font-semibold leading-5">
                {{ title }}
            </p>
            <p
                v-if="description"
                class="mt-1 break-words text-xs leading-4"
                :class="descriptionClasses"
            >
                {{ description }}
            </p>
        </div>

        <span
            v-if="badge"
            class="mt-0.5 shrink-0 self-start rounded-full px-2.5 py-1 text-[11px] font-semibold"
            :class="badgeClasses"
        >
            {{ badge }}
        </span>
    </button>
</template>

<style scoped>
.checkbox-wrapper-30 .checkbox {
    display: grid;
    place-items: center;
    position: relative;
    width: var(--box-size, 22px);
    height: var(--box-size, 22px);
}

.checkbox-wrapper-30 .checkbox svg {
    fill: none;
    pointer-events: none;
    position: relative;
    z-index: 1;
    width: calc(var(--box-size, 22px) * 0.78);
    height: calc(var(--box-size, 22px) * 0.78);
    stroke: var(--check-color, transparent);
    stroke-dasharray: 18;
    stroke-dashoffset: 18;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2.35px;
    transition: stroke-dashoffset 0.24s ease, transform 0.24s ease, stroke 0.24s ease;
    transform: scale(0.78);
}

.checkbox-wrapper-30 .checkbox::before,
.checkbox-wrapper-30 .checkbox::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 0.42rem;
}

.checkbox-wrapper-30 .checkbox::before {
    background: var(--box-bg, transparent);
    border: 2px solid var(--box-border, color-mix(in srgb, var(--text) 22%, transparent));
    transition: border-color 0.24s ease, background-color 0.24s ease, transform 0.24s ease;
}

.checkbox-wrapper-30 .checkbox::after {
    box-shadow: 0 0 0 0 var(--box-ring, transparent);
    opacity: 0;
    transition: box-shadow 0.24s ease, opacity 0.24s ease, transform 0.24s ease;
}

.checkbox-wrapper-30.is-checked .checkbox::after {
    box-shadow: 0 0 0 5px var(--box-ring, transparent);
    opacity: 1;
}

.checkbox-wrapper-30.is-checked .checkbox svg {
    stroke-dashoffset: 0;
    transform: scale(1);
}

.checkbox-wrapper-30.is-disabled {
    opacity: 0.72;
}
</style>
