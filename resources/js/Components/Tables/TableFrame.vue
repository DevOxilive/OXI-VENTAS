<script setup>
defineProps({
  minWidth: { type: String, default: null },
  compact: { type: Boolean, default: false },
  scrollable: { type: Boolean, default: true },
})
</script>

<template>
  <div class="max-w-full" :class="scrollable ? 'max-h-[560px] overflow-auto' : 'overflow-visible'">
    <table class="w-full min-w-full table-auto border-collapse text-sm [&_thead]:border-b [&_thead]:border-secondary [&_thead]:bg-secondary [&_thead]:text-text [&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-20 [&_thead_th]:bg-secondary [&_thead_th]:py-3 [&_thead_th]:font-semibold [&_tbody]:divide-y [&_tbody]:divide-secondary [&_tbody]:bg-background [&_tbody_td]:align-middle"
      :class="compact
        ? '[&_thead_th]:px-2 [&_tbody_td]:px-2 [&_tbody_td]:py-2 [&_tbody_td]:whitespace-nowrap [&_tbody_input]:min-w-[4rem]'
        : '[&_thead_th]:px-4 [&_tbody_td]:px-4 [&_tbody_td]:py-3'"
      :style="minWidth ? { minWidth } : null">
      <slot />
    </table>
  </div>
</template>
