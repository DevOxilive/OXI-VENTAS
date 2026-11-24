<script setup>
import { computed, nextTick, ref, watch } from "vue"

const props = defineProps({
  open: { type: Boolean, default: false },
  item: { type: Object, required: true },
})

const emit = defineEmits(["request-close", "closed"])
const activeImageIndex = ref(0)
const touchStartX = ref(null)
const closeButton = ref(null)

const images = computed(() => {
  const gallery = (props.item?.images ?? [])
    .map((image) => image?.url ?? image)
    .filter((image) => typeof image === "string" && image.length > 0)

  return gallery.length ? gallery : (props.item?.image ? [props.item.image] : [])
})

const activeImage = computed(() => images.value[activeImageIndex.value] ?? null)

function previousImage() {
  if (images.value.length < 2) return
  activeImageIndex.value = (activeImageIndex.value - 1 + images.value.length) % images.value.length
}

function nextImage() {
  if (images.value.length < 2) return
  activeImageIndex.value = (activeImageIndex.value + 1) % images.value.length
}

function selectImage(index) {
  activeImageIndex.value = index
}

function rememberTouchStart(event) {
  touchStartX.value = event.touches?.[0]?.clientX ?? null
}

function handleTouchEnd(event) {
  const startX = touchStartX.value
  const endX = event.changedTouches?.[0]?.clientX
  touchStartX.value = null

  if (startX === null || endX === undefined || Math.abs(endX - startX) < 20) return
  if (endX < startX) nextImage()
  else previousImage()
}

watch(() => props.open, async (open) => {
  if (!open) return
  activeImageIndex.value = 0
  await nextTick()
  closeButton.value?.focus({ preventScroll: true })
})

watch(images, (gallery) => {
  if (activeImageIndex.value >= gallery.length) activeImageIndex.value = 0
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
      @after-leave="$emit('closed')"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/70 p-3 backdrop-blur-sm sm:p-6"
        role="dialog"
        aria-modal="true"
        :aria-label="`Imágenes de ${item.name}`"
        @click.self="$emit('request-close')"
        @keydown.esc="$emit('request-close')"
      >
        <section class="gallery-modal-panel w-full max-w-3xl overflow-hidden rounded-3xl border border-secondary bg-background shadow-2xl">
          <header class="flex items-center justify-between gap-4 border-b border-secondary px-4 py-3 sm:px-5">
            <div class="min-w-0">
              <p class="truncate text-base font-bold text-text">{{ item.name }}</p>
              <p class="text-xs text-text opacity-65">{{ images.length }} {{ images.length === 1 ? 'imagen' : 'imágenes' }}</p>
            </div>
            <button
              ref="closeButton"
              type="button"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-text transition hover:bg-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
              aria-label="Cerrar galería"
              @click="$emit('request-close')"
            >
              <span class="material-symbols-outlined">close</span>
            </button>
          </header>

          <div
            class="relative flex h-[min(62vh,520px)] items-center justify-center bg-secondary p-3 sm:p-5"
            @touchstart.passive="rememberTouchStart"
            @touchend.passive="handleTouchEnd"
          >
            <img v-if="activeImage" :src="activeImage" :alt="`${item.name} — imagen ${activeImageIndex + 1}`" class="max-h-full max-w-full object-contain" />
            <span v-else class="material-symbols-outlined text-5xl text-text opacity-30">image_not_supported</span>

            <template v-if="images.length > 1">
              <button type="button" class="gallery-arrow left-3" :aria-label="`Ver imagen anterior de ${item.name}`" @click="previousImage">
                <span class="material-symbols-outlined">chevron_left</span>
              </button>
              <button type="button" class="gallery-arrow right-3" :aria-label="`Ver imagen siguiente de ${item.name}`" @click="nextImage">
                <span class="material-symbols-outlined">chevron_right</span>
              </button>
              <span class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-background/90 px-3 py-1 text-xs font-bold text-text shadow-sm">
                {{ activeImageIndex + 1 }} / {{ images.length }}
              </span>
            </template>
          </div>

          <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto px-4 py-3 sm:px-5">
            <button
              v-for="(image, index) in images"
              :key="image"
              type="button"
              class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border-2 bg-secondary transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
              :class="index === activeImageIndex ? 'border-primary' : 'border-transparent hover:border-secondary'"
              :aria-label="`Ver imagen ${index + 1}`"
              @click="selectImage(index)"
            >
              <img :src="image" :alt="`${item.name} miniatura ${index + 1}`" class="h-full w-full object-cover" />
            </button>
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.gallery-modal-panel {
  animation: gallery-panel-in 200ms ease-out;
}

.gallery-arrow {
  position: absolute;
  top: 50%;
  display: inline-flex;
  height: 2.75rem;
  width: 2.75rem;
  transform: translateY(-50%);
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: color-mix(in srgb, var(--background) 92%, transparent);
  color: var(--text);
  box-shadow: 0 2px 8px rgb(0 0 0 / 0.18);
}

.gallery-arrow:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

@keyframes gallery-panel-in {
  from { transform: scale(.96); }
  to { transform: scale(1); }
}

@media (prefers-reduced-motion: reduce) {
  .gallery-modal-panel { animation: none; }
}
</style>
