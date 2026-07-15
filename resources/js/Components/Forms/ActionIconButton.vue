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
        hover:bg-secondary
        hover:border-primary
        hover:shadow-sm
    `,

    amber: `
        hover:bg-secondary
        hover:border-accent
        hover:shadow-sm
    `,

    red: `
        hover:bg-secondary
        hover:border-primary
        hover:shadow-sm
    `,

    green: `
        bg-secondary
        border-secondary
        text-accent
        shadow-sm

        hover:bg-secondary
        hover:border-accent
        hover:shadow-md
        hover:-translate-y-[1px]
    `,

    slate: `
        hover:bg-secondary
        hover:border-secondary
    `
}

const iconVariants = {
    blue: 'group-hover:text-primary',

    amber: 'group-hover:text-accent',

    red: 'group-hover:text-primary',

    green: `
        text-accent
        group-hover:text-accent
    `,

    slate: 'group-hover:text-text'
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
            border-secondary
            bg-background
            transition-all
            duration-200
        " :class="buttonVariants[variant] || buttonVariants.blue">
        <span class="
                material-symbols-outlined
                text-[20px]
                text-text opacity-70
                transition-all
                duration-200
            " :class="iconVariants[variant] || iconVariants.blue">
            {{ icon }}
        </span>
    </button>
</template>
