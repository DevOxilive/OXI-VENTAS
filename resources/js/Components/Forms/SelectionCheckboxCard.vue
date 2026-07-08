<script setup>
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
})

const emit = defineEmits(['toggle'])
</script>

<template>
    <button
        type="button"
        class="group flex items-center gap-3 rounded-[24px] border text-left transition"
        :class="[
            compact ? 'px-4 py-3' : 'px-4 py-4',
            darkChecked && checked
                ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-300/50'
                : checked
                    ? 'border-slate-300 bg-white shadow-sm hover:border-slate-400 hover:shadow-md'
                    : 'border-slate-200 bg-slate-50/80 hover:border-slate-300 hover:bg-white',
            disabled ? 'cursor-default' : '',
            highlighted && !darkChecked ? 'border-emerald-300 bg-emerald-50' : '',
        ]"
        @click="!disabled && emit('toggle')"
    >
        <span class="checkbox-wrapper-30 shrink-0" :class="{ 'is-disabled': disabled && darkChecked }">
            <span class="checkbox" :style="{ '--size': compact ? 1 : 1.15 }">
                <input
                    type="checkbox"
                    :checked="checked"
                    :disabled="disabled"
                    @click.stop="!disabled && emit('toggle')"
                />
                <svg viewBox="0 0 22 22" aria-hidden="true">
                    <path
                        fill="none"
                        stroke="currentColor"
                        d="M5.5,11.3L9,14.8L20.2,3.3l0,0c-0.5-1-1.5-1.8-2.7-1.8h-13c-1.7,0-3,1.3-3,3v13c0,1.7,1.3,3,3,3h13 c1.7,0,3-1.3,3-3v-13c0-0.4-0.1-0.8-0.3-1.2"
                    />
                </svg>
            </span>
        </span>

        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold">
                {{ title }}
            </p>
            <p
                v-if="description"
                class="mt-1 text-xs"
                :class="darkChecked && checked ? 'text-slate-300' : 'text-slate-500'"
            >
                {{ description }}
            </p>
        </div>

        <span
            v-if="badge"
            class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
            :class="darkChecked && checked ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-600'"
        >
            {{ badge }}
        </span>
    </button>
</template>

<style scoped>
.checkbox-wrapper-30 .checkbox {
    --bg: #fff;
    --brdr: #d1d6ee;
    --brdr-actv: #111827;
    --brdr-hovr: #94a3b8;
    --dur: calc((var(--size, 2) / 2) * 0.6s);
    display: inline-block;
    position: relative;
    width: calc(var(--size, 1) * 22px);
}

.checkbox-wrapper-30 .checkbox::after {
    content: '';
    display: block;
    padding-top: 100%;
    width: 100%;
}

.checkbox-wrapper-30 .checkbox > * {
    position: absolute;
}

.checkbox-wrapper-30 .checkbox input {
    -webkit-appearance: none;
    -moz-appearance: none;
    -webkit-tap-highlight-color: transparent;
    background-color: var(--bg);
    border: calc(var(--newBrdr, var(--size, 1)) * 1px) solid;
    border-radius: calc(var(--size, 1) * 4px);
    color: var(--newBrdrClr, var(--brdr));
    cursor: pointer;
    margin: 0;
    outline: none;
    padding: 0;
    transition: all calc(var(--dur) / 3) linear;
}

.checkbox-wrapper-30 .checkbox input:hover,
.checkbox-wrapper-30 .checkbox input:checked {
    --newBrdr: calc(var(--size, 1) * 2);
}

.checkbox-wrapper-30 .checkbox input:hover {
    --newBrdrClr: var(--brdr-hovr);
}

.checkbox-wrapper-30 .checkbox input:checked {
    --newBrdrClr: var(--brdr-actv);
    transition-delay: calc(var(--dur) / 1.3);
}

.checkbox-wrapper-30 .checkbox input:checked + svg {
    --dashArray: 16 93;
    --dashOffset: 109;
}

.checkbox-wrapper-30 .checkbox svg {
    fill: none;
    left: 0;
    pointer-events: none;
    stroke: currentColor;
    stroke-dasharray: var(--dashArray, 93);
    stroke-dashoffset: var(--dashOffset, 94);
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2px;
    top: 0;
    transition: stroke-dasharray var(--dur), stroke-dashoffset var(--dur);
}

.checkbox-wrapper-30 .checkbox svg,
.checkbox-wrapper-30 .checkbox input {
    display: block;
    height: 100%;
    width: 100%;
}

.checkbox-wrapper-30.is-disabled .checkbox {
    --bg: rgba(255, 255, 255, 0.12);
    --brdr: rgba(255, 255, 255, 0.45);
    --brdr-actv: #ffffff;
    --brdr-hovr: rgba(255, 255, 255, 0.6);
}

.checkbox-wrapper-30.is-disabled .checkbox input {
    cursor: default;
}
</style>
