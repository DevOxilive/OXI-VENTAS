<script setup>
defineProps({
  clipContent: {
    type: Boolean,
    default: true,
  },
  maxHeight: {
    type: String,
    default: null,
  },
})
</script>

<template>
  <section
    class="w-full max-w-full rounded-2xl border border-secondary bg-background shadow-sm"
    :class="clipContent ? 'overflow-hidden' : 'overflow-visible'"
    :style="maxHeight ? { maxHeight } : null"
  >
    <slot />
  </section>
</template>
