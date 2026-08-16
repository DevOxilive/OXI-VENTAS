<script setup>
defineProps({
  maxHeight: {
    type: String,
    default: null,
  },
})
</script>

<template>
  <section
    class="w-full max-w-full overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm"
    :style="maxHeight ? { maxHeight } : null"
  >
    <slot />
  </section>
</template>
