<script setup>
import { computed, useSlots } from "vue"

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: String,
    default: "",
  },
  description: {
    type: String,
    default: "",
  },
  tone: {
    type: String,
    default: "neutral",
  },
})

const slots = useSlots()
const hasAction = computed(() => Boolean(slots.action))

const toneClasses = {
  neutral: {
    card: "border-secondary bg-secondary",
    label: "text-text opacity-50",
    value: "text-text",
    description: "text-text opacity-70",
  },
  dark: {
    card: "border-primary bg-primary",
    label: "text-white opacity-80",
    value: "text-white",
    description: "text-white opacity-80",
  },
}
</script>

<template>
  <div
    class="rounded-2xl border px-4 py-4"
    :class="toneClasses[tone]?.card ?? toneClasses.neutral.card"
  >
    <div class="flex items-center justify-between gap-3">
      <p
        class="text-[11px] uppercase tracking-[0.14em]"
        :class="toneClasses[tone]?.label ?? toneClasses.neutral.label"
      >
        {{ label }}
      </p>

      <slot v-if="hasAction" name="action" />
    </div>

    <p
      v-if="value"
      class="mt-2 text-base font-semibold"
      :class="toneClasses[tone]?.value ?? toneClasses.neutral.value"
    >
      {{ value }}
    </p>

    <p
      v-if="description"
      class="mt-1 text-xs"
      :class="toneClasses[tone]?.description ?? toneClasses.neutral.description"
    >
      {{ description }}
    </p>

    <slot />
  </div>
</template>
