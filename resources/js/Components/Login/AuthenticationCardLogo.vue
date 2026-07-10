<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <Link :href="'/'" class="rounded-[2.25rem] border border-secondary bg-background p-4 shadow-lg sm:p-5">
        <img
            src="/icons/super-kay-source.png"
            alt="Super Kay Logo"
            class="h-24 w-24 rounded-[1.5rem] object-cover sm:h-28 sm:w-28"
        />
    </Link>
</template>
