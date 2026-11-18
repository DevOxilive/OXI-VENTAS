<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import ProfileSummary from '@/Pages/Profile/Partials/ProfileSummary.vue'

defineOptions({ layout: AdminLayout })

</script>

<template>
    <PageLayout :padded="false" fill-height>
        <div class="h-full w-full">
            <ProfileSummary :user="$page.props.auth.user" />
        </div>
    </PageLayout>
</template>
