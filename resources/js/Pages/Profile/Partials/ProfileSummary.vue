<script setup>
import { computed } from 'vue'
import { t } from '@/i18n/es'

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
})

const employeeName = computed(() => {
    const employee = props.user?.employee
    const fullName = [employee?.first_name, employee?.last_name]
        .filter(Boolean)
        .join(' ')

    return fullName || t('common.unknown')
})

const branches = computed(() => props.user?.branches ?? [])

const initials = computed(() => {
    const source = employeeName.value === t('common.unknown')
        ? props.user?.name
        : employeeName.value

    return String(source || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase() || 'SK'
})
</script>

<template>
    <section class="profile-summary flex h-full min-h-0 w-full flex-col overflow-hidden bg-[#fcfafb]" aria-labelledby="profile-summary-title">
        <div class="profile-hero relative flex min-h-[13.5rem] shrink-0 overflow-hidden bg-gradient-to-br from-primary via-[#c91428] to-[#7f1020] px-6 py-8 text-white sm:px-10 md:min-h-[15rem] md:px-14">
            <div class="absolute -right-14 -top-20 h-64 w-64 rounded-full border-[28px] border-white/10" aria-hidden="true" />
            <div class="absolute -bottom-24 left-[42%] h-56 w-56 rounded-full bg-white/[0.06]" aria-hidden="true" />

            <div class="relative mx-auto flex w-full max-w-6xl items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/70">
                        Super-Kay
                    </p>
                    <h2 id="profile-summary-title" class="profile-title mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ t('profile.information.title') }}
                    </h2>
                    <p class="mt-2 max-w-lg text-sm leading-6 text-white/80">
                        {{ t('profile.information.description') }}
                    </p>
                </div>
                <span class="material-symbols-outlined hidden rounded-2xl bg-white/10 p-3 text-3xl sm:block" aria-hidden="true">badge</span>
            </div>
        </div>

        <div class="profile-content min-h-0 flex-1 bg-[radial-gradient(circle_at_top_right,_rgba(224,0,15,0.05),_transparent_35%)] px-6 py-6 sm:px-10 md:px-14">
            <div class="mx-auto flex h-full w-full max-w-6xl flex-col justify-center">
                <div class="profile-identity grid gap-5 border-b border-slate-200/80 pb-6 text-center sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:text-left">
                <div class="mx-auto sm:mx-0">
                    <div class="profile-avatar grid h-24 w-24 shrink-0 place-items-center rounded-[1.7rem] border-4 border-white bg-gradient-to-br from-slate-900 to-slate-700 text-2xl font-black tracking-wide text-white shadow-lg shadow-slate-900/20">
                        {{ initials }}
                    </div>
                </div>

                <div class="min-w-0 text-slate-900">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-primary">
                        {{ t('profile.employeeName') }}
                    </p>
                    <h3 class="mt-1 truncate text-xl font-extrabold tracking-tight sm:text-2xl">
                        {{ employeeName }}
                    </h3>
                    <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500">
                        <span class="material-symbols-outlined text-base text-primary" aria-hidden="true">verified_user</span>
                        {{ user.role?.name || t('common.unknown') }}
                    </p>
                </div>

                <div class="mx-auto inline-flex w-fit items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 sm:mx-0">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
                    Usuario activo
                </div>
            </div>

                <dl class="profile-details mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="profile-data-card group rounded-2xl border border-slate-200 bg-white/80 p-4 transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:bg-white hover:shadow-lg hover:shadow-slate-200/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-xl text-primary" aria-hidden="true">alternate_email</span>
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ t('profile.systemUser') }}
                            </dt>
                            <dd class="mt-1 truncate text-base font-bold text-slate-900">
                                {{ user.name || t('common.unknown') }}
                            </dd>
                        </div>
                    </div>
                </div>

                    <div class="profile-data-card group rounded-2xl border border-slate-200 bg-white/80 p-4 transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:bg-white hover:shadow-lg hover:shadow-slate-200/50">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-100 text-xl text-amber-700" aria-hidden="true">shield_person</span>
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ t('profile.role') }}
                            </dt>
                            <dd class="mt-1 truncate text-base font-bold text-slate-900">
                                {{ user.role?.name || t('common.unknown') }}
                            </dd>
                        </div>
                    </div>
                </div>

                    <div class="profile-data-card rounded-2xl border border-slate-200 bg-white/80 p-4 sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-sky-100 text-xl text-sky-700" aria-hidden="true">storefront</span>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ t('profile.branch') }}
                            </dt>
                            <dd class="mt-2 flex flex-wrap gap-2">
                                <template v-if="branches.length">
                                    <span v-for="branch in branches" :key="branch.id" class="rounded-lg border border-sky-200 bg-white px-2.5 py-1 text-sm font-bold text-slate-800">
                                        {{ branch.name }}
                                    </span>
                                </template>
                                <span v-else class="text-base font-bold text-slate-900">
                                    {{ t('profile.noBranch') }}
                                </span>
                            </dd>
                        </div>
                    </div>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>

<style scoped>
@media (max-height: 700px) {
    .profile-hero {
        min-height: 8rem;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .profile-title {
        font-size: 1.5rem;
        line-height: 1.9rem;
    }

    .profile-content {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .profile-identity {
        gap: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .profile-avatar {
        width: 3.75rem;
        height: 3.75rem;
        border-radius: 1.1rem;
        font-size: 1.25rem;
    }

    .profile-details {
        margin-top: 0.75rem;
        gap: 0.5rem;
    }

    .profile-data-card {
        padding: 0.75rem;
    }
}
</style>
