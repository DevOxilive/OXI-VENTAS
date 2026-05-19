import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";

const livePermissions = ref([]);
const liveRole = ref(null);
const initialized = ref(false);

export function initializePermissions() {
    const page = usePage();

    if (initialized.value) return;

    livePermissions.value =
        page.props.auth?.user?.permissions?.map(
            (permission) => permission.name,
        ) || [];

    liveRole.value = page.props.auth?.user?.role?.name || null;

    initialized.value = true;
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
        return permissions.value.includes(permission);
    };

    const canAny = (permissionList = []) => {
        return permissionList.some((permission) =>
            permissions.value.includes(permission),
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
