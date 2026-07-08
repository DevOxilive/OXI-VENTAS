<script setup>
import { computed, useSlots } from 'vue'
import { cardClasses } from '@/Components/Cards/cardClasses'
import SectionTitle from '@/Components/Cards/SectionTitle.vue'

defineEmits(['submitted'])

const hasActions = computed(() => !!useSlots().actions)
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
            <form @submit.prevent="$emit('submitted')">
                <div :class="cardClasses.sectionPanel">
                    <div :class="cardClasses.sectionBody">
                        <slot name="form" />
                    </div>

                    <div v-if="hasActions" :class="cardClasses.sectionFooter">
                        <slot name="actions" />
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
