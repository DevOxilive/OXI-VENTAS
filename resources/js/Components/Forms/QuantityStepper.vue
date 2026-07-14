<script setup>
defineProps({
  value: {
    type: [String, Number],
    required: true,
  },
  decreaseDisabled: {
    type: Boolean,
    default: false,
  },
  increaseDisabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(["decrease", "increase"])
</script>

<template>
  <div class="mt-1 flex items-center rounded-xl border border-secondary bg-background md:mt-0">
    <button
      type="button"
      class="h-9 w-9 text-lg font-bold text-text disabled:cursor-not-allowed disabled:opacity-35"
      :disabled="decreaseDisabled"
      @click="$emit('decrease')"
    >
      -
    </button>

    <span class="min-w-10 px-2 text-center text-sm font-semibold text-text">
      {{ value }}
    </span>

    <button
      type="button"
      class="h-9 w-9 text-lg font-bold text-text disabled:cursor-not-allowed disabled:opacity-35"
      :disabled="increaseDisabled"
      @click="$emit('increase')"
    >
      +
    </button>
  </div>
</template>
