<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated, ref, useAttrs, useId, watch } from 'vue'

defineOptions({ inheritAttrs: false })
const props = defineProps({ modelValue: { default: undefined }, modelModifiers: { default: () => ({}) } })
const emit = defineEmits(['update:modelValue', 'change', 'blur'])
const attrs = useAttrs()
const menuId = `select-${useId()}`
const native = ref(null)
const trigger = ref(null)
const menu = ref(null)
const options = ref([])
const open = ref(false)
const active = ref(0)
const selected = ref('')
const position = ref({})
const disabled = computed(() => attrs.disabled === '' || Boolean(attrs.disabled))
const label = computed(() => options.value.find(o => o.value === selected.value)?.label || 'Selecciona una opción')
let observer
let search = ''
let searchTimer

function sync() {
  if (!native.value) return
  const items = Array.from(native.value.options).map(o => ({ value: o.value, label: o.textContent.trim(), disabled: o.disabled || o.parentElement?.disabled }))
  if (JSON.stringify(items) !== JSON.stringify(options.value)) options.value = items
  const value = props.modelValue !== undefined ? props.modelValue : attrs.value
  if (value !== undefined) native.value.value = String(value ?? '')
  selected.value = native.value.value
}
function place() {
  if (!trigger.value) return
  const r = trigger.value.getBoundingClientRect()
  const below = window.innerHeight - r.bottom
  position.value = { position: 'fixed', width: `${Math.min(Math.max(r.width, 190), window.innerWidth - 24)}px`, left: `${Math.max(12, Math.min(r.left, window.innerWidth - Math.max(r.width, 190) - 12))}px`, maxHeight: `${Math.max(100, Math.min(300, below > 180 ? below - 16 : r.top - 16))}px`, ...(below > 180 ? { top: `${r.bottom + 6}px` } : { bottom: `${window.innerHeight - r.top + 6}px` }) }
}
function close() { open.value = false }
async function show() {
  if (disabled.value) return
  sync()
  active.value = Math.max(0, options.value.findIndex(o => o.value === selected.value && !o.disabled))
  place()
  open.value = true
  await nextTick()
  reveal()
}
function reveal() { menu.value?.children[active.value]?.scrollIntoView({ block: 'nearest' }) }
function choose(index) {
  const option = options.value[index]
  if (!option || option.disabled) return
  native.value.value = option.value
  selected.value = option.value
  let value = option.value
  if (props.modelModifiers.number && value !== '' && !Number.isNaN(Number(value))) value = Number(value)
  emit('update:modelValue', value)
  native.value.dispatchEvent(new Event('change', { bubbles: true }))
  close()
  trigger.value?.focus()
}
function keydown(event) {
  if (event.key === 'Tab') { close(); return }
  if (event.key === 'Escape') { close(); event.preventDefault(); return }
  if (['Enter', ' '].includes(event.key)) {
    event.preventDefault()
    open.value ? choose(active.value) : show()
    return
  }
  if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
    event.preventDefault()
    if (!open.value) { show(); return }
    const indexes = options.value.map((o, i) => o.disabled ? -1 : i).filter(i => i >= 0)
    const at = indexes.indexOf(active.value)
    active.value = event.key === 'Home' ? indexes[0] : event.key === 'End' ? indexes.at(-1) : indexes[(at + (event.key === 'ArrowDown' ? 1 : -1) + indexes.length) % indexes.length]
    nextTick(reveal)
  } else if (event.key.length === 1) {
    clearTimeout(searchTimer)
    search += event.key.toLocaleLowerCase()
    const index = options.value.findIndex(o => !o.disabled && o.label.toLocaleLowerCase().startsWith(search))
    if (index >= 0) { active.value = index; if (!open.value) choose(index); else nextTick(reveal) }
    searchTimer = setTimeout(() => { search = '' }, 700)
  }
}
function outside(event) { if (!trigger.value?.contains(event.target) && !menu.value?.contains(event.target)) close() }
watch(disabled, value => { if (value) close() })
onMounted(() => {
  sync()
  observer = new MutationObserver(sync)
  observer.observe(native.value, { childList: true, subtree: true, attributes: true, characterData: true })
  document.addEventListener('pointerdown', outside)
  window.addEventListener('resize', close)
  window.addEventListener('scroll', place, true)
})
onUpdated(sync)
onBeforeUnmount(() => {
  observer?.disconnect()
  clearTimeout(searchTimer)
  document.removeEventListener('pointerdown', outside)
  window.removeEventListener('resize', close)
  window.removeEventListener('scroll', place, true)
})
</script>

<template>
  <select ref="native" :name="attrs.name" :disabled="disabled" tabindex="-1" aria-hidden="true" class="hidden" @change="emit('change', $event)"><slot /></select>
  <button v-bind="attrs" ref="trigger" type="button" :disabled="disabled" class="global-select" role="combobox" aria-haspopup="listbox" :aria-expanded="open" :aria-controls="menuId" :aria-activedescendant="open ? `${menuId}-${active}` : undefined" :aria-label="attrs['aria-label'] || label" @click="open ? close() : show()" @keydown="keydown" @blur="emit('blur', $event)">
    <span class="truncate">{{ label }}</span>
    <svg class="global-select-chevron" :class="{ 'rotate-180': open }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
  </button>
  <Teleport to="body">
    <div v-if="open" :id="menuId" ref="menu" role="listbox" class="global-select-menu" :style="position" @mousedown.prevent>
      <div v-for="(option, index) in options" :id="`${menuId}-${index}`" :key="index" role="option" :aria-selected="option.value === selected" :aria-disabled="option.disabled" class="global-select-option" :class="{ 'is-active': index === active, 'is-selected': option.value === selected, 'is-disabled': option.disabled }" @pointermove="active = index" @click="choose(index)">
        <span>{{ option.label }}</span><span v-if="option.value === selected" aria-hidden="true">✓</span>
      </div>
      <p v-if="!options.length" class="px-3 py-4 text-sm opacity-60">No hay opciones disponibles</p>
    </div>
  </Teleport>
</template>

<style>
.global-select { display: inline-flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 38px; padding: 9px 12px; border: 1px solid var(--border); border-radius: 12px; background-color: var(--surface); color: var(--text); background-image: none !important; text-align: left; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.global-select:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
.global-select:disabled { cursor: not-allowed; opacity: .5; }
.global-select-chevron { flex-shrink: 0; opacity: .6; transition: transform .15s; }
.global-select-menu { z-index: 2147483000; overflow-y: auto; padding: 6px; border-radius: 14px; border: 1px solid #e5e7eb; background: #fff; color: #25212a; box-shadow: 0 16px 48px #0002, 0 3px 8px #0001; }
.dark .global-select-menu { background: #211b20; color: #f7f2f5; border-color: #46373f; }
.global-select-option { display: flex; justify-content: space-between; gap: 20px; padding: 10px 12px; border-radius: 8px; font-size: 14px; cursor: pointer; }
.global-select-option.is-active { background: #f3f0f2; }
.global-select-option.is-selected { color: #d00020; background: #fff0f2; font-weight: 600; }
.dark .global-select-option.is-active { background: #382d34; }
.dark .global-select-option.is-selected { color: #ff91a1; background: #48242f; }
.global-select-option.is-disabled { opacity: .4; cursor: not-allowed; }
</style>
