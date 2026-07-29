<script setup>
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageLayout from '@/Layouts/PageLayout.vue';
import { GlobalToolbar } from '@/Components/Toolbars';
import { t } from '@/i18n/es';

defineOptions({ layout: AdminLayout })

defineProps({
    tokens: Array,
    availablePermissions: Array,
    defaultPermissions: Array,
});
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                icon="api"
                :title="t('api.title')"
                subtitle="Administra los tokens de acceso y sus permisos."
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
            />
        </template>

        <div class="mx-auto w-full max-w-6xl">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <ApiTokenManager
                    :tokens="tokens"
                    :available-permissions="availablePermissions"
                    :default-permissions="defaultPermissions"
                />
            </div>
        </div>
    </PageLayout>
</template>
