<script setup>
import { paperFormatsFor, applyPaperFormat } from "@/config/printPaperFormats";
import AnimatedCheckbox from "@/Components/Forms/AnimatedCheckbox.vue";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import InputField from "@/Components/Forms/InputField.vue";
import {
    WarningAlert,
    ToastAlert,
} from "@/Components/Modales/UniversalActionModal";
import { usePermissions } from "@/Composables/usePermissions";
import {
    createVisualLayout,
    createVisualElement,
    DOCUMENT_KINDS,
    FIELD_LABELS,
    sampleVisualData,
} from "@/config/visualPrintLayout";
import {
    drawVisualStage,
    visualAssets,
    renderVisualPrint,
} from "@/Components/Printers/visualPrintRenderer";
import {
    getQzPrinters,
    findLabelPrinter,
    findTicketPrinter,
    printImageLabel,
    printEscPosTicket,
} from "@/Composables/useQzTray";

defineOptions({ layout: AdminLayout });
const props = defineProps({
    kind: { type: String, required: true },
    saved: { type: Object, required: true },
});
const { can } = usePermissions();
const permissionPrefix = (kind) =>
    kind === "label"
        ? "systems.labels"
        : kind === "closure"
          ? "systems.cash-closure-tickets"
          : "systems.tickets";
const canEdit = computed(() => can(`${permissionPrefix(props.kind)}.update`));
const canPrint = computed(() =>
    props.kind === "label"
        ? can("systems.labels.print")
        : can("systems.qz.sign"),
);
const clone = (value) => JSON.parse(JSON.stringify(value));
const layout = ref(
    clone(
        props.saved.layout ||
            applyPaperFormat(
                createVisualLayout(props.kind),
                props.kind === "label" ? "continuous-62" : "receipt-80",
            ),
    ),
);
const name = ref(
    props.saved.name ||
        `Diseño visual · ${DOCUMENT_KINDS.find((k) => k.key === props.kind)?.label}`,
);
const persisted = ref(clone(props.saved));
const snapshot = () =>
    JSON.stringify({ layout: layout.value, name: name.value });
const savedSnapshot = ref(props.saved.layout ? snapshot() : "");
const dirty = computed(() => snapshot() !== savedSnapshot.value);
const unpublished = computed(
    () =>
        JSON.stringify(persisted.value.layout) !==
        JSON.stringify(persisted.value.published),
);
const selectedId = ref(null);
const selected = computed(() =>
    layout.value.elements.find((e) => e.id === selectedId.value),
);
const canvasHost = ref(null);
const zoom = ref(1.5);
const grid = ref(true);
const preview = ref(false);
const sampleCount = ref(3);
const sample = computed(() => sampleVisualData(props.kind, sampleCount.value));
const error = ref("");
const busy = ref(false);
const actualHeight = ref(layout.value.height);
const past = ref([]),
    future = ref([]);
let stage,
    timer,
    generation = 0;
const elements = [
    { type: "text", label: "Texto", icon: "title" },
    { type: "field", label: "Dato variable", icon: "data_object" },
    { type: "logo", label: "Logo", icon: "image" },
    { type: "items", label: "Detalle dinámico", icon: "view_list" },
    { type: "barcode", label: "Código de barras", icon: "barcode" },
    { type: "line", label: "Línea", icon: "horizontal_rule" },
    { type: "rect", label: "Recuadro", icon: "crop_square" },
];
const toolbar = computed(() => ({
    title: "Editor visual",
    subtitle:
        "Diseña con elementos independientes. Guardar un borrador no cambia las impresiones actuales.",
    icon: "draw",
    showSearch: false,
    showCounter: false,
    showRecordsPerPage: false,
    backButton: true,
    backLabel: "Plantillas",
    tabs: DOCUMENT_KINDS.filter(
        (k) =>
            can(`${permissionPrefix(k.key)}.view`) ||
            can(`${permissionPrefix(k.key)}.update`),
    ).map((k) => ({ key: k.key, label: k.label })),
    activeTab: props.kind,
    actions: [
        {
            id: "export",
            label: "Exportar diseño",
            icon: "download",
            variant: "slate",
        },
        ...(canPrint.value
            ? [
                  {
                      id: "test",
                      label: busy.value ? "Procesando…" : "Imprimir prueba",
                      icon: "print",
                      variant: "slate",
                      disabled: busy.value,
                  },
              ]
            : []),
        ...(canEdit.value
            ? [
                  {
                      id: "save",
                      label: busy.value ? "Guardando…" : "Guardar borrador",
                      icon: "save",
                      variant: "primary",
                      disabled: busy.value,
                  },
                  {
                      id: "activate",
                      label: persisted.value.active
                          ? "Publicar cambios"
                          : "Usar este diseño",
                      icon: "check_circle",
                      variant: "green",
                      disabled:
                          busy.value || dirty.value || !persisted.value.layout,
                  },
                  ...(persisted.value.active
                      ? [
                            {
                                id: "legacy",
                                label: "Volver al diseño anterior",
                                icon: "restore",
                                variant: "slate",
                                disabled: busy.value,
                            },
                        ]
                      : []),
              ]
            : []),
    ],
}));
function commit(next) {
    if (!canEdit.value) return;
    past.value = [...past.value.slice(-49), clone(layout.value)];
    future.value = [];
    layout.value = next;
}
function changeElement(id, patch) {
    if (!canEdit.value) return;
    const next = clone(layout.value);
    const element = next.elements.find((e) => e.id === id);
    if (!element) return;
    Object.assign(element, patch);
    for (const key of ["x", "y", "width", "height", "font_size", "stroke"])
        element[key] = Math.round(Number(element[key]) * 100) / 100;
    commit(next);
}
function edit(field, value) {
    if (selected.value) changeElement(selected.value.id, { [field]: value });
}
function add(type) {
    const next = clone(layout.value);
    const element = createVisualElement(type, {
        width: Math.min(40, next.width - 8),
        height: Math.min(type === "items" ? 25 : 8, next.height - 8),
        binding: props.kind === "label" ? "name" : "folio",
    });
    if (type === "barcode")
        element.binding = props.kind === "label" ? "barcode" : "folio";
    if (type === "logo") {
        element.width = Math.min(14, next.width - 8);
        element.height = Math.min(14, next.height - 8);
    }
    next.elements.push(element);
    commit(next);
    selectedId.value = element.id;
}
function remove() {
    if (!selected.value) return;
    const next = clone(layout.value);
    next.elements = next.elements.filter((e) => e.id !== selectedId.value);
    commit(next);
    selectedId.value = null;
}
function duplicate() {
    if (!selected.value) return;
    const next = clone(layout.value);
    const copy = createVisualElement(selected.value.type, {
        ...clone(selected.value),
        id: createVisualElement("text").id,
    });
    copy.x = Math.min(next.width - copy.width, copy.x + 2);
    copy.y = Math.min(next.height - copy.height, copy.y + 2);
    next.elements.push(copy);
    commit(next);
    selectedId.value = copy.id;
}
function reorder(delta) {
    const next = clone(layout.value);
    const index = next.elements.findIndex((e) => e.id === selectedId.value);
    const to = index + delta;
    if (index < 0 || to < 0 || to >= next.elements.length) return;
    [next.elements[index], next.elements[to]] = [
        next.elements[to],
        next.elements[index],
    ];
    commit(next);
}
function alignPage(align) {
    if (!selected.value) return;
    edit(
        "x",
        align === "left"
            ? 0
            : align === "right"
              ? layout.value.width - selected.value.width
              : (layout.value.width - selected.value.width) / 2,
    );
}
function undo() {
    if (!canEdit.value || !past.value.length) return;
    future.value.push(clone(layout.value));
    layout.value = past.value.pop();
}
function redo() {
    if (!canEdit.value || !future.value.length) return;
    past.value.push(clone(layout.value));
    layout.value = future.value.pop();
}
const paperFormats = computed(() => paperFormatsFor(props.kind));
const currentPaperFormat = computed(() =>
    paperFormats.value.find(
        (format) => format.id === layout.value.paper_format,
    ),
);
function selectPaperFormat(id) {
    commit(applyPaperFormat(layout.value, id));
}
function paper(field, value) {
    const next = clone(layout.value);
    if (field === "width") {
        value = Math.max(20, Math.min(210, value || 20));
        const ratio = value / next.width;
        next.elements.forEach((element) => {
            element.x = Math.round(element.x * ratio * 100) / 100;
            element.width = Math.max(
                1,
                Math.round(element.width * ratio * 100) / 100,
            );
        });
    }
    if (field === "height") value = Math.max(12, Math.min(500, value || 12));
    next[field] = value;
    commit(next);
}
async function redraw() {
    const current = ++generation;
    try {
        const images = await visualAssets(layout.value, sample.value);
        if (current !== generation || !canvasHost.value) return;
        stage?.destroy();
        const drawn = drawVisualStage(
            canvasHost.value,
            layout.value,
            sample.value,
            images,
            {
                zoom: zoom.value,
                grid: grid.value && !preview.value,
                editing: canEdit.value && !preview.value,
                selectedId: selectedId.value,
                onSelect: (id) => (selectedId.value = id),
                onChange: changeElement,
            },
        );
        stage = drawn.stage;
        actualHeight.value = drawn.geometry.height;
        error.value = drawn.geometry.overflow
            ? "El detalle supera el papel. Activa longitud automática o aumenta el alto."
            : "";
    } catch (e) {
        if (current === generation) error.value = e.message;
    }
}
function scheduleDraw() {
    generation++;
    clearTimeout(timer);
    timer = setTimeout(redraw, 40);
}
watch([layout, selectedId, zoom, grid, preview, sampleCount], scheduleDraw, {
    deep: true,
});
function keydown(event) {
    if (event.target.closest('input,textarea,select,[contenteditable="true"]'))
        return;
    if (!canEdit.value || preview.value) return;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "z") {
        event.preventDefault();
        event.shiftKey ? redo() : undo();
    } else if (event.key === "Delete") {
        event.preventDefault();
        remove();
    } else if (
        selected.value &&
        !selected.value.locked &&
        ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(event.key)
    ) {
        event.preventDefault();
        const step = event.shiftKey ? 2 : 0.5;
        const dx =
            event.key === "ArrowLeft"
                ? -step
                : event.key === "ArrowRight"
                  ? step
                  : 0;
        const dy =
            event.key === "ArrowUp"
                ? -step
                : event.key === "ArrowDown"
                  ? step
                  : 0;
        changeElement(selectedId.value, {
            x: Math.max(
                0,
                Math.min(
                    layout.value.width - selected.value.width,
                    selected.value.x + dx,
                ),
            ),
            y: Math.max(
                0,
                Math.min(
                    layout.value.height - selected.value.height,
                    selected.value.y + dy,
                ),
            ),
        });
    }
}
function beforeUnload(event) {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = "";
}
const removeNavigationGuard = router.on("before", () => {
    if (
        dirty.value &&
        !window.confirm("Hay cambios sin guardar. ¿Salir y descartarlos?")
    )
        return false;
});
onMounted(async () => {
    await nextTick();
    redraw();
    window.addEventListener("keydown", keydown);
    window.addEventListener("beforeunload", beforeUnload);
});
onBeforeUnmount(() => {
    generation++;
    clearTimeout(timer);
    stage?.destroy();
    window.removeEventListener("keydown", keydown);
    window.removeEventListener("beforeunload", beforeUnload);
    removeNavigationGuard();
});
function back() {
    router.get(
        props.kind === "label"
            ? route("printers.labels.index")
            : props.kind === "closure"
              ? route("printers.cash-closure-tickets.index")
              : route("printers.tickets.index"),
    );
}
function navigate(kind) {
    if (kind !== props.kind)
        router.get(
            route("printers.visual-editor.show", { kind }),
            {},
            { preserveState: false },
        );
}
function download(content, filename, type) {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
async function action(id) {
    if (busy.value) return;
    if (id === "export") {
        download(
            JSON.stringify(
                { name: name.value, kind: props.kind, layout: layout.value },
                null,
                2,
            ),
            `plantilla-${props.kind}.json`,
            "application/json",
        );
        return;
    }
    if (id !== "test" && !canEdit.value) return;
    if (id === "test" && !canPrint.value) return;
    busy.value = true;
    try {
        if (id === "save") {
            const { data } = await window.axios.put(
                route("printers.visual-editor.save", { kind: props.kind }),
                {
                    name: name.value,
                    layout: layout.value,
                    revision: persisted.value.revision,
                },
            );
            persisted.value = data;
            savedSnapshot.value = snapshot();
            ToastAlert({ title: "Borrador guardado" });
        } else if (id === "activate" || id === "legacy") {
            if (id === "activate" && dirty.value)
                throw new Error("Guarda el borrador antes de publicarlo.");
            const { data } = await window.axios.post(
                route("printers.visual-editor.activate", { kind: props.kind }),
                {
                    active: id === "activate",
                    revision: persisted.value.revision,
                },
            );
            persisted.value = data;
            ToastAlert({
                title:
                    id === "activate"
                        ? "Diseño visual publicado. Recarga las pantallas de venta abiertas."
                        : "La plantilla anterior vuelve a utilizarse.",
            });
        } else if (id === "test") {
            const printers = await getQzPrinters();
            const printer =
                props.kind === "label"
                    ? findLabelPrinter(printers)
                    : findTicketPrinter(printers);
            if (!printer)
                throw new Error(
                    "Asigna una impresora en Configurar impresoras.",
                );
            if (props.kind === "label") {
                const output = await renderVisualPrint(
                    layout.value,
                    sample.value,
                    { dpi: layout.value.dpi || 300 },
                );
                await printImageLabel(printer, output.base64, output, 1);
            } else
                await printEscPosTicket(printer, [
                    {
                        type: "visual-layout",
                        layout: layout.value,
                        data: sample.value,
                        settings: {
                            auto_cut: true,
                            feed_lines: 1,
                            open_cash_drawer: false,
                        },
                    },
                ]);
            ToastAlert({ title: "Prueba enviada a la impresora" });
        }
    } catch (e) {
        WarningAlert({
            title: "No se pudo completar",
            message:
                Object.values(e.response?.data?.errors || {})
                    .flat()
                    .join(" ") ||
                e.response?.data?.message ||
                e.message,
        });
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <PageLayout>
        <template #toolbar
            ><GlobalToolbar
                v-bind="toolbar"
                @action="action"
                @back="back"
                @update:active-tab="navigate"
        /></template>
        <div
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-text"
        >
            <span
                class="rounded-full border border-secondary bg-background px-4 py-2"
                >{{
                    persisted.active
                        ? "Diseño visual en uso"
                        : "La plantilla anterior sigue en uso"
                }}
                ·
                {{
                    dirty
                        ? "Cambios sin guardar"
                        : unpublished
                          ? "Borrador pendiente de publicar"
                          : "Guardado"
                }}</span
            ><span>Diseño en milímetros · Ctrl+Z para deshacer</span>
        </div>
        <div
            v-if="error"
            role="alert"
            class="rounded-xl border border-amber-500 bg-amber-50 p-3 text-sm text-amber-900"
        >
            {{ error }}
        </div>
        <div
            class="grid items-start gap-4 text-text xl:grid-cols-[220px_minmax(0,1fr)_280px]"
        >
            <aside
                class="space-y-5 rounded-2xl border border-secondary bg-background p-4"
            >
                <InputField
                    v-model="name"
                    label="Nombre del diseño"
                    field="visual_template_name"
                    :readonly="!canEdit"
                    :show-counter="false"
                />
                <div>
                    <h3 class="mb-3 text-sm font-bold">Agregar elemento</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="element in elements"
                            :key="element.type"
                            type="button"
                            :disabled="
                                !canEdit || layout.elements.length >= 100
                            "
                            class="flex flex-col items-center gap-2 rounded-xl border border-secondary p-3 text-xs font-semibold transition hover:border-primary hover:bg-secondary disabled:opacity-40"
                            @click="add(element.type)"
                        >
                            <span
                                class="material-symbols-outlined text-primary"
                                >{{ element.icon }}</span
                            >{{ element.label }}
                        </button>
                    </div>
                </div>
                <div>
                    <h3 class="mb-3 text-sm font-bold">Capas</h3>
                    <div class="max-h-72 space-y-1 overflow-y-auto">
                        <button
                            v-for="element in [...layout.elements].reverse()"
                            :key="element.id"
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs"
                            :class="
                                element.id === selectedId
                                    ? 'bg-primary text-white'
                                    : 'hover:bg-secondary'
                            "
                            @click="selectedId = element.id"
                        >
                            <span class="material-symbols-outlined text-base">{{
                                element.locked
                                    ? "lock"
                                    : element.visible
                                      ? "visibility"
                                      : "visibility_off"
                            }}</span
                            ><span class="truncate">{{
                                element.type === "field"
                                    ? FIELD_LABELS[element.binding]
                                    : element.text ||
                                      elements.find(
                                          (e) => e.type === element.type,
                                      )?.label
                            }}</span>
                        </button>
                    </div>
                </div>
            </aside>
            <main
                class="min-w-0 overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm"
            >
                <div
                    class="flex flex-wrap items-center gap-2 border-b border-secondary p-3"
                >
                    <button
                        type="button"
                        class="editor-button"
                        :disabled="!past.length || !canEdit"
                        @click="undo"
                    >
                        ↶ Deshacer</button
                    ><button
                        type="button"
                        class="editor-button"
                        :disabled="!future.length || !canEdit"
                        @click="redo"
                    >
                        ↷ Rehacer</button
                    ><label class="flex items-center gap-2 text-xs"
                        >Zoom<select
                            v-model.number="zoom"
                            class="editor-select"
                        >
                            <option :value="0.75">75%</option>
                            <option :value="1">100%</option>
                            <option :value="1.5">150%</option>
                            <option :value="2">200%</option>
                        </select></label
                    ><label class="flex items-center gap-2 text-xs"
                        ><AnimatedCheckbox v-model="grid" />Guías</label
                    ><button
                        type="button"
                        class="editor-button ml-auto"
                        :aria-pressed="preview"
                        @click="preview = !preview"
                    >
                        {{ preview ? "Volver a editar" : "Vista de impresión" }}
                    </button>
                </div>
                <div
                    class="max-h-[75vh] min-h-96 overflow-auto bg-slate-100 p-6"
                >
                    <div class="mb-2 text-center text-xs text-slate-500">
                        {{ layout.width }} ×
                        {{ Math.round(actualHeight * 10) / 10 }} mm ·
                        {{
                            preview
                                ? "Sin controles de edición"
                                : "Selecciona y arrastra. Usa las esquinas para cambiar el tamaño."
                        }}
                    </div>
                    <div
                        ref="canvasHost"
                        class="mx-auto w-fit bg-white shadow-lg"
                    />
                </div>
                <div
                    class="flex items-center justify-between gap-3 border-t border-secondary p-3 text-xs"
                >
                    <span>Datos de ejemplo</span
                    ><label class="flex items-center gap-2"
                        >Cantidad de filas<select
                            v-model.number="sampleCount"
                            class="editor-select"
                        >
                            <option :value="1">1</option>
                            <option :value="3">3</option>
                            <option :value="15">15</option>
                            <option :value="40">40</option>
                        </select></label
                    >
                </div>
            </main>
            <aside
                class="space-y-5 rounded-2xl border border-secondary bg-background p-4"
            >
                <fieldset :disabled="!canEdit" class="space-y-3">
                    <legend class="mb-3 text-sm font-bold">Papel</legend>
                    <label class="editor-field"
                        >Tipo de papel
                        <select
                            :value="currentPaperFormat?.id || 'custom'"
                            @change="selectPaperFormat($event.target.value)"
                        >
                            <option
                                v-for="format in paperFormats"
                                :key="format.id"
                                :value="format.id"
                            >
                                {{ format.label }}
                            </option>
                            <option value="custom">
                                Otro formato / configuración existente
                            </option>
                        </select>
                    </label>
                    <p class="text-xs opacity-70">
                        {{
                            currentPaperFormat?.note ||
                            "Se conservan las medidas de este diseño. Elige un formato de la lista o introduce las medidas indicadas por el fabricante y el controlador."
                        }}
                    </p>
                    <p
                        v-if="currentPaperFormat"
                        class="rounded-lg bg-secondary p-3 text-xs"
                    >
                        Rollo: {{ currentPaperFormat.paperWidth }} mm ·
                        {{
                            kind === "label"
                                ? "Ancho de página"
                                : "Área de diseño"
                        }}: {{ layout.width }} mm
                    </p>
                    <label v-if="!currentPaperFormat" class="editor-field"
                        >{{
                            kind === "label"
                                ? "Ancho de papel (mm)"
                                : "Ancho imprimible (mm)"
                        }}
                        <input
                            :value="layout.width"
                            type="number"
                            min="20"
                            max="210"
                            step="0.1"
                            @change="
                                paper('width', Number($event.target.value))
                            "
                        />
                    </label>
                    <label class="editor-field"
                        >{{
                            layout.auto_height
                                ? "Longitud mínima del diseño (mm)"
                                : "Longitud de corte (mm)"
                        }}
                        <input
                            :value="layout.height"
                            type="number"
                            min="12"
                            max="500"
                            @change="
                                paper('height', Number($event.target.value))
                            "
                        />
                    </label>
                    <label class="flex items-center gap-2 text-xs">
                        <AnimatedCheckbox
                            :model-value="layout.auto_height"
                            @change="
                                paper('auto_height', $event.target.checked)
                            "
                        />
                        Ajustar longitud al contenido
                    </label>
                    <p class="text-xs opacity-60">
                        En rollo continuo, el ancho lo define el papel; la
                        longitud la define tu diseño. La longitud mínima reserva
                        espacio y el detalle puede hacerla crecer.
                    </p>
                    <details class="border-t border-secondary pt-3">
                        <summary class="cursor-pointer text-xs font-semibold">
                            Ajustes del equipo
                        </summary>
                        <label class="editor-field mt-3"
                            >Resolución de la impresora
                            <select
                                :value="
                                    layout.dpi || (kind === 'label' ? 300 : 203)
                                "
                                @change="
                                    paper('dpi', Number($event.target.value))
                                "
                            >
                                <option :value="203">203 dpi</option>
                                <option :value="300">300 dpi</option>
                            </select>
                        </label>
                        <p class="mt-2 text-xs opacity-60">
                            Usa la resolución indicada por el fabricante. Para
                            un área imprimible diferente, selecciona Otro
                            formato.
                        </p>
                    </details>
                </fieldset>
                <fieldset
                    v-if="selected"
                    :disabled="!canEdit"
                    class="space-y-4 border-t border-secondary pt-4"
                >
                    <legend class="text-sm font-bold">
                        Elemento seleccionado
                    </legend>
                    <label
                        v-if="['text', 'field'].includes(selected.type)"
                        class="editor-field"
                        >{{
                            selected.type === "field"
                                ? "Prefijo (opcional)"
                                : "Texto"
                        }}<textarea
                            :value="selected.text"
                            rows="3"
                            @change="edit('text', $event.target.value)"
                        />
                    </label>
                    <label
                        v-if="['field', 'barcode'].includes(selected.type)"
                        class="editor-field"
                        >Dato del documento<select
                            :value="selected.binding"
                            @change="edit('binding', $event.target.value)"
                        >
                            <option
                                v-for="(label, key) in FIELD_LABELS"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select></label
                    >
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="field in ['x', 'y', 'width', 'height']"
                            :key="field"
                            class="editor-field"
                            >{{
                                {
                                    x: "Izquierda",
                                    y: "Arriba",
                                    width: "Ancho",
                                    height: "Alto",
                                }[field]
                            }}
                            (mm)<input
                                :value="selected[field]"
                                type="number"
                                step="0.5"
                                min="0"
                                @change="
                                    edit(field, Number($event.target.value))
                                "
                        /></label>
                    </div>
                    <div
                        v-if="
                            ['text', 'field', 'items'].includes(selected.type)
                        "
                        class="space-y-3"
                    >
                        <label class="editor-field"
                            >Letra (puntos)<input
                                :value="selected.font_size"
                                type="number"
                                min="4"
                                max="72"
                                @change="
                                    edit(
                                        'font_size',
                                        Number($event.target.value),
                                    )
                                " /></label
                        ><label class="flex items-center gap-2 text-xs"
                            ><AnimatedCheckbox
                                :model-value="selected.bold"
                                @change="edit('bold', $event.target.checked)"
                            />Negrita</label
                        ><label class="editor-field"
                            >Alineación del texto<select
                                :value="selected.align"
                                @change="edit('align', $event.target.value)"
                            >
                                <option value="left">Izquierda</option>
                                <option value="center">Centro</option>
                                <option value="right">Derecha</option>
                            </select></label
                        >
                    </div>
                    <label
                        v-if="['line', 'rect'].includes(selected.type)"
                        class="editor-field"
                        >Grosor (mm)<input
                            :value="selected.stroke"
                            type="number"
                            min="0"
                            max="3"
                            step="0.1"
                            @change="
                                edit('stroke', Number($event.target.value))
                            "
                    /></label>
                    <div>
                        <p class="mb-2 text-xs font-semibold">
                            Alinear elemento al papel
                        </p>
                        <div class="flex gap-1">
                            <button
                                v-for="alignment in ['left', 'center', 'right']"
                                :key="alignment"
                                type="button"
                                class="editor-button flex-1"
                                @click="alignPage(alignment)"
                            >
                                {{
                                    { left: "←", center: "↔", right: "→" }[
                                        alignment
                                    ]
                                }}
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-4 text-xs">
                        <label class="flex gap-2"
                            ><AnimatedCheckbox
                                :model-value="selected.visible"
                                @change="edit('visible', $event.target.checked)"
                            />Visible</label
                        ><label class="flex gap-2"
                            ><AnimatedCheckbox
                                :model-value="selected.locked"
                                @change="edit('locked', $event.target.checked)"
                            />Bloqueado</label
                        >
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="editor-button"
                            @click="reorder(1)"
                        >
                            Al frente</button
                        ><button
                            type="button"
                            class="editor-button"
                            @click="reorder(-1)"
                        >
                            Atrás</button
                        ><button
                            type="button"
                            class="editor-button"
                            @click="duplicate"
                        >
                            Duplicar</button
                        ><button
                            type="button"
                            class="editor-button text-red-600"
                            @click="remove"
                        >
                            Eliminar
                        </button>
                    </div>
                </fieldset>
                <p
                    v-else
                    class="border-t border-secondary pt-4 text-sm opacity-60"
                >
                    Selecciona un elemento en el papel o en la lista de capas
                    para editarlo.
                </p>
            </aside>
        </div>
    </PageLayout>
</template>
<style scoped>
.editor-button {
    border: 1px solid #94a3b84d;
    border-radius: 0.65rem;
    padding: 0.45rem 0.65rem;
    font-size: 0.75rem;
    transition:
        background-color 0.15s,
        transform 0.15s;
}
.editor-button:hover:not(:disabled) {
    background: #94a3b81a;
}
.editor-button:disabled {
    opacity: 0.4;
}
.editor-field {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
}
.editor-field input,
.editor-field select,
.editor-field textarea,
.editor-select {
    width: 100%;
    margin-top: 0.3rem;
    border: 1px solid #94a3b866;
    border-radius: 0.6rem;
    background: transparent;
    color: inherit;
    padding: 0.45rem;
    font-size: 0.8rem;
}
.editor-select {
    width: auto;
    margin: 0;
}
@media (prefers-reduced-motion: reduce) {
    .editor-button {
        transition: none;
    }
}
</style>
