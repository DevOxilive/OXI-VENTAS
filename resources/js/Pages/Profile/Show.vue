<script setup>
import SectionBorder from '@/Components/Layout/SectionBorder.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue'
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue'
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue'
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue'
import { t } from '@/i18n/es'

defineOptions({ layout: AdminLayout })

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                icon="person"
                :title="t('profile.title')"
                :subtitle="t('profile.subtitle')"
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
            />
        </template>

        <div class="mx-auto w-full max-w-6xl space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />

                    <SectionBorder />
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm class="mt-10 sm:mt-0" />

                    <SectionBorder />
                </div>

                <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <TwoFactorAuthenticationForm
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                        class="mt-10 sm:mt-0"
                    />

                    <SectionBorder />
                </div>

                <LogoutOtherBrowserSessionsForm :sessions="sessions" class="mt-10 sm:mt-0" />

                <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                    <SectionBorder />

                    <DeleteUserForm class="mt-10 sm:mt-0" />
                </template>
            </div>
        </div>
    </PageLayout>
</template>
