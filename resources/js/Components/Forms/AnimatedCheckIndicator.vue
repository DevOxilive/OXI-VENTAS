<script setup>
import { computed } from "vue";
const props = defineProps({
    checked: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});
const checkboxClasses = computed(() => {
    return {
        "is-checked": props.checked,
        "is-disabled": props.disabled,
    };
});

const checkboxStyle = computed(() => ({
    "--box-size": props.compact ? "20px" : "22px",
    "--box-bg": props.checked ? "var(--primary)" : "transparent",
    "--box-border": props.checked
        ? "var(--primary)"
        : "color-mix(in srgb, var(--text) 22%, transparent)",
    "--box-ring": props.checked
        ? "color-mix(in srgb, var(--primary) 28%, transparent)"
        : "transparent",
    "--check-color": props.checked ? "var(--background)" : "transparent",
}));
</script>
<template>
    <span
        class="checkbox-wrapper-30 inline-flex shrink-0"
        :class="checkboxClasses"
        aria-hidden="true"
    >
        <span class="checkbox" :style="checkboxStyle">
            <svg viewBox="0 0 16 16">
                <path fill="none" d="M3.5 8.5 6.5 11.5 12.5 4.75" />
            </svg>
        </span>
    </span>
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
    transition:
        stroke-dashoffset 0.24s ease,
        transform 0.24s ease,
        stroke 0.24s ease;
    transform: scale(0.78);
}

.checkbox-wrapper-30 .checkbox::before,
.checkbox-wrapper-30 .checkbox::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 0.42rem;
}

.checkbox-wrapper-30 .checkbox::before {
    background: var(--box-bg, transparent);
    border: 2px solid
        var(--box-border, color-mix(in srgb, var(--text) 22%, transparent));
    transition:
        border-color 0.24s ease,
        background-color 0.24s ease,
        transform 0.24s ease;
}

.checkbox-wrapper-30 .checkbox::after {
    box-shadow: 0 0 0 0 var(--box-ring, transparent);
    opacity: 0;
    transition:
        box-shadow 0.24s ease,
        opacity 0.24s ease,
        transform 0.24s ease;
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

@media (prefers-reduced-motion: reduce) {
    .checkbox-wrapper-30 .checkbox svg,
    .checkbox-wrapper-30 .checkbox::before,
    .checkbox-wrapper-30 .checkbox::after {
        transition: none;
    }
}
</style>
