<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    isFirst: {
        type: Boolean,
        required: false,
        default: false
    }
});
</script>
<template>
    <div>
        <hr v-show="!isFirst" class="mx-2 mt-6 mb-4 border-t-sky-700">
        <h2 class="text-2xl mb-3 poppins-regular text-sky-800">{{ title }}</h2>
    </div>
</template>