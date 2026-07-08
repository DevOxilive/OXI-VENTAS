<script setup>
import { cardClasses } from '@/Components/Cards/cardClasses'
</script>

<template>
    <article :class="cardClasses.mobileShell">
        <slot />
    </article>
</template>
