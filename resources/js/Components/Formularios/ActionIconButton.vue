<!-- resources/js/Components/ActionIconButton.vue -->

<script setup>
defineProps({
    icon: {
        type: String,
        required: true
    },
    title: {
        type: String,
        default: ''
    },
    variant: {
        type: String,
        default: 'blue'
    },
    type: {
        type: String,
        default: 'button'
    }
})

const buttonVariants = {
    blue: 'hover:bg-blue-50 hover:border-blue-200',
    amber: 'hover:bg-amber-50 hover:border-amber-200',
    red: 'hover:bg-red-50 hover:border-red-200',
    green: 'hover:bg-green-50 hover:border-green-200',
    slate: 'hover:bg-slate-50 hover:border-slate-300'
}

const iconVariants = {
    blue: 'group-hover:text-blue-600',
    amber: 'group-hover:text-amber-600',
    red: 'group-hover:text-red-600',
    green: 'group-hover:text-green-600',
    slate: 'group-hover:text-slate-700'
}
</script>

<template>
    <button :type="type" :title="title"
        class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white transition-all duration-200"
        :class="buttonVariants[variant] || buttonVariants.blue">
        <span class="material-symbols-outlined text-[20px] text-slate-500 transition"
            :class="iconVariants[variant] || iconVariants.blue">
            {{ icon }}
        </span>
    </button>
</template>