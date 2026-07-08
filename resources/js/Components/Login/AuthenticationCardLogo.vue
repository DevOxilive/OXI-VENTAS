<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <Link :href="'/'" class="rounded-[2.25rem] bg-white/90 p-4 shadow-lg shadow-amber-900/10 ring-1 ring-amber-100 sm:p-5">
        <img
            src="/icons/super-kay-source.png"
            alt="Super Kay Logo"
            class="h-24 w-24 rounded-[1.5rem] object-cover sm:h-28 sm:w-28"
        />
    </Link>
</template>
