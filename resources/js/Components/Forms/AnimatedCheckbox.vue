<script setup>
import AnimatedCheckIndicator from "./AnimatedCheckIndicator.vue";
defineOptions({ inheritAttrs: false });
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    compact: { type: Boolean, default: true },
});
const emit = defineEmits(["update:modelValue", "change"]);
function change(event) {
    emit("update:modelValue", event.target.checked);
    emit("change", event);
}
</script>
<template>
    <span class="animated-checkbox relative inline-flex shrink-0 align-middle">
        <input
            v-bind="$attrs"
            type="checkbox"
            class="check-input absolute inset-0 z-10 m-0 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
            :checked="modelValue"
            :disabled="disabled"
            @change="change"
        />
        <AnimatedCheckIndicator
            :checked="modelValue"
            :disabled="disabled"
            :compact="compact"
        />
    </span>
</template>
<style scoped>
.animated-checkbox {
    margin: 5px;
}
.check-input:focus-visible + span {
    outline: 2px solid var(--primary);
    outline-offset: 8px;
    border-radius: 0.42rem;
}
.check-input:disabled + span {
    opacity: 0.72;
}
</style>
