<script setup>
import AnimatedCheckbox from "@/Components/Forms/AnimatedCheckbox.vue";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";

import TemplateWorkspace from "@/Components/Printers/TemplateWorkspace.vue";
import TemplateBlocksEditor from "@/Components/Printers/TemplateBlocksEditor.vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import InputField from "@/Components/Forms/InputField.vue";
import {
    ToastAlert,
    WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import { getPrinterLabelsToolbarConfig } from "@/config/ToolbarConfigs/printerLabelsToolbarConfig";
import {
    buildLabelPngBase64,
    createDefaultLabelTemplate,
    getLabelBlockCatalog,
    normalizeLabelTemplate,
    resolveLabelPrintDimensions,
    saveStoredLabelTemplateSettings,
} from "@/config/labelTemplate";
import {
    findLabelPrinter,
    getQzPrinters,
    getStoredLabelPrinterName,
    printImageLabel,
} from "@/Composables/useQzTray";
import { usePermissions } from "@/Composables/usePermissions";

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    visualLayout: { type: Object, default: null },
    template: {
        type: Object,
        required: true,
    },
    sampleProduct: {
        type: Object,
        required: true,
    },
    products: {
        type: Array,
        default: () => [],
    },
});

const blockCatalog = getLabelBlockCatalog();
const selectedProductId = ref(
    props.products[0]?.id || props.sampleProduct?.id || "",
);
const productSearch = ref("");
const remoteProductIds = ref(null);
let productSearchTimer = null;
let productSearchRequest = 0;
const availablePrinters = ref([]);
const selectedPrinterName = ref(getStoredLabelPrinterName());
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir etiquetas.");
const printing = ref(false);
const printCopies = ref(1);
const { can } = usePermissions();
const canUpdateTemplate = computed(() => can("systems.labels.update"));
const canPrintLabels = computed(() => can("systems.labels.print"));

const form = useForm({
    name: props.template?.name || "Etiqueta de producto",
    is_active: props.template?.is_active ?? true,
    settings: normalizeLabelTemplate(
        props.template?.settings || createDefaultLabelTemplate(),
    ),
    record_version: props.template?.updated_at || "",
});

const editorTab = ref("content");
const visualPreview = ref(null);
const visualPreviewError = ref("");
let visualPreviewGeneration = 0;
const previewImage = computed(() => {
    if (props.visualLayout) return visualPreview.value ? `data:image/png;base64,${visualPreview.value.base64}` : "";
    if (typeof document === "undefined") return "";
    return `data:image/png;base64,${buildLabelPngBase64(previewTemplate.value, selectedProduct.value)}`;
});
watch(
    () => props.template.updated_at,
    (value) => {
        form.record_version = value || "";
    },
);
function applyLabelSize(event) {
    if (!canUpdateTemplate.value) return;
    const [width, height] = event.target.value.split("x").map(Number);
    if (width && height)
        Object.assign(form.settings, {
            label_width_mm: width,
            label_height_mm: height,
        });
}
const previewTemplate = computed(() =>
    normalizeLabelTemplate({ ...form.settings }),
);
const editableProductName = ref("");

const baseSelectedProduct = computed(
    () =>
        props.products.find(
            (product) => String(product.id) === String(selectedProductId.value),
        ) || props.sampleProduct,
);

watch(
    baseSelectedProduct,
    (product) => {
        editableProductName.value = product?.name || "";
    },
    { immediate: true },
);

const selectedProduct = computed(() => ({
    ...baseSelectedProduct.value,
    name:
        editableProductName.value.trim() ||
        baseSelectedProduct.value?.name ||
        "",
}));
watch([() => props.visualLayout, selectedProduct], async ([layout, product]) => {
    const generation = ++visualPreviewGeneration;
    visualPreview.value = null;
    visualPreviewError.value = "";
    if (!layout) return;
    try {
        const {renderVisualPrint} = await import("@/Components/Printers/visualPrintRenderer");
        const output = await renderVisualPrint(layout, product, {dpi: layout.dpi || 300});
        if (generation === visualPreviewGeneration) visualPreview.value = output;
    } catch (error) {
        if (generation === visualPreviewGeneration) visualPreviewError.value = error.message;
    }
}, {deep: true, immediate: true});
const previewDimensions = computed(() => visualPreview.value || props.visualLayout || resolveLabelPrintDimensions(previewTemplate.value));
const toolbarConfig = computed(() =>
    getPrinterLabelsToolbarConfig({
        processing: form.processing,
        printing: printing.value,
        canSave: canUpdateTemplate.value,
        canPrint: canPrintLabels.value,
    }),
);
const productOptions = computed(() =>
    props.products.map((product) => ({
        label: `${product.name} - $${Number(product.price || 0).toFixed(2)}`,
        value: product.id,
        searchText: [product.name, product.barcode, ...(product.barcodes || [])]
            .filter(Boolean)
            .join(" ")
            .toLowerCase(),
    })),
);
const filteredProductOptions = computed(() => {
    const query = productSearch.value.trim().toLowerCase();

    if (!query) {
        return productOptions.value;
    }

    if (Array.isArray(remoteProductIds.value)) {
        const optionsById = new Map(
            productOptions.value.map((option) => [
                String(option.value),
                option,
            ]),
        );
        const matches = remoteProductIds.value
            .map((id) => optionsById.get(String(id)))
            .filter(Boolean);
        const selected = optionsById.get(String(selectedProductId.value));

        if (
            selected &&
            !matches.some(
                (option) => String(option.value) === String(selected.value),
            )
        ) {
            matches.push(selected);
        }

        return matches;
    }

    return productOptions.value.filter(
        (option) =>
            option.searchText.includes(query) ||
            String(option.value) === String(selectedProductId.value),
    );
});

watch(productSearch, (value) => {
    clearTimeout(productSearchTimer);
    const query = value.trim();
    const requestId = ++productSearchRequest;
    remoteProductIds.value = null;

    if (!query) return;

    productSearchTimer = setTimeout(async () => {
        try {
            const { data } = await window.axios.get(
                route("printers.labels.products.search"),
                {
                    params: { search: query },
                },
            );

            if (requestId === productSearchRequest) {
                remoteProductIds.value = Array.isArray(data?.product_ids)
                    ? data.product_ids
                    : [];
            }
        } catch {
            if (requestId === productSearchRequest) {
                remoteProductIds.value = null;
            }
        }
    }, 150);
});

onMounted(() => {
    initializePrinterBridge({ silent: true });
});

onBeforeUnmount(() => {
    clearTimeout(productSearchTimer);
    productSearchRequest += 1;
});

function resetTemplate() {
    if (!canUpdateTemplate.value) return;

    const nextSettings = normalizeLabelTemplate(createDefaultLabelTemplate());

    nextSettings.blocks = nextSettings.blocks.map((block) => {
        if (["product_name", "price"].includes(block.key)) {
            return {
                ...block,
                size_percent: 440,
            };
        }

        return block;
    });

    form.defaults({
        name: "Etiqueta de producto",
        is_active: true,
        settings: nextSettings,
        record_version: props.template?.updated_at || "",
    });

    form.reset();
    saveStoredLabelTemplateSettings(nextSettings);

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

    form.settings = normalizeLabelTemplate({
        ...form.settings,
    });

    form.transform((data) => ({
        ...data,
        _method: "put",
    })).post(route("printers.labels.update", props.template.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            saveStoredLabelTemplateSettings(form.settings);
            ToastAlert({
                title: "Etiqueta actualizada correctamente",
            });
        },
        onError: () => {
            WarningAlert({
                title: "No se pudo guardar",
                message:
                    "Revisa la configuracion de la etiqueta e intenta nuevamente.",
            });
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
}

function handleToolbarAction(actionId) {
    if (actionId === "visual-editor") {
        router.get(route("printers.visual-editor.show", {kind: "label"}));
        return;
    }
    if (actionId === "reset") {
        resetTemplate();
        return;
    }

    if (actionId === "print" && !printing.value) {
        if (!canPrintLabels.value) return;
        printTestLabel();
        return;
    }

    if (actionId === "save" && !form.processing) {
        saveTemplate();
    }
}

async function initializePrinterBridge({ silent = true } = {}) {
    try {
        const printers = await getQzPrinters({
            expectedPrinterNames: [
                selectedPrinterName.value || getStoredLabelPrinterName(),
            ],
        });
        availablePrinters.value = printers;
        printerBridgeReady.value = true;

        let printerName = selectedPrinterName.value;

        if (!printerName || !printers.includes(printerName)) {
            printerName = findLabelPrinter(printers);
        }

        selectedPrinterName.value = printerName || "";
        printerBridgeMessage.value = selectedPrinterName.value
            ? `Impresora lista: ${selectedPrinterName.value}`
            : "QZ Tray conectado. Configura una impresora de etiquetas.";
    } catch (error) {
        printerBridgeReady.value = false;
        availablePrinters.value = [];
        printerBridgeMessage.value =
            "QZ Tray no esta conectado o no respondio.";

        if (!silent) {
            WarningAlert({
                title: "No se pudo conectar QZ Tray",
                message: error?.message || "Abre QZ Tray y vuelve a intentar.",
            });
        }
    }
}

async function printTestLabel() {
    if (!canPrintLabels.value) return;
    printing.value = true;

    try {
        if (!printerBridgeReady.value || !selectedPrinterName.value) {
            await initializePrinterBridge({ silent: false });
        }

        if (!selectedPrinterName.value) {
            throw new Error("No hay una impresora seleccionada.");
        }

        const visualOutput = props.visualLayout
            ? await (await import("@/Components/Printers/visualPrintRenderer")).renderVisualPrint(props.visualLayout, selectedProduct.value, {dpi: props.visualLayout.dpi || 300})
            : null;
        const image = visualOutput?.base64 || previewImage.value.split(",")[1];
        await printImageLabel(
            selectedPrinterName.value,
            image,
            visualOutput || resolveLabelPrintDimensions(previewTemplate.value),
            printCopies.value,
        );

        ToastAlert({
            title: "Etiqueta enviada a la impresora",
        });
    } catch (error) {
        WarningAlert({
            title: "No se pudo imprimir",
            message:
                error?.message ||
                "QZ Tray no pudo enviar la etiqueta a la impresora seleccionada.",
        });
    } finally {
        printing.value = false;
    }
}
</script>

<template>
    <PageLayout>
        <template #toolbar
            ><GlobalToolbar
                v-bind="toolbarConfig"
                @action="handleToolbarAction"
        /></template>
        <p v-if="visualPreviewError" role="alert" class="text-red-600">{{ visualPreviewError }}</p>
        <div v-if="visualLayout" class="rounded-xl border border-secondary bg-background p-4 text-sm text-text">
            Se imprime el diseño del editor visual. Estos ajustes conservan tu plantilla anterior como respaldo.
            <button type="button" class="font-semibold text-primary underline" @click="handleToolbarAction('visual-editor')">Abrir diseño activo</button>
        </div>
        <TemplateWorkspace
            v-model="editorTab"
            title="Una etiqueta lista para el anaquel"
            description="Elige un producto, revisa su etiqueta y envíala. El papel y el diseño se ajustan por separado."
        >
            <template #status
                ><span
                    class="rounded-full border border-secondary px-4 py-2 text-sm font-semibold"
                    >{{ previewDimensions.width }} ×
                    {{ previewDimensions.height }} mm</span
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
            <div v-show="editorTab === 'content'" class="space-y-5">
                <div>
                    <h3 class="font-bold">¿Qué producto vas a etiquetar?</h3>
                    <p class="mt-1 text-sm opacity-60">
                        El precio y el código se toman del producto
                        seleccionado.
                    </p>
                </div>
                <InputField
                    v-model="productSearch"
                    label="Buscar producto"
                    field="label-product-search"
                    validation-field="toolbar_search"
                    type="search"
                    placeholder="Nombre o código de barras"
                    :show-counter="false"
                />
                <label class="print-field"
                    >Producto<select v-model="selectedProductId">
                        <option
                            v-for="option in filteredProductOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select></label
                >
                <InputField
                    v-model="editableProductName"
                    label="Nombre en la etiqueta"
                    field="label_product_name"
                    :show-counter="false"
                />
                <p class="text-xs opacity-60">
                    Este nombre solo cambia la impresión; no modifica el
                    catálogo.
                </p>
                <label class="print-field"
                    >Cantidad de etiquetas<input
                        v-model.number="printCopies"
                        type="number"
                        min="1"
                        max="100"
                /></label>
            </div>
            <fieldset
                v-show="editorTab === 'paper'"
                :disabled="!canUpdateTemplate"
                class="space-y-5 disabled:opacity-60"
            >
                <div>
                    <h3 class="font-bold">El papel que tienes colocado</h3>
                    <p class="mt-1 text-sm opacity-60">
                        Usa el ancho del rollo y la longitud de cada etiqueta.
                        Estas medidas funcionan con cualquier modelo compatible
                        con impresión por driver.
                    </p>
                </div>
                <label class="print-field"
                    >Formato de referencia<select
                        value=""
                        @change="applyLabelSize"
                    >
                        <option value="">
                            Elegir o escribir medidas personalizadas
                        </option>
                        <option value="62x29">
                            Rollo de 62 mm · etiqueta de 29 mm
                        </option>
                        <option value="62x100">62 × 100 mm</option>
                        <option value="50x30">50 × 30 mm</option>
                        <option value="100x150">100 × 150 mm</option>
                    </select></label
                >
                <div class="grid grid-cols-2 gap-4">
                    <label class="print-field"
                        >Ancho (mm)<input
                            v-model.number="form.settings.label_width_mm"
                            type="number"
                            min="20"
                            max="210" /></label
                    ><label class="print-field"
                        >Longitud (mm)<input
                            v-model.number="form.settings.label_height_mm"
                            type="number"
                            min="12"
                            max="300"
                    /></label>
                </div>
                <p class="rounded-xl bg-secondary p-4 text-sm">
                    La impresora debe admitir estas medidas y el driver debe
                    coincidir con el rollo colocado. Cambiar de modelo no cambia
                    automáticamente tu diseño.
                </p>
                <a
                    :href="route('printers.qz-setup.index')"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-primary"
                    ><span class="material-symbols-outlined">print</span
                    >Configurar impresoras</a
                >
            </fieldset>
            <fieldset
                v-show="editorTab === 'design'"
                :disabled="!canUpdateTemplate"
                class="space-y-5 disabled:opacity-60"
            >
                <InputField
                    v-model="form.name"
                    label="Nombre de la plantilla"
                    field="name"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <InputField
                        v-model="form.settings.header_text"
                        label="Marca"
                        field="label_header_text"
                    /><InputField
                        v-model="form.settings.footer_text"
                        label="Mensaje al pie"
                        field="label_footer_text"
                    />
                </div>
                <label class="print-check"
                    ><AnimatedCheckbox
                        v-model="form.settings.show_border"

                    />Mostrar borde de etiqueta</label
                >
                <TemplateBlocksEditor
                    v-model:blocks="form.settings.blocks"
                    :catalog="blockCatalog"
                    :min="50"
                    :max="2000"
                    :disabled="!canUpdateTemplate"
                />
                <details class="rounded-xl border border-secondary p-4">
                    <summary class="cursor-pointer text-sm font-semibold">
                        Código de barras y texto adicional
                    </summary>
                    <div class="mt-4 space-y-4">
                        <label class="print-field"
                            >Alto del código (mm)<input
                                v-model.number="form.settings.barcode_height_mm"
                                type="number"
                                min="6"
                                max="28" /></label
                        ><label class="print-field"
                            >Ancho del código (%)<input
                                v-model.number="
                                    form.settings.barcode_width_percent
                                "
                                type="number"
                                min="45"
                                max="100" /></label
                        ><InputField
                            v-model="form.settings.custom_text"
                            label="Texto libre"
                            field="label_custom_text"
                            :show-counter="false"
                        />
                    </div>
                </details>
            </fieldset>
            <template #preview>
                <section
                    class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-secondary p-5"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-widest opacity-50"
                            >
                                Vista de impresión
                            </p>
                            <h3 class="mt-1 font-bold">
                                {{ selectedProduct.name }}
                            </h3>
                        </div>
                        <span class="material-symbols-outlined text-primary"
                            >barcode</span
                        >
                    </div>
                    <div
                        class="flex min-h-64 items-center justify-center bg-secondary/40 p-6 sm:p-10"
                    >
                        <img
                            v-if="previewImage"
                            :src="previewImage"
                            alt="Etiqueta que se enviará a la impresora"
                            class="h-auto max-w-full bg-white shadow-lg"
                            :style="{
                                width: `${previewDimensions.width * 5}px`,
                            }"
                        />
                    </div>
                    <div class="space-y-4 p-5">
                        <p class="text-xs opacity-60">
                            La imagen del diseño es la misma que se envía. El
                            tamaño físico depende del papel y del driver.
                        </p>
                        <div class="rounded-xl border border-secondary p-3">
                            <p class="text-xs opacity-60">Impresora asignada</p>
                            <p class="mt-1 break-words text-sm font-semibold">
                                {{ selectedPrinterName || "Sin asignar" }}
                            </p>
                            <p class="mt-1 text-xs opacity-60" role="status">
                                {{ printerBridgeMessage }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded-xl border border-secondary px-4 py-3 text-sm font-semibold"
                                @click="
                                    initializePrinterBridge({ silent: false })
                                "
                            >
                                Reconectar</button
                            ><button
                                v-if="canPrintLabels"
                                type="button"
                                :disabled="printing"
                                class="flex-1 rounded-xl bg-primary px-4 py-3 font-semibold text-white disabled:opacity-50"
                                @click="printTestLabel"
                            >
                                {{
                                    printing
                                        ? "Enviando…"
                                        : `Imprimir ${printCopies || 1} etiqueta(s)`
                                }}
                            </button>
                        </div>
                    </div>
                </section>
            </template>
        </TemplateWorkspace>
    </PageLayout>
</template>
