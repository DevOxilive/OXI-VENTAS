<script setup>
import { computed, onBeforeUnmount, ref } from "vue";
import { useForm } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { ToastAlert, WarningAlert } from "@/Components/Modales/UniversalActionModal";
import {
  buildTicketPreviewBlocks,
  createDefaultTicketTemplate,
  getTicketBlockCatalog,
  normalizeTicketTemplate,
  reorderTicketBlocks,
  saveStoredTicketTemplateSettings,
} from "@/config/ticketTemplate";

defineOptions({
  layout: AdminLayout,
});

const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
  samplePrintJob: {
    type: Object,
    required: true,
  },
});

const blockCatalog = getTicketBlockCatalog();
const paperWidthOptions = [
  { label: "58 mm / 5.8 cm", value: 32 },
  { label: "80 mm compacto", value: 42 },
  { label: "80/88 mm amplio", value: 48 },
];
const dragIndex = ref(null);
const previewPaper = ref(null);
const activeHorizontalKey = ref(null);
const activePointerBlockKey = ref(null);

const form = useForm({
  name: props.template?.name || "Ticket principal",
  is_active: props.template?.is_active ?? true,
  settings: normalizeTicketTemplate(props.template?.settings || createDefaultTicketTemplate()),
});

const previewTemplate = computed(() => normalizeTicketTemplate({ ...form.settings }));

const previewBlocks = computed(() => buildTicketPreviewBlocks(previewTemplate.value, props.samplePrintJob));
const paperClass = computed(() => {
  const width = Number(previewTemplate.value.paper_width || 32);

  if (width === 32) return "max-w-[17rem]";
  if (width === 42) return "max-w-[20rem]";

  return "max-w-[24rem]";
});

function updateBlock(index, field, value) {
  form.settings.blocks = form.settings.blocks.map((block, blockIndex) =>
    blockIndex === index ? { ...block, [field]: value } : { ...block }
  );
}

function updateBlockByKey(key, field, value) {
  const index = form.settings.blocks.findIndex((block) => block.key === key);

  if (index >= 0) {
    updateBlock(index, field, value);
  }
}

function resetTemplate() {
  const nextSettings = normalizeTicketTemplate(createDefaultTicketTemplate());

  form.defaults({
    name: "Ticket principal",
    is_active: true,
    settings: nextSettings,
  });

  form.reset();
  saveStoredTicketTemplateSettings(nextSettings);

  ToastAlert({
    title: "Plantilla restablecida",
  });
}

function saveTemplate() {
  form.settings = normalizeTicketTemplate({
    ...form.settings,
  });

  form
    .transform((data) => ({
      ...data,
      _method: "put",
    }))
    .post(route("printers.tickets.update", props.template.id), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        saveStoredTicketTemplateSettings(form.settings);
        ToastAlert({
          title: "Ticket actualizado correctamente",
        });
      },
      onError: () => {
        WarningAlert({
          title: "No se pudo guardar",
          message: "Revisa la configuracion del ticket e intenta nuevamente.",
        });
      },
      onFinish: () => {
        form.transform((data) => data);
      },
    });
}

function startDrag(index) {
  dragIndex.value = index;
}

function dropBlock(targetIndex) {
  if (dragIndex.value === null) {
    return;
  }

  form.settings.blocks = reorderTicketBlocks(form.settings.blocks, dragIndex.value, targetIndex);
  dragIndex.value = null;
}

function clampPercent(value, min, max) {
  const numeric = Number(value);

  if (!Number.isFinite(numeric)) {
    return min;
  }

  return Math.max(min, Math.min(max, Math.round(numeric)));
}

function textStyle(row) {
  const centeredText = row.type === "text" && row.block_key === "document_title";

  return {
    fontSize: `${Math.max(10, Math.round((row.size_percent || 100) * 0.1))}px`,
    paddingLeft: centeredText ? "0%" : `${Math.max(0, Math.min(78, row.position_percent || 0)) * 0.65}%`,
    textAlign: centeredText ? "center" : "left",
  };
}

function startHorizontalAdjust(event, blockKey) {
  if (event.target?.closest?.("[data-drag-handle='true']")) {
    return;
  }

  activePointerBlockKey.value = blockKey;
  activeHorizontalKey.value = blockKey;
  updatePreviewBlockPosition(event, blockKey);
  window.addEventListener("pointermove", handlePointerMove);
  window.addEventListener("pointerup", stopHorizontalAdjust);
}

function handlePointerMove(event) {
  if (!activePointerBlockKey.value) {
    return;
  }

  updatePreviewBlockPosition(event, activePointerBlockKey.value);
}

function stopHorizontalAdjust() {
  activePointerBlockKey.value = null;
  activeHorizontalKey.value = null;
  window.removeEventListener("pointermove", handlePointerMove);
  window.removeEventListener("pointerup", stopHorizontalAdjust);
}

function updatePreviewBlockPosition(event, blockKey) {
  const container = previewPaper.value;

  if (!container) {
    return;
  }

  const rect = container.getBoundingClientRect();
  const percent = clampPercent(((event.clientX - rect.left) / rect.width) * 100, 0, 100);

  updateBlockByKey(blockKey, "position_percent", percent);

  const previewItems = [...container.querySelectorAll("[data-preview-block-key]")];
  const hoverIndex = previewItems.findIndex((item) => {
    const itemRect = item.getBoundingClientRect();
    return event.clientY >= itemRect.top && event.clientY <= itemRect.bottom;
  });

  const currentIndex = form.settings.blocks.findIndex((block) => block.key === blockKey);

  if (hoverIndex >= 0 && currentIndex >= 0 && hoverIndex !== currentIndex) {
    form.settings.blocks = reorderTicketBlocks(form.settings.blocks, currentIndex, hoverIndex);
  }
}

onBeforeUnmount(() => {
  stopHorizontalAdjust();
});
</script>

<template>
  <PageLayout :padded="false">
    <div class="px-4 py-4 2xl:px-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
              Impresoras
            </p>
            <h1 class="mt-1 text-xl font-black text-slate-900">
              Tickets
            </h1>
            <p class="mt-1 text-[13px] text-slate-500">
              Ajusta el ticket con una plantilla visual y libre para impresion.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <button
              type="button"
              class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
              @click="resetTemplate"
            >
              Restablecer
            </button>

            <button
              type="button"
              class="rounded-2xl bg-[#1f1d2b] px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="form.processing"
              @click="saveTemplate"
            >
              {{ form.processing ? "Guardando..." : "Guardar" }}
            </button>
          </div>
        </div>

        <div class="mt-4 space-y-4">
          <div class="grid grid-cols-2 items-start gap-3">
            <div
              class="min-w-0 space-y-3 overflow-y-auto pr-1"
              style="max-height: calc(100vh - 2rem);"
            >
              <div class="grid gap-3 lg:grid-cols-2">
                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Plantilla</span>
                  <input
                    v-model="form.name"
                    type="text"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                    placeholder="Ticket principal"
                  >
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Activa</span>
                  <div class="mt-2 flex justify-end">
                    <input
                      v-model="form.is_active"
                      type="checkbox"
                      class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
                    >
                  </div>
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Marca</span>
                  <input
                    v-model="form.settings.header_text"
                    type="text"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                  >
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Titulo</span>
                  <input
                    v-model="form.settings.subheader_text"
                    type="text"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                  >
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Caja #</span>
                  <input
                    v-model="form.settings.cash_box_text"
                    type="text"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                    placeholder="Caja #1"
                  >
                </label>
              </div>

              <label class="block rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Pie</span>
                <textarea
                  v-model="form.settings.footer_text"
                  rows="3"
                  class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                />
              </label>

              <div class="grid gap-3 lg:grid-cols-4">
                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Ancho</span>
                  <select
                    v-model="form.settings.paper_width"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                  >
                    <option
                      v-for="option in paperWidthOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Motor</span>
                  <div class="mt-2 rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                    Estable ESC/POS
                  </div>
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Saltos</span>
                  <input
                    v-model.number="form.settings.feed_lines"
                    type="number"
                    min="1"
                    max="1"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                  >
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Corte</span>
                  <div class="mt-2 flex justify-end">
                    <input
                      v-model="form.settings.auto_cut"
                      type="checkbox"
                      class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
                    >
                  </div>
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Cajon</span>
                  <div class="mt-2 flex justify-end">
                    <input
                      v-model="form.settings.open_cash_drawer"
                      type="checkbox"
                      class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
                    >
                  </div>
                </label>

                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Lineas</span>
                  <div class="mt-2 flex justify-end">
                    <input
                      v-model="form.settings.show_dividers"
                      type="checkbox"
                      class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
                    >
                  </div>
                </label>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <div class="mb-3 flex items-center justify-between">
                  <h3 class="text-sm font-bold text-slate-900">Bloques</h3>
                  <span class="text-xs text-slate-400">Ajuste rapido</span>
                </div>

                <div class="grid gap-3 lg:grid-cols-3">
                  <article
                    v-for="(block, index) in form.settings.blocks"
                    :key="block.key"
                    draggable="true"
                    class="rounded-2xl border border-slate-200 bg-white p-3"
                    @dragstart="startDrag(index)"
                    @dragover.prevent
                    @drop="dropBlock(index)"
                  >
                    <div class="space-y-3">
                      <p class="text-sm font-bold text-slate-900">
                        {{ blockCatalog.find((item) => item.key === block.key)?.label || block.key }}
                      </p>

                      <div class="flex items-center justify-between">
                        <button
                          type="button"
                          class="flex h-9 w-9 items-center justify-center rounded-xl border transition"
                          :class="block.enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-400'"
                          @click="updateBlock(index, 'enabled', !block.enabled)"
                        >
                          <span class="material-symbols-outlined text-[18px]">visibility</span>
                        </button>

                        <input
                          :value="block.size_percent"
                          type="number"
                          min="60"
                          max="180"
                          class="w-24 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-center text-sm text-slate-900 outline-none transition focus:border-slate-400"
                          @input="updateBlock(index, 'size_percent', clampPercent($event.target.value, 60, 180))"
                        >
                      </div>
                    </div>
                  </article>
                </div>
              </div>
            </div>

            <aside class="sticky top-4 h-fit self-start">
              <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="border-b border-slate-200 pb-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Ticket en vivo
                  </p>
                  <h2 class="mt-1 text-lg font-black text-slate-900 lg:text-xl">
                    Vista previa
                  </h2>
                  <p class="mt-1 text-xs text-slate-500 lg:text-sm">
                    Arrastra cada bloque aqui mismo para moverlo a la izquierda, derecha, arriba o abajo.
                  </p>
                </div>

                <div class="mt-4 flex justify-center">
                  <div class="w-full rounded-[24px] bg-slate-100 p-2.5 lg:p-3">
                    <div
                      ref="previewPaper"
                      class="mx-auto w-full rounded-[28px] border border-slate-200 bg-white px-4 py-5 shadow-sm"
                      :class="paperClass"
                    >
                      <div class="space-y-1 text-slate-900">
                        <article
                          v-for="(previewBlock, index) in previewBlocks"
                          :key="previewBlock.key"
                          :data-preview-block-key="previewBlock.key"
                          class="relative rounded-xl border border-transparent px-2 py-2 transition"
                          :class="[
                            activeHorizontalKey === previewBlock.key ? 'bg-slate-50 ring-1 ring-slate-200' : 'hover:border-slate-200 hover:bg-slate-50/70',
                            dragIndex === index ? 'border-slate-300 bg-slate-50/80 opacity-70' : '',
                          ]"
                          @pointerdown.prevent="startHorizontalAdjust($event, previewBlock.key)"
                        >
                          <template v-for="(row, rowIndex) in previewBlock.rows" :key="`${previewBlock.key}-${row.type}-${rowIndex}`">
                            <p
                              v-if="row.type === 'text'"
                              class="break-words font-semibold"
                              :class="row.bold ? 'font-black' : 'font-semibold'"
                              :style="textStyle(row)"
                            >
                              {{ row.text }}
                            </p>

                            <p
                              v-else-if="row.type === 'divider'"
                              class="overflow-hidden text-[10px] tracking-[0.12em] text-slate-400"
                            >
                              {{ row.text }}
                            </p>

                            <p
                              v-else-if="row.type === 'pair'"
                              class="break-words font-medium"
                              :style="textStyle(row)"
                            >
                              {{ row.label }}: {{ row.value }}
                            </p>

                            <div
                              v-else-if="row.type === 'columns'"
                              class="flex items-start justify-between gap-3"
                              :class="row.bold ? 'font-black' : 'font-medium'"
                              :style="textStyle(row)"
                            >
                              <span class="min-w-0 flex-1 break-words">{{ row.left }}</span>
                              <span class="shrink-0 text-right">{{ row.right }}</span>
                            </div>

                            <div
                              v-else-if="row.type === 'center_right'"
                              class="relative min-h-[1.25rem] font-bold"
                              :style="{
                                minHeight: `${Math.round(Math.max(
                                  Math.max(10, Math.round((row.center_size_percent || row.size_percent || 100) * 0.1)),
                                  Math.max(10, Math.round((row.right_size_percent || row.size_percent || 100) * 0.1))
                                ) * 1.35)}px`,
                              }"
                            >
                              <span
                                class="block truncate pr-24 text-center"
                                :style="{ fontSize: `${Math.max(10, Math.round((row.center_size_percent || row.size_percent || 100) * 0.1))}px` }"
                              >{{ row.center }}</span>
                              <span
                                class="absolute right-0 top-0 max-w-[44%] truncate whitespace-nowrap text-right"
                                :style="{ fontSize: `${Math.max(10, Math.round((row.right_size_percent || row.size_percent || 100) * 0.1))}px` }"
                              >{{ row.right }}</span>
                            </div>
                          </template>
                        </article>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </section>
    </div>
  </PageLayout>
</template>
