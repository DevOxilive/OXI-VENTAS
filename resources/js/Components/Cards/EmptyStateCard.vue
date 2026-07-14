<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: "",
  },
  icon: {
    type: String,
    default: "",
  },
  minHeightClass: {
    type: String,
    default: "min-h-[260px]",
  },
  tone: {
    type: String,
    default: "neutral",
  },
})

const toneClasses = {
  neutral: "border-dashed border-secondary bg-secondary",
  white: "border-dashed border-secondary bg-background",
}
</script>

<template>
  <div
    class="flex items-center justify-center rounded-2xl border px-6 text-center"
    :class="[minHeightClass, toneClasses[tone] ?? toneClasses.neutral]"
  >
    <div>
      <span
        v-if="icon"
        class="material-symbols-outlined text-4xl text-text opacity-35"
      >
        {{ icon }}
      </span>

      <p class="mt-2 text-sm font-semibold text-text">
        {{ title }}
      </p>

      <p v-if="description" class="mt-1 text-sm text-text opacity-70">
        {{ description }}
      </p>
    </div>
  </div>
</template>
