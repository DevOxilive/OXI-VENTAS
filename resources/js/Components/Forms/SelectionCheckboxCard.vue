<script setup>
import { computed } from "vue";
import AnimatedCheckIndicator from "./AnimatedCheckIndicator.vue";

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
        default: "",
    },
    badge: {
        type: String,
        default: "",
    },
    icon: {
        type: String,
        default: "",
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
        default: "surface",
    },
});

const emit = defineEmits(["toggle"]);

const resolvedVariant = computed(() => {
    return props.darkChecked ? "solid" : props.variant;
});

const isSolidChecked = computed(() => {
    return resolvedVariant.value === "solid" && props.checked;
});

const cardClasses = computed(() => {
    const variants = {
        surface: props.checked
            ? "border-primary bg-background shadow-sm hover:border-primary hover:shadow-md"
            : "border-secondary bg-secondary hover:border-primary hover:bg-background",
        soft: props.checked
            ? "border-primary bg-secondary shadow-sm"
            : "border-secondary bg-background hover:border-primary hover:bg-secondary",
        solid: props.checked
            ? "border-primary bg-secondary shadow-sm"
            : "border-secondary bg-secondary hover:border-primary hover:bg-background",
    };

    return variants[resolvedVariant.value] || variants.surface;
});

const descriptionClasses = computed(() => {
    if (resolvedVariant.value === "solid") {
        return "text-text opacity-75";
    }

    return "text-text opacity-70";
});

const badgeClasses = computed(() => {
    if (isSolidChecked.value) {
        return "bg-background text-text";
    }

    return "bg-secondary text-text";
});
</script>

<template>
    <button
        type="button"
        class="group flex items-start gap-3 rounded-[24px] border text-left transition"
        :class="[
            compact ? 'px-4 py-3' : 'px-4 py-4',
            cardClasses,
            disabled ? 'cursor-default' : '',
            highlighted && resolvedVariant !== 'solid'
                ? 'border-accent bg-secondary'
                : '',
        ]"
        :disabled="disabled"
        :aria-pressed="checked"
        @click="!disabled && emit('toggle')"
    >
        <AnimatedCheckIndicator
            class="mt-0.5"
            :checked="checked"
            :disabled="disabled"
            :compact="compact"
        />

        <span
            v-if="icon"
            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl transition"
            :class="
                checked
                    ? 'bg-primary text-white'
                    : 'bg-secondary text-primary group-hover:bg-primary group-hover:text-white'
            "
        >
            <span class="material-symbols-outlined text-[21px]">{{
                icon
            }}</span>
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
