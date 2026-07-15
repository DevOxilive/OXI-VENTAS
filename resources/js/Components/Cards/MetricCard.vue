<script setup>
const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  suffix: {
    type: String,
    default: "",
  },
  tone: {
    type: String,
    default: "neutral",
  },
  size: {
    type: String,
    default: "md",
  },
})

const toneClasses = {
  neutral: {
    card: "border-secondary bg-secondary",
    label: "text-text opacity-50",
    value: "text-text",
    suffix: "text-text opacity-70",
  },
  success: {
    card: "border-accent bg-secondary",
    label: "text-accent",
    value: "text-accent",
    suffix: "text-accent",
  },
  danger: {
    card: "border-primary bg-secondary",
    label: "text-primary",
    value: "text-primary",
    suffix: "text-primary",
  },
  dark: {
    card: "border-primary bg-primary",
    label: "text-white opacity-80",
    value: "text-white",
    suffix: "text-white opacity-85",
  },
}

const sizeClasses = {
  sm: {
    wrapper: "px-4 py-3",
    value: "mt-1 text-base font-bold",
  },
  md: {
    wrapper: "px-4 py-4",
    value: "mt-2 text-3xl font-black",
  },
  lg: {
    wrapper: "px-5 py-6",
    value: "mt-3 text-5xl font-black leading-none",
  },
}
</script>

<template>
  <div
    class="rounded-2xl border"
    :class="[
      toneClasses[tone]?.card ?? toneClasses.neutral.card,
      sizeClasses[size]?.wrapper ?? sizeClasses.md.wrapper,
    ]"
  >
    <p
      class="text-[11px] uppercase tracking-[0.14em]"
      :class="toneClasses[tone]?.label ?? toneClasses.neutral.label"
    >
      {{ label }}
    </p>

    <p
      :class="[
        sizeClasses[size]?.value ?? sizeClasses.md.value,
        toneClasses[tone]?.value ?? toneClasses.neutral.value,
      ]"
    >
      {{ value }}
    </p>

    <p
      v-if="suffix"
      class="mt-1 text-sm"
      :class="toneClasses[tone]?.suffix ?? toneClasses.neutral.suffix"
    >
      {{ suffix }}
    </p>
  </div>
</template>
