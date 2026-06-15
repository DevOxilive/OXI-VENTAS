import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";

const livePermissions = ref([]);
const liveRole = ref(null);

export function initializePermissions() {
    const page = usePage();

    livePermissions.value = page.props.auth?.permissions || [];
    liveRole.value = page.props.auth?.user?.role?.name || null;
}

export function updateLivePermissions({ permissions = [], role = null }) {
    livePermissions.value = permissions;
    liveRole.value = role;
}

export function usePermissions() {
    initializePermissions();

    const permissions = computed(() => livePermissions.value);
    const role = computed(() => liveRole.value);

    const can = (permission) => {
        if (role.value === "Administrador") return true;

        return permissions.value.includes(permission);
    };

    const canAny = (permissionList = []) => {
        if (role.value === "Administrador") return true;

        return permissionList.some((permission) =>
            permissions.value.includes(permission)
        );
    };

    return {
        permissions,
        role,
        can,
        canAny,
        updateLivePermissions,
    };
}