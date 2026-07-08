<script setup>
import { cardClasses } from '@/Components/Cards/cardClasses'
import SectionTitle from '@/Components/Cards/SectionTitle.vue'
</script>

<template>
    <div :class="cardClasses.sectionGrid">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>

            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div :class="cardClasses.sectionContent">
            <div :class="cardClasses.sectionPanel">
                <div class="px-5 py-5 md:px-6">
                    <slot name="content" />
                </div>
            </div>
        </div>
    </div>
</template>
