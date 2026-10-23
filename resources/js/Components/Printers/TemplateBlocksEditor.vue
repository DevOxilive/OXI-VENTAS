<script setup>
import AnimatedCheckbox from "@/Components/Forms/AnimatedCheckbox.vue";
import { computed, ref } from "vue";
const props = defineProps({
    blocks: { type: Array, required: true },
    catalog: { type: Array, required: true },
    min: { type: Number, default: 60 },
    max: { type: Number, default: 180 },
    disabled: Boolean,
    fixedAlignmentKeys: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:blocks"]);
const selectedKey = ref("");
const selected = computed(
    () =>
        props.blocks.find((block) => block.key === selectedKey.value) ||
        props.blocks[0],
);
const label = (key) =>
    props.catalog.find((item) => item.key === key)?.label || key;
function update(field, value) {
    if (props.disabled || !selected.value) return;
    emit(
        "update:blocks",
        props.blocks.map((block) =>
            block.key === selected.value.key
                ? { ...block, [field]: value }
                : { ...block },
        ),
    );
}
function move(direction) {
    if (props.disabled) return;
    const index = props.blocks.findIndex(
        (block) => block.key === selected.value?.key,
    );
    const next = index + direction;
    if (index < 0 || next < 0 || next >= props.blocks.length) return;
    const blocks = props.blocks.map((block) => ({ ...block }));
    [blocks[index], blocks[next]] = [blocks[next], blocks[index]];
    selectedKey.value = selected.value.key;
    emit("update:blocks", blocks);
}
</script>
<template>
    <div class="space-y-5">
        <p class="text-sm text-text opacity-70">
            Selecciona un elemento para ajustar su apariencia. El orden de la
            lista es el orden del documento.
        </p>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="block in blocks"
                :key="block.key"
                type="button"
                :aria-pressed="selected?.key === block.key"
                class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition"
                :class="
                    selected?.key === block.key
                        ? 'border-primary bg-primary text-white'
                        : 'border-secondary bg-background text-text'
                "
                @click="selectedKey = block.key"
            >
                <span class="material-symbols-outlined text-base">{{
                    block.enabled ? "visibility" : "visibility_off"
                }}</span
                >{{ label(block.key) }}
            </button>
        </div>
        <fieldset
            v-if="selected"
            :disabled="disabled"
            class="space-y-5 rounded-2xl border border-secondary p-5 disabled:opacity-60"
        >
            <div class="flex items-center justify-between gap-3">
                <strong>{{ label(selected.key) }}</strong
                ><label class="flex items-center gap-2 text-sm"
                    ><AnimatedCheckbox

                        :model-value="selected.enabled"
                        @change="update('enabled', $event.target.checked)"
                    />
                    Mostrar</label
                >
            </div>
            <label class="block text-sm font-semibold"
                >Tamaño del texto (%)<input
                    class="mt-2 w-full accent-primary"
                    type="range"
                    :min="min"
                    :max="max"
                    :value="selected.size_percent"
                    @input="update('size_percent', Number($event.target.value))"
                /><span class="mt-1 block text-right font-normal"
                    >{{ selected.size_percent }}%</span
                ></label
            >
            <label class="block text-sm font-semibold"
                >Alineación<select
                    :disabled="fixedAlignmentKeys.includes(selected.key)"
                    class="mt-2 w-full rounded-xl border-secondary bg-background text-text"
                    :value="
                        selected.position_percent <= 25
                            ? 0
                            : selected.position_percent >= 75
                              ? 100
                              : 50
                    "
                    @change="
                        update('position_percent', Number($event.target.value))
                    "
                >
                    <option :value="0">Izquierda</option>
                    <option :value="50">Centro</option>
                    <option :value="100">Derecha</option>
                </select></label
            >
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-secondary px-4 py-2 text-sm disabled:opacity-40"
                    :disabled="blocks[0]?.key === selected.key"
                    @click="move(-1)"
                >
                    ↑ Subir</button
                ><button
                    type="button"
                    class="rounded-xl border border-secondary px-4 py-2 text-sm disabled:opacity-40"
                    :disabled="blocks[blocks.length - 1]?.key === selected.key"
                    @click="move(1)"
                >
                    ↓ Bajar
                </button>
            </div>
        </fieldset>
    </div>
</template>
