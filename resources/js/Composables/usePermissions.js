import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const livePermissions = ref([]);
const liveRole = ref(null);
const permissionsInitialized = ref(false);

export function initializePermissions() {
    const page = usePage();

    if (permissionsInitialized.value) {
        return;
    }

    livePermissions.value = page.props.auth?.permissions || [];
    liveRole.value = page.props.auth?.user?.role?.name || null;
    permissionsInitialized.value = true;
}

export function updateLivePermissions({ permissions = [], role = null }) {
    livePermissions.value = permissions;
    liveRole.value = role;
}

export function usePermissions() {
    const page = usePage();

    initializePermissions();

    watch(
        () => [
            page.props.auth?.permissions || [],
            page.props.auth?.user?.role?.name || null,
        ],
        ([permissions, role]) => {
            livePermissions.value = permissions;
            liveRole.value = role;
        },
        {
            immediate: true,
            deep: true,
        },
    );

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
