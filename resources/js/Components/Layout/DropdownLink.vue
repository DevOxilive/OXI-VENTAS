<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" :class="['block w-full px-4 py-2 text-start text-sm leading-5 text-text transition duration-150 ease-in-out hover:bg-secondary focus:bg-secondary focus:outline-none', $props.class]">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" :class="['block px-4 py-2 text-sm leading-5 text-text transition duration-150 ease-in-out hover:bg-secondary focus:bg-secondary focus:outline-none', $props.class]">
            <slot />
        </a>

        <Link v-else :href="href" :class="['block px-4 py-2 text-sm leading-5 text-text transition duration-150 ease-in-out hover:bg-secondary focus:bg-secondary focus:outline-none', $props.class]">
            <slot />
        </Link>
    </div>
</template>
