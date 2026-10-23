<script setup>
import AnimatedCheckbox from "@/Components/Forms/AnimatedCheckbox.vue";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";

import TemplateWorkspace from "@/Components/Printers/TemplateWorkspace.vue";
import TemplateBlocksEditor from "@/Components/Printers/TemplateBlocksEditor.vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import InputField from "@/Components/Forms/InputField.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import {
    ToastAlert,
    WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import { getPrinterTicketsToolbarConfig } from "@/config/ToolbarConfigs/printerTicketsToolbarConfig";
import {
    buildTicketPreviewBlocks,
    createDefaultTicketTemplate,
    getTicketBlockCatalog,
    normalizeTicketTemplate,
    reorderTicketBlocks,
    saveStoredTicketTemplateSettings,
} from "@/config/ticketTemplate";
import { usePermissions } from "@/Composables/usePermissions";

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    visualLayoutActive: {type: Boolean, default: false},
    template: {
        type: Object,
        required: true,
    },
    samplePrintJob: {
        type: Object,
        required: true,
    },
    templateContext: {
        type: Object,
        default: () => ({}),
    },
});

const baseBlockCatalog = getTicketBlockCatalog();
const paperWidthOptions = [
    { label: "58 mm / 5.8 cm", value: 32 },
    { label: "80 mm · 42 columnas", value: 42 },
    { label: "80 mm · 48 columnas", value: 48 },
];
const dragIndex = ref(null);
const previewPaper = ref(null);
const activeHorizontalKey = ref(null);
const activePointerBlockKey = ref(null);
const { can } = usePermissions();

const ticketDocuments = computed(() =>
    [
        {
            slug: "sales-ticket",
            label: "Ventas",
            icon: "receipt_long",
            url: route("printers.tickets.index"),
            allowed:
                can("systems.tickets.view") || can("systems.tickets.update"),
        },
        {
            slug: "employee-credit-statement-ticket",
            label: "Estados de cuenta",
            icon: "account_balance_wallet",
            url: route("printers.employee-credit-statement-tickets.index"),
            allowed:
                can("systems.tickets.view") || can("systems.tickets.update"),
        },
        {
            slug: "cash-closure-ticket",
            label: "Cortes de caja",
            icon: "point_of_sale",
            url: route("printers.cash-closure-tickets.index"),
            allowed:
                can("systems.cash-closure-tickets.view") ||
                can("systems.cash-closure-tickets.update"),
        },
        {
            slug: "sale-return-ticket",
            label: "Cancelaciones y devoluciones",
            icon: "assignment_return",
            url: route("printers.document-tickets.index", {
                kind: "sale-return",
            }),
            allowed:
                can("systems.tickets.view") || can("systems.tickets.update"),
        },
        {
            slug: "credit-payment-ticket",
            label: "Abonos",
            icon: "payments",
            url: route("printers.document-tickets.index", {
                kind: "credit-payment",
            }),
            allowed:
                can("systems.tickets.view") || can("systems.tickets.update"),
        },
    ].filter((document) => document.allowed),
);

const form = useForm({
    name: props.template?.name || "Ticket principal",
    is_active: props.template?.is_active ?? true,
    settings: normalizeTicketTemplate(
        props.template?.settings || createDefaultTicketTemplate(),
    ),
    record_version: props.template?.updated_at || "",
});

const editorTab = ref("content");
watch(
    () => props.template.updated_at,
    (value) => {
        form.record_version = value || "";
    },
);
const previewTemplate = computed(() =>
    normalizeTicketTemplate({ ...form.settings }),
);
const fieldLabels = computed(() => ({
    name: "Plantilla",
    header: "Marca",
    subheader: "Titulo",
    cashBox: "Caja #",
    footer: "Pie",
    ...(props.templateContext?.fieldLabels || {}),
}));
const blockCatalog = computed(() =>
    baseBlockCatalog.map((block) => ({
        ...block,
        label: props.templateContext?.blockLabels?.[block.key] || block.label,
    })),
);

const previewBlocks = computed(() =>
    buildTicketPreviewBlocks(previewTemplate.value, props.samplePrintJob),
);
const updateRouteParams = computed(() =>
    props.templateContext?.documentKind
        ? {
              kind: props.templateContext.documentKind,
              ticketTemplate: props.template.id,
          }
        : props.template.id,
);
const updateRouteName = computed(
    () =>
        ({
            "sale-return-ticket": "printers.document-tickets.update",
            "credit-payment-ticket": "printers.document-tickets.update",
            "cash-closure-ticket": "printers.cash-closure-tickets.update",
            "employee-credit-statement-ticket":
                "printers.employee-credit-statement-tickets.update",
        })[props.template?.slug] || "printers.tickets.update",
);
const updatePermission = computed(() =>
    props.template?.slug === "cash-closure-ticket"
        ? "systems.cash-closure-tickets.update"
        : "systems.tickets.update",
);
const canUpdateTemplate = computed(() => can(updatePermission.value));
const toolbarConfig = computed(() =>
    getPrinterTicketsToolbarConfig({
        tabs: ticketDocuments.value.map(({ slug, label, icon }) => ({
            key: slug,
            label,
            icon,
        })),
        activeTab: props.template.slug,
        processing: form.processing,
        canSave: canUpdateTemplate.value,
        title: "Tickets",
        subtitle:
            "Configura la plantilla de cada comprobante. Se aplica automáticamente al imprimir.",
    }),
);
const paperClass = computed(() => {
    const width = Number(previewTemplate.value.paper_width || 32);

    if (width === 32) return "max-w-[17rem]";
    if (width === 42) return "max-w-[20rem]";

    return "max-w-[24rem]";
});

function updateBlock(index, field, value) {
    if (!canUpdateTemplate.value) return;

    form.settings.blocks = form.settings.blocks.map((block, blockIndex) =>
        blockIndex === index ? { ...block, [field]: value } : { ...block },
    );
}

function updateBlockByKey(key, field, value) {
    const index = form.settings.blocks.findIndex((block) => block.key === key);

    if (index >= 0) {
        updateBlock(index, field, value);
    }
}

function resetTemplate() {
    if (!canUpdateTemplate.value) return;

    const nextSettings = normalizeTicketTemplate(createDefaultTicketTemplate());
    const contextSettings = {
        ...nextSettings,
        subheader_text:
            props.templateContext?.defaultSubheader ||
            nextSettings.subheader_text,
        footer_text:
            props.templateContext?.defaultFooter || nextSettings.footer_text,
        items_format:
            props.templateContext?.itemFormat || nextSettings.items_format,
        open_cash_drawer:
            props.template?.slug === "employee-credit-statement-ticket"
                ? false
                : (props.templateContext?.openCashDrawer ??
                  nextSettings.open_cash_drawer),
    };

    form.defaults({
        name: props.templateContext?.defaultName || "Ticket principal",
        is_active: true,
        settings: contextSettings,
        record_version: props.template?.updated_at || "",
    });

    form.reset();
    saveStoredTicketTemplateSettings(contextSettings, props.template.slug);

    ToastAlert({
        title: "Plantilla restablecida",
    });
}

function saveTemplate() {
    if (!canUpdateTemplate.value) {
        WarningAlert({
            title: "Sin permiso para guardar",
            message:
                "Puedes consultar la plantilla, pero no tienes permiso para editarla.",
        });
        return;
    }

    form.settings = normalizeTicketTemplate({
        ...form.settings,
        items_format:
            props.templateContext?.itemFormat || form.settings.items_format,
        open_cash_drawer:
            props.template?.slug === "employee-credit-statement-ticket"
                ? false
                : form.settings.open_cash_drawer,
    });

    form.transform((data) => ({
        ...data,
        _method: "put",
    })).post(route(updateRouteName.value, updateRouteParams.value), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            saveStoredTicketTemplateSettings(
                form.settings,
                props.template.slug,
            );
            ToastAlert({
                title: "Ticket actualizado correctamente",
            });
        },
        onError: () => {
            WarningAlert({
                title: "No se pudo guardar",
                message:
                    "Revisa la configuracion del ticket e intenta nuevamente.",
            });
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
}

function selectDocumentTemplate(slug) {
    const document = ticketDocuments.value.find((item) => item.slug === slug);
    if (!document || slug === props.template.slug) return;
    router.get(document.url, {}, { preserveState: false });
}

function handleToolbarAction(actionId) {
    if (actionId === "visual-editor") {
        const kind = {"sales-ticket":"sales", "employee-credit-statement-ticket":"statement", "cash-closure-ticket":"closure", "sale-return-ticket":"sale-return", "credit-payment-ticket":"credit-payment"}[props.template.slug] || "sales";
        router.get(route("printers.visual-editor.show", {kind}));
        return;
    }
    if (actionId === "reset") {
        resetTemplate();
        return;
    }

    if (actionId === "save" && !form.processing) {
        saveTemplate();
    }
}

function startDrag(index) {
    dragIndex.value = index;
}

function dropBlock(targetIndex) {
    if (!canUpdateTemplate.value) return;
    if (dragIndex.value === null) {
        return;
    }

    form.settings.blocks = reorderTicketBlocks(
        form.settings.blocks,
        dragIndex.value,
        targetIndex,
    );
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
    const centeredText =
        row.type === "text" && row.block_key === "document_title";

    return {
        fontSize: `${Math.max(10, Math.round((row.size_percent || 100) * 0.1))}px`,
        paddingLeft: centeredText
            ? "0%"
            : `${Math.max(0, Math.min(78, row.position_percent || 0)) * 0.65}%`,
        textAlign: centeredText ? "center" : "left",
    };
}

function startHorizontalAdjust(event, blockKey) {
    if (!canUpdateTemplate.value) return;
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
    const percent = clampPercent(
        ((event.clientX - rect.left) / rect.width) * 100,
        0,
        100,
    );

    updateBlockByKey(blockKey, "position_percent", percent);

    const previewItems = [
        ...container.querySelectorAll("[data-preview-block-key]"),
    ];
    const hoverIndex = previewItems.findIndex((item) => {
        const itemRect = item.getBoundingClientRect();
        return (
            event.clientY >= itemRect.top && event.clientY <= itemRect.bottom
        );
    });

    const currentIndex = form.settings.blocks.findIndex(
        (block) => block.key === blockKey,
    );
    const targetBlockKey = previewBlocks.value[hoverIndex]?.key;
    const targetIndex = form.settings.blocks.findIndex(
        (block) => block.key === targetBlockKey,
    );

    if (targetIndex >= 0 && currentIndex >= 0 && targetIndex !== currentIndex) {
        form.settings.blocks = reorderTicketBlocks(
            form.settings.blocks,
            currentIndex,
            targetIndex,
        );
    }
}

onBeforeUnmount(() => {
    stopHorizontalAdjust();
});
</script>

<template>
    <PageLayout>
        <template #toolbar
            ><GlobalToolbar
                v-bind="toolbarConfig"
                @action="handleToolbarAction"
                @update:active-tab="selectDocumentTemplate"
        /></template>
        <div v-if="visualLayoutActive" class="rounded-xl border border-secondary bg-background p-4 text-sm text-text">
            Este documento utiliza el diseño del editor visual. Los ajustes de esta pantalla conservan tu plantilla anterior como respaldo.
            <button type="button" class="font-semibold text-primary underline" @click="handleToolbarAction('visual-editor')">Abrir diseño activo</button>
        </div>
        <TemplateWorkspace
            v-model="editorTab"
            :title="
                props.templateContext?.title ||
                'Tu comprobante, claro y ordenado'
            "
            description="Personaliza el contenido y ajusta el formato al papel de tu impresora. Ventas, cortes y estados de cuenta conservan sus propias plantillas."
        >
            <template #status
                ><span
                    class="rounded-full border border-secondary px-4 py-2 text-sm font-semibold"
                    >{{ previewTemplate.paper_width }} caracteres por
                    línea</span
                ></template
            >
            <div
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="rounded-xl border border-red-400 p-3 text-sm text-red-600"
            >
                <p v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </p>
            </div>
            <fieldset
                :disabled="!canUpdateTemplate"
                class="space-y-5 disabled:opacity-60"
            >
                <div v-show="editorTab === 'content'" class="space-y-5">
                    <div>
                        <h3 class="font-bold">La información de tu negocio</h3>
                        <p class="mt-1 text-sm opacity-60">
                            Los productos, importes y folios se completan con
                            los datos de cada operación.
                        </p>
                    </div>
                    <InputField
                        v-model="form.name"
                        :label="fieldLabels.name"
                        field="name"
                    /><InputField
                        v-model="form.settings.header_text"
                        :label="fieldLabels.header"
                        field="ticket_header_text"
                    /><InputField
                        v-model="form.settings.subheader_text"
                        :label="fieldLabels.subheader"
                        field="ticket_subheader_text"
                    /><InputField
                        v-model="form.settings.cash_box_text"
                        :label="fieldLabels.cashBox"
                        field="ticket_cash_box_text"
                    /><TextareaField
                        v-model="form.settings.footer_text"
                        :label="fieldLabels.footer"
                        field="ticket_footer_text"
                        :rows="3"
                    />
                </div>
                <div v-show="editorTab === 'paper'" class="space-y-5">
                    <div>
                        <h3 class="font-bold">Formato para tu impresora</h3>
                        <p class="mt-1 text-sm opacity-60">
                            Elige una referencia o ajusta las columnas según el
                            ancho imprimible de tu equipo.
                        </p>
                    </div>
                    <label class="print-field"
                        >Formato de referencia<select
                            v-model.number="form.settings.paper_width"
                        >
                            <option
                                v-for="option in paperWidthOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                            <option
                                v-if="
                                    ![32, 42, 48].includes(
                                        form.settings.paper_width,
                                    )
                                "
                                :value="form.settings.paper_width"
                            >
                                Personalizado ·
                                {{ form.settings.paper_width }} columnas
                            </option>
                        </select></label
                    >
                    <div class="grid grid-cols-2 gap-4">
                        <label class="print-field"
                            >Caracteres por línea<input
                                v-model.number="form.settings.paper_width"
                                type="number"
                                min="24"
                                max="64" /></label
                        ><label class="print-field"
                            >Avance al terminar (líneas)<input
                                v-model.number="form.settings.feed_lines"
                                type="number"
                                min="1"
                                max="8"
                        /></label>
                    </div>
                    <label class="print-check"
                        ><AnimatedCheckbox
                            v-model="form.settings.auto_cut"

                        />Cortar papel al terminar</label
                    ><label
                        v-if="
                            props.template.slug !==
                            'employee-credit-statement-ticket'
                        "
                        class="print-check"
                        ><AnimatedCheckbox
                            v-model="form.settings.open_cash_drawer"

                        />Permitir apertura del cajón</label
                    >
                    <p class="rounded-xl bg-secondary p-4 text-sm">
                        Compatible con equipos que aceptan ESC/POS. Activa corte
                        y cajón solo si el equipo los admite. Cambia la
                        impresora asignada desde Configurar impresoras.
                    </p>
                    <a
                        :href="route('printers.qz-setup.index')"
                        class="inline-block text-sm font-semibold text-primary"
                        >Configurar impresoras →</a
                    >
                </div>
                <div v-show="editorTab === 'design'" class="space-y-5">
                    <label class="print-check"
                        ><AnimatedCheckbox
                            v-model="form.settings.show_dividers"

                        />Mostrar líneas divisorias</label
                    ><TemplateBlocksEditor
                        v-model:blocks="form.settings.blocks"
                        :catalog="blockCatalog"
                        :disabled="!canUpdateTemplate"
                        :fixed-alignment-keys="['cash_box', 'folio', 'date']"
                    />
                    <p class="text-xs opacity-60">
                        La impresora utiliza tamaños de letra por niveles. Caja,
                        folio y fecha mantienen su alineación para conservar el
                        encabezado.
                    </p>
                </div>
            </fieldset>
            <template #preview>
                <aside class="sticky top-4 h-fit self-start">
                    <div
                        class="rounded-2xl border border-secondary bg-background p-4 shadow-sm"
                    >
                        <div class="border-b border-secondary pb-4">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-text opacity-50"
                            >
                                Ticket en vivo
                            </p>
                            <h2
                                class="mt-1 text-lg font-black text-text lg:text-xl"
                            >
                                Vista previa
                            </h2>
                            <p
                                class="mt-1 text-xs text-text opacity-70 lg:text-sm"
                            >
                                Referencia del contenido. La tipografía final
                                depende de la impresora ESC/POS.
                            </p>
                        </div>

                        <div class="mt-4 flex justify-center">
                            <div
                                class="w-full rounded-[24px] bg-secondary p-2.5 lg:p-3"
                            >
                                <div
                                    ref="previewPaper"
                                    class="mx-auto w-full border border-gray-200 bg-white px-4 py-5 shadow-sm"
                                    :class="paperClass"
                                >
                                    <div class="space-y-1 text-black">
                                        <article
                                            v-for="(
                                                previewBlock, index
                                            ) in previewBlocks"
                                            :key="previewBlock.key"
                                            :data-preview-block-key="
                                                previewBlock.key
                                            "
                                            class="relative rounded-xl border border-transparent px-2 py-2 transition"
                                            :class="[
                                                activeHorizontalKey ===
                                                previewBlock.key
                                                    ? 'bg-secondary ring-1 ring-primary'
                                                    : 'hover:border-secondary hover:bg-secondary',
                                                dragIndex === index
                                                    ? 'border-primary bg-secondary opacity-70'
                                                    : '',
                                            ]"
                                            @pointerdown.prevent="
                                                startHorizontalAdjust(
                                                    $event,
                                                    previewBlock.key,
                                                )
                                            "
                                        >
                                            <template
                                                v-for="(
                                                    row, rowIndex
                                                ) in previewBlock.rows"
                                                :key="`${previewBlock.key}-${row.type}-${rowIndex}`"
                                            >
                                                <div
                                                    v-if="
                                                        row.type ===
                                                        'brand_header'
                                                    "
                                                    class="grid w-full items-center gap-2 py-1 font-black leading-none"
                                                    :class="'grid-cols-[auto_1fr_auto]'"
                                                    :style="{
                                                        fontSize: `${Math.max(10, Math.round((row.size_percent || 100) * 0.16))}px`,
                                                    }"
                                                >
                                                    <img
                                                        :src="row.src"
                                                        :alt="row.alt || 'Logo'"
                                                        class="h-auto shrink-0 object-contain"
                                                        :style="{
                                                            width: `${Math.round(72 * Math.max(0.65, Math.min(1.35, Number(row.size_percent || 100) / 100)))}px`,
                                                        }"
                                                    />
                                                    <span
                                                        class="whitespace-nowrap text-center"
                                                        >{{ row.title }}</span
                                                    >
                                                    <span
                                                        class="whitespace-nowrap text-right text-[0.82em]"
                                                        >{{
                                                            row.cash_box
                                                        }}</span
                                                    >
                                                </div>

                                                <div
                                                    v-else-if="
                                                        row.type === 'image'
                                                    "
                                                    class="flex justify-center py-1"
                                                >
                                                    <img
                                                        :src="row.src"
                                                        :alt="row.alt || 'Logo'"
                                                        class="h-auto max-w-full object-contain"
                                                        :style="{
                                                            width: `${Math.round(160 * Math.max(0.65, Math.min(1.35, Number(row.size_percent || 100) / 100)))}px`,
                                                        }"
                                                    />
                                                </div>

                                                <p
                                                    v-else-if="
                                                        row.type === 'text'
                                                    "
                                                    class="break-words font-semibold"
                                                    :class="
                                                        row.bold
                                                            ? 'font-black'
                                                            : 'font-semibold'
                                                    "
                                                    :style="textStyle(row)"
                                                >
                                                    {{ row.text }}
                                                </p>

                                                <p
                                                    v-else-if="
                                                        row.type === 'divider'
                                                    "
                                                    class="overflow-hidden text-[10px] tracking-[0.12em] text-text opacity-50"
                                                >
                                                    {{ row.text }}
                                                </p>

                                                <p
                                                    v-else-if="
                                                        row.type === 'pair'
                                                    "
                                                    class="break-words font-medium"
                                                    :style="textStyle(row)"
                                                >
                                                    {{ row.label }}:
                                                    {{ row.value }}
                                                </p>

                                                <div
                                                    v-else-if="
                                                        row.type === 'columns'
                                                    "
                                                    class="flex items-start justify-between gap-3"
                                                    :class="
                                                        row.bold
                                                            ? 'font-black'
                                                            : 'font-medium'
                                                    "
                                                    :style="textStyle(row)"
                                                >
                                                    <span
                                                        class="min-w-0 flex-1 break-words"
                                                        >{{ row.left }}</span
                                                    >
                                                    <span
                                                        class="shrink-0 text-right"
                                                        >{{ row.right }}</span
                                                    >
                                                </div>

                                                <div
                                                    v-else-if="
                                                        row.type ===
                                                        'center_right'
                                                    "
                                                    class="relative min-h-[1.25rem] font-bold"
                                                    :style="{
                                                        minHeight: `${Math.round(
                                                            Math.max(
                                                                Math.max(
                                                                    10,
                                                                    Math.round(
                                                                        (row.center_size_percent ||
                                                                            row.size_percent ||
                                                                            100) *
                                                                            0.1,
                                                                    ),
                                                                ),
                                                                Math.max(
                                                                    10,
                                                                    Math.round(
                                                                        (row.right_size_percent ||
                                                                            row.size_percent ||
                                                                            100) *
                                                                            0.1,
                                                                    ),
                                                                ),
                                                            ) * 1.35,
                                                        )}px`,
                                                    }"
                                                >
                                                    <span
                                                        class="block truncate pr-24 text-center"
                                                        :style="{
                                                            fontSize: `${Math.max(10, Math.round((row.center_size_percent || row.size_percent || 100) * 0.1))}px`,
                                                        }"
                                                        >{{ row.center }}</span
                                                    >
                                                    <span
                                                        class="absolute right-0 top-0 max-w-[44%] truncate whitespace-nowrap text-right"
                                                        :style="{
                                                            fontSize: `${Math.max(10, Math.round((row.right_size_percent || row.size_percent || 100) * 0.1))}px`,
                                                        }"
                                                        >{{ row.right }}</span
                                                    >
                                                </div>
                                            </template>
                                        </article>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </template>
        </TemplateWorkspace>
    </PageLayout>
</template>
