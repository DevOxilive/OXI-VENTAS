<script setup>
import { cardClasses } from '@/Components/Cards/cardClasses'
</script>

<template>
    <article :class="cardClasses.desktopShell">
        <slot />
    </article>
</template>
