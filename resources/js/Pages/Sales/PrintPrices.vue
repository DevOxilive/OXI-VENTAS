<script setup>
import Labels from '@/Pages/Printers/Labels.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineOptions({ layout: AdminLayout });
defineProps(['template', 'visualLayout', 'products', 'sampleProduct']);
</script>

<template>
    <Labels :template="template" :visual-layout="visualLayout" :products="products" :sample-product="sampleProduct" print-only />
</template>
