<script setup>
import GlobalTable from "@/Components/Tables/GlobalTable.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";

defineProps({
    title: String,
    subtitle: String,
    search: String,
    searchPlaceholder: String,
    items: {
        type: Array,
        default: () => [],
    },
    tableConfig: {
        type: Object,
        required: true,
    },
    tableActions: {
        type: Array,
        default: () => [],
    },
    toolbarActions: {
        type: Array,
        default: () => [],
    },
    totalRecords: {
        type: Number,
        default: 0,
    },
});

defineEmits(["update:search", "create", "action"]);
</script>

<template>
    <section class="min-w-0 overflow-hidden rounded-3xl border border-secondary bg-background shadow-sm">
        <div class="border-b border-secondary p-3 sm:p-4">
            <GlobalToolbar
                :title="title"
                :subtitle="subtitle"
                :search="search"
                :search-placeholder="searchPlaceholder"
                :actions="toolbarActions"
                :show-records-per-page="false"
                :total-records="totalRecords"
                :filtered-records="items.length"
                @update:search="$emit('update:search', $event)"
                @action="$event === 'create' && $emit('create')"
            />
        </div>

        <div class="p-3 sm:p-4">
            <GlobalTable
                :items="items"
                :columns="tableConfig.columns"
                :actions="tableActions"
                :row-key="tableConfig.rowKey"
                :no-data-message="tableConfig.noDataMessage"
                :mobile-card-header-field="tableConfig.mobileCardHeaderField"
                :show-pagination="false"
                @action="$emit('action', $event)"
            />
        </div>
    </section>
</template>
