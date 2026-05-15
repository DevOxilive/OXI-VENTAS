<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

defineProps({
    label: {
        type: String,
        default: 'Excel'
    }
})

defineEmits(['click'])

const page = usePage()

const permissions = computed(() =>
    page.props.auth?.permissions || []
)

const canExport = computed(() =>
    permissions.value.some(permission => {
        const name = typeof permission === 'string'
            ? permission
            : permission.name

        return name === 'exportar.archivos'
    })
)
</script>

<template>
    <button v-if="canExport" @click="$emit('click')"
        class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">
            download
        </span>

        {{ label }}
    </button>
</template>