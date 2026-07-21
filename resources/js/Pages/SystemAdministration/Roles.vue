<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, toRef } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import { usePermissionLabels } from '@/Composables/usePermissionLabels'

const props = defineProps({ roles: Array, permissions: Array })
const { permissionLabel } = usePermissionLabels(toRef(props, 'permissions'))
const selectedRole = ref(null)
const selectedPermissions = ref([])
const edit = (role) => { selectedRole.value = role; selectedPermissions.value = role.permissions.map((permission) => permission.id) }
const save = () => router.put(route('system-administration.roles.update', selectedRole.value.id), { permission_ids: selectedPermissions.value }, { preserveScroll: true, onSuccess: () => { selectedRole.value = null } })
</script>

<template>
    <Head title="Roles y permisos" />
    <AdminLayout>
        <PageLayout>
            <GlobalToolbar title="Roles y permisos" subtitle="Las autorizaciones se administran por permisos, no por usuarios específicos." :back-button="true" back-label="Centro de Administración" :show-search="false" :show-records-per-page="false" :show-counter="false" @back="router.get(route('system-administration.index'))" />
            <div class="grid gap-4 lg:grid-cols-[20rem_1fr]">
                <aside class="space-y-2 rounded-2xl border border-secondary bg-background p-3">
                    <AppButton v-for="role in roles" :key="role.id" variant="secondary" block @click="edit(role)">{{ role.name }} ({{ role.permissions.length }})</AppButton>
                </aside>
                <section class="rounded-2xl border border-secondary bg-background p-5">
                    <div v-if="selectedRole">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-text">{{ selectedRole.name }}</h2>
                                <p class="text-sm text-text opacity-70">Selecciona las acciones que este rol puede realizar.</p>
                            </div>
                            <AppButton @click="save">Guardar permisos</AppButton>
                        </div>
                        <div class="mt-5 grid gap-2 md:grid-cols-2">
                            <SelectionCheckboxCard
                                v-for="permission in permissions"
                                :key="permission.id"
                                compact
                                :checked="selectedPermissions.includes(permission.id)"
                                :title="permissionLabel(permission.name)"
                                @toggle="selectedPermissions = selectedPermissions.includes(permission.id)
                                    ? selectedPermissions.filter((id) => id !== permission.id)
                                    : [...selectedPermissions, permission.id]"
                            />
                        </div>
                    </div>
                    <p v-else class="py-10 text-center text-sm text-text opacity-60">Selecciona un rol para administrar sus permisos.</p>
                </section>
            </div>
        </PageLayout>
    </AdminLayout>
</template>
