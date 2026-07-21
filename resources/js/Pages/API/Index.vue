<script setup>
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageLayout from '@/Layouts/PageLayout.vue';
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
        <div class="mx-auto w-full max-w-6xl">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-slate-800">
                        {{ t('api.title') }}
                    </h1>

                    <p class="mt-1 text-sm text-slate-500">
                        Administra los tokens de acceso y sus permisos.
                    </p>
                </div>

                <ApiTokenManager
                    :tokens="tokens"
                    :available-permissions="availablePermissions"
                    :default-permissions="defaultPermissions"
                />
            </div>
        </div>
    </PageLayout>
</template>
