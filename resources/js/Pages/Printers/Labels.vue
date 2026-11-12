<script setup>
import AnimatedCheckbox from "@/Components/Forms/AnimatedCheckbox.vue";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";

import TemplateWorkspace from "@/Components/Printers/TemplateWorkspace.vue";
import TemplateBlocksEditor from "@/Components/Printers/TemplateBlocksEditor.vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import {
    ToastAlert,
    WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import { getPrinterLabelsToolbarConfig } from "@/config/ToolbarConfigs/printerLabelsToolbarConfig";
import {
    createDefaultLabelTemplate,
    getLabelBlockCatalog,
    normalizeLabelTemplate,
    saveStoredLabelTemplateSettings,
} from "@/config/labelTemplate";
import { createVisualLayout } from "@/config/visualPrintLayout";
import {
    findLabelPrinter,
    getQzPrinters,
    getStoredLabelPrinterName,
    printVisualLabel,
    subscribeToQzTrayRecovery,
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
    printOnly: { type: Boolean, default: false },
});

const blockCatalog = getLabelBlockCatalog();
const selectedProductId = ref(
    props.products[0]?.id || props.sampleProduct?.id || "",
);
const productSearch = ref("");
const searchInput = ref(null);
const highlightedSuggestionIndex = ref(0);
const remoteProductIds = ref(null);
let productSearchTimer = null;
let productSearchRequest = 0;
const availablePrinters = ref([]);
const selectedPrinterName = ref(getStoredLabelPrinterName());
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir etiquetas.");
let stopPrinterRecovery = null;
const printing = ref(false);
const printCopies = ref(1);
const printQueue = ref([]);

function pricePresentationOptions(product) {
    const options = [{ value: "piece", label: "Pieza", price: Number(product?.sale_price_per_piece ?? product?.price ?? 0) }];

    if (product?.has_package_presentation) {
        options.push({ value: "package", label: "Paquete", price: Number(product.sale_price_per_package ?? 0) });
    }

    if (product?.has_box_presentation) {
        options.push({ value: "box", label: "Caja", price: Number(product.sale_price_per_box ?? 0) });
    }

    return options;
}

function productForPriceLabel(product, presentation = "piece") {
    const option = pricePresentationOptions(product).find((item) => item.value === presentation)
        ?? pricePresentationOptions(product)[0];

    return {
        ...product,
        price: option.price,
        price_presentation: option.value,
    };
}

function updateQueuePresentation(row, presentation) {
    row.presentation = presentation;
    row.sent = false;
}

function addToQueue(product) {
    if (printing.value || !product) return;

    selectedProductId.value = product.id;
    const row = printQueue.value.find((item) => String(item.product.id) === String(product.id));

    if (row) {
        row.copies += 1;
        row.sent = false;
        return;
    }

    printQueue.value.push({
        product: { ...product },
        presentation: pricePresentationOptions(product)[0].value,
        copies: 1,
        sent: false,
    });
}

function focusSearch() {
    nextTick(() => searchInput.value?.focus?.());
}

function selectSuggestion(product) {
    addToQueue(product);
    productSearch.value = '';
    remoteProductIds.value = null;
    highlightedSuggestionIndex.value = 0;
    focusSearch();
}

function clearPrintQueue() {
    printQueue.value = [];
    productSearch.value = '';
    remoteProductIds.value = null;
    highlightedSuggestionIndex.value = 0;
    focusSearch();
}
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
const labelLayout = computed(() =>
    props.visualLayout || createVisualLayout("label"),
);
const previewImage = computed(() =>
    visualPreview.value ? `data:image/png;base64,${visualPreview.value.base64}` : "",
);
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
watch([labelLayout, selectedProduct], async ([layout, product]) => {
    const generation = ++visualPreviewGeneration;
    visualPreview.value = null;
    visualPreviewError.value = "";
    try {
        const {renderVisualPrint} = await import("@/Components/Printers/visualPrintRenderer");
        const output = await renderVisualPrint(layout, product, {dpi: layout.dpi || 300});
        if (generation === visualPreviewGeneration) visualPreview.value = output;
    } catch (error) {
        if (generation === visualPreviewGeneration) visualPreviewError.value = error.message;
    }
}, {deep: true, immediate: true});
const previewDimensions = computed(() => visualPreview.value || labelLayout.value);
const toolbarConfig = computed(() =>
    getPrinterLabelsToolbarConfig({
        processing: form.processing,
        printing: printing.value,
        canSave: !props.printOnly && canUpdateTemplate.value,
        canPrint: !props.printOnly && canPrintLabels.value,
        showVisualEditor: !props.printOnly,
        title: props.printOnly ? "Imprimir precios" : "Etiquetas",
        subtitle: props.printOnly
            ? ""
            : "Productos, papel y diseño en un solo lugar.",
        printLabel: props.printOnly ? "Imprimir precios" : "Imprimir etiquetas",
    }),
);
const productOptions = computed(() =>
    props.products.map((product) => ({
        label: `${product.name} - $${Number(product.sale_price_per_piece ?? product.price ?? 0).toFixed(2)}`,
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

const searchSuggestions = computed(() => {
    const query = productSearch.value.trim().toLowerCase();
    if (!query) return [];

    return filteredProductOptions.value
        .map((option) => props.products.find((product) => String(product.id) === String(option.value)))
        .filter(Boolean)
        .sort((a, b) => {
            const aName = String(a.name || '').toLowerCase();
            const bName = String(b.name || '').toLowerCase();
            const aBarcode = String(a.barcode || '').toLowerCase();
            const bBarcode = String(b.barcode || '').toLowerCase();
            const aStarts = aName.startsWith(query) || aBarcode.startsWith(query);
            const bStarts = bName.startsWith(query) || bBarcode.startsWith(query);
            if (aStarts !== bStarts) return aStarts ? -1 : 1;
            return aName.localeCompare(bName, 'es');
        })
        .slice(0, 6);
});

watch(searchSuggestions, (suggestions) => {
    if (!suggestions.length || highlightedSuggestionIndex.value >= suggestions.length) {
        highlightedSuggestionIndex.value = 0;
    }
});

function moveSuggestion(delta) {
    if (!searchSuggestions.value.length) return;
    const maxIndex = searchSuggestions.value.length - 1;
    const nextIndex = highlightedSuggestionIndex.value + delta;
    highlightedSuggestionIndex.value = nextIndex < 0 ? maxIndex : nextIndex > maxIndex ? 0 : nextIndex;
}

function handleSearchKeydown(event) {
    if (!props.printOnly) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveSuggestion(1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveSuggestion(-1);
        return;
    }

    if (event.key === 'Enter' && searchSuggestions.value.length) {
        event.preventDefault();
        selectSuggestion(searchSuggestions.value[Math.min(highlightedSuggestionIndex.value, searchSuggestions.value.length - 1)]);
    }
}

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
    stopPrinterRecovery = subscribeToQzTrayRecovery(async () => {
        await initializePrinterBridge({ silent: true });
        return !getStoredLabelPrinterName()
            || Boolean(printerBridgeReady.value && selectedPrinterName.value);
    });
});

onBeforeUnmount(() => {
    clearTimeout(productSearchTimer);
    productSearchRequest += 1;
    stopPrinterRecovery?.();
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
    if (!canPrintLabels.value || printing.value) return;
    printing.value = true;

    try {
        if (!printerBridgeReady.value || !selectedPrinterName.value) {
            await initializePrinterBridge({ silent: false });
        }

        if (!selectedPrinterName.value) {
            throw new Error("No hay una impresora seleccionada.");
        }

        if (props.printOnly) {
            const jobs = printQueue.value.filter(row => !row.sent);
            if (!jobs.length) throw new Error('Agrega productos pendientes a la lista.');
            for (const row of jobs) {
                if (!Number.isInteger(Number(row.copies)) || row.copies < 1 || row.copies > 100) throw new Error('La cantidad por producto debe ser un entero entre 1 y 100.');
            }
            for (const row of jobs) {
                await printVisualLabel(
                    selectedPrinterName.value,
                    labelLayout.value,
                    productForPriceLabel(row.product, row.presentation),
                    Number(row.copies),
                );
                row.sent = true;
            }
            printQueue.value = [];
        } else {
        await printVisualLabel(
            selectedPrinterName.value,
            labelLayout.value,
            selectedProduct.value,
            1,
        );
        }

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
        <div v-if="!printOnly" class="rounded-xl border border-secondary bg-background p-4 text-sm text-text">
            Todas las etiquetas se imprimen con el mismo diseño visual. La plantilla anterior ya no se usa al imprimir.
            <button type="button" class="font-semibold text-primary underline" @click="handleToolbarAction('visual-editor')">Abrir diseño activo</button>
        </div>
        <TemplateWorkspace
            :compact="printOnly"
            :single-column="printOnly"
            v-model="editorTab"
            :tabs="printOnly ? [{ value: 'content', label: 'Producto', icon: 'sell' }] : undefined"
            :title="printOnly ? 'Imprime el precio del producto' : 'Una etiqueta lista para el anaquel'"
            :description="printOnly ? 'La impresora, el tamaño y el diseño ya están configurados.' : 'Elige un producto, revisa su etiqueta y envíala. El papel y el diseño se ajustan por separado.'"
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
                <div v-if="!printOnly">
                    <h3 class="font-bold">¿Qué producto vas a etiquetar?</h3>
                    <p class="mt-1 text-sm opacity-60">
                        El precio y el código se toman del producto
                        seleccionado.
                    </p>
                </div>
                <div v-if="printOnly" class="rounded-2xl border border-secondary bg-[var(--surface)] p-4 sm:p-5">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="material-symbols-outlined flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">barcode_scanner</span>
                        <div><h3 class="text-base font-bold">Prepara tus etiquetas</h3><p class="mt-0.5 text-xs text-text/60">Busca un producto o escanea su código para agregarlo a la lista.</p></div>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="min-w-0 flex-1">
                        <div class="pos-search relative">
                            <InputField
                                ref="searchInput"
                                v-model="productSearch"
                                label="Buscar o escanear"
                                field="sales_search"
                                validation-field="toolbar_search"
                                icon="barcode_scanner"
                                placeholder="Código de barras o nombre del producto"
                                autocomplete="off"
                                :show-counter="false"
                                @keydown="handleSearchKeydown"
                            />
                            <div v-if="searchSuggestions.length" class="absolute left-0 right-0 top-[calc(100%+0.25rem)] z-20 overflow-hidden rounded-2xl border border-secondary bg-background shadow-xl">
                                <button
                                    v-for="(product, index) in searchSuggestions"
                                    :key="product.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 border-b border-secondary px-4 py-3 text-left last:border-b-0"
                                    :class="index === highlightedSuggestionIndex ? 'bg-primary text-white' : 'bg-background text-text hover:bg-secondary'"
                                    @click="selectSuggestion(product)"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-semibold">{{ product.name }}</p>
                                        <p class="truncate text-sm" :class="index === highlightedSuggestionIndex ? 'text-white opacity-80' : 'text-text opacity-70'">{{ product.barcode || 'Sin codigo' }}</p>
                                    </div>
                                    <span class="shrink-0 text-base font-bold">${{ Number(product.price || 0).toFixed(2) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" :disabled="printing || (!printQueue.length && !productSearch)" class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl border border-secondary px-4 text-sm font-semibold text-text transition hover:border-primary hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40 sm:mt-6" @click="clearPrintQueue"><span class="material-symbols-outlined text-lg" aria-hidden="true">restart_alt</span>Limpiar</button>
                    </div>
                </div>
                <InputField
                    v-else
                    v-model="productSearch"
                    label="Buscar producto"
                    field="label-product-search"
                    validation-field="toolbar_search"
                    type="search"
                    placeholder="Nombre o código de barras"
                    :show-counter="false"
                    @keydown="handleSearchKeydown"
                />
                <template v-if="printOnly">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3 px-1"><h3 class="text-sm font-bold">Etiquetas por imprimir</h3><span class="rounded-full bg-secondary/60 px-3 py-1 text-xs font-semibold text-text/70">{{ printQueue.length }} {{ printQueue.length === 1 ? 'producto' : 'productos' }}</span></div>
                        <div v-if="!printQueue.length" class="flex flex-col items-center rounded-2xl border border-dashed border-secondary bg-secondary/20 px-5 py-6 text-center">
                            <span class="material-symbols-outlined mb-2 flex h-11 w-11 items-center justify-center rounded-2xl bg-background text-2xl text-primary shadow-sm" aria-hidden="true">sell</span>
                            <p class="text-base font-bold text-text">Tu lista está lista para comenzar</p>
                            <p class="mt-1 max-w-xl text-sm leading-relaxed text-text/70">Agrega el primer producto desde el buscador. Aquí podrás elegir su precio y cuántas etiquetas necesitas.</p>
                        </div>
                        <div v-for="(row, index) in printQueue" :key="row.product.id" class="flex flex-wrap items-center gap-4 rounded-2xl border border-secondary bg-[var(--surface)] p-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-secondary/50 text-xs font-bold text-text/60">{{ index + 1 }}</span>
                            <button type="button" class="min-w-0 flex-1 basis-40 text-left" @click="selectedProductId = row.product.id"><span class="block truncate text-sm font-semibold">{{ row.product.name }}</span><span class="mt-1 block text-xs text-text/50">{{ row.product.barcode || 'Sin código de barras' }}</span><span v-if="row.sent" class="mt-1 inline-block text-xs font-semibold text-primary">Impreso</span></button>
                            <div class="flex items-center gap-2 text-xs font-semibold text-text opacity-80">
                                <span>Precio</span>
                                <SelectField
                                    :model-value="row.presentation"
                                    hide-label
                                    :options="pricePresentationOptions(row.product).map(option => ({ ...option, label: `${option.label} · $${option.price.toFixed(2)}` }))"
                                    :show-placeholder="false"
                                    :disabled="printing"
                                    class="!w-auto !rounded-lg !px-2 !py-1 !text-sm"
                                    @update:model-value="updateQueuePresentation(row, $event)"
                                />
                            </div>
                            <label class="flex items-center gap-2 text-xs font-semibold text-text/80">Copias<input v-model.number="row.copies" :aria-label="`Cantidad para ${row.product.name}`" type="number" min="1" max="100" class="h-10 w-16 rounded-xl border border-secondary bg-background px-2 text-center text-sm focus:border-primary focus:ring-primary" :disabled="printing || row.sent" /></label>
                            <button type="button" :disabled="printing" aria-label="Quitar producto" class="flex h-9 w-9 items-center justify-center rounded-xl text-text/50 transition hover:bg-primary/10 hover:text-primary disabled:opacity-40" @click="printQueue.splice(index, 1)"><span class="material-symbols-outlined text-xl">close</span></button>
                        </div>
                        <div v-if="printQueue.length" class="flex flex-col gap-4 border-t border-secondary pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-text/60">Revisa los precios y las copias antes de imprimir.</p>
                        <button
                            v-if="canPrintLabels"
                            type="button"
                            :disabled="printing || !printQueue.length"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 disabled:cursor-not-allowed disabled:bg-secondary disabled:text-text/40 disabled:shadow-none sm:shrink-0"
                            @click="printTestLabel"
                        >
                            <span class="material-symbols-outlined text-xl" aria-hidden="true">print</span>{{ printing ? 'Imprimiendo…' : 'Imprimir etiquetas' }}
                        </button>
                        </div>
                    </div>
                </template>
                <label v-else class="print-field"
                    >Producto<GlobalSelect v-model="selectedProductId">
                        <option v-for="option in filteredProductOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </GlobalSelect></label>
                <InputField
                    v-if="!printOnly"
                    v-model="editableProductName"
                    label="Nombre en la etiqueta"
                    field="label_product_name"
                    :show-counter="false"
                />
                <p v-if="!printOnly" class="text-xs opacity-60">
                    Este nombre solo cambia la impresión; no modifica el
                    catálogo.
                </p>
                <label v-if="!printOnly" class="print-field"
                    >Cantidad de etiquetas<input
                        v-model.number="printCopies"
                        type="number"
                        min="1"
                        max="100"
                /></label>
            </div>
            <fieldset
                v-if="!printOnly"
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
                    >Formato de referencia<GlobalSelect
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
                    </GlobalSelect></label
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
                v-if="!printOnly"
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
            <template v-if="!printOnly" #preview>
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
                        class="flex min-h-36 items-center justify-center bg-secondary/40 p-4"
                    >
                        <img
                            v-if="previewImage"
                            :src="previewImage"
                            alt="Etiqueta que se enviará a la impresora"
                            class="h-auto max-w-full bg-white shadow-lg"
                            :style="{
                                width: printOnly ? `${previewDimensions.width * 3}px` : `${previewDimensions.width * 5}px`,
                            }"
                        />
                    </div>
                    <div class="space-y-4 p-5">
                        <p v-if="!printOnly" class="text-xs opacity-60">
                            La imagen del diseño es la misma que se envía. El
                            tamaño físico depende del papel y del driver.
                        </p>
                        <div v-if="!printOnly" class="rounded-xl border border-secondary p-3">
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
                                v-if="!printOnly"
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
                                :disabled="printing || (printOnly && !printQueue.length)"
                                class="flex-1 rounded-xl bg-primary px-4 py-3 font-semibold text-white disabled:opacity-50"
                                @click="printTestLabel"
                            >
                                {{
                                    printing
                                        ? "Enviando…"
                                        : printOnly ? 'Imprimir etiquetas' : 'Imprimir etiqueta'
                                }}
                            </button>
                        </div>
                    </div>
                </section>
            </template>
        </TemplateWorkspace>
    </PageLayout>
</template>

<style scoped>
.pos-search :deep(label) { font-size: 1rem; }
.pos-search :deep(input) { min-height: 3.5rem; font-size: 1rem; }
.pos-search :deep(.material-symbols-outlined) { font-size: 1.5rem; }
</style>
