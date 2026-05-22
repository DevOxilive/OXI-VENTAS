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
    blue: `
        hover:bg-blue-50
        hover:border-blue-200
        hover:shadow-blue-100
    `,

    amber: `
        hover:bg-amber-50
        hover:border-amber-200
        hover:shadow-amber-100
    `,

    red: `
        hover:bg-red-50
        hover:border-red-200
        hover:shadow-red-100
    `,

    green: `
        bg-emerald-50
        border-emerald-200
        text-emerald-600
        shadow-sm
        shadow-emerald-100/50

        hover:bg-emerald-100
        hover:border-emerald-300
        hover:shadow-md
        hover:shadow-emerald-200/60
        hover:-translate-y-[1px]
    `,

    slate: `
        hover:bg-slate-50
        hover:border-slate-300
    `
}

const iconVariants = {
    blue: 'group-hover:text-blue-600',

    amber: 'group-hover:text-amber-600',

    red: 'group-hover:text-red-600',

    green: `
        text-emerald-600
        group-hover:text-emerald-700
    `,

    slate: 'group-hover:text-slate-700'
}
</script>

<template>
    <button :type="type" :title="title" class="
            group
            w-10
            h-10
            flex
            items-center
            justify-center
            rounded-xl
            border
            bg-white
            transition-all
            duration-200
        " :class="buttonVariants[variant] || buttonVariants.blue">
        <span class="
                material-symbols-outlined
                text-[20px]
                text-slate-500
                transition-all
                duration-200
            " :class="iconVariants[variant] || iconVariants.blue">
            {{ icon }}
        </span>
    </button>
</template>