import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const livePermissions = ref([]);
const liveRole = ref(null);
const liveUserId = ref(null);
const hasRealtimePermissions = ref(false);
const permissionsInitialized = ref(false);

export function initializePermissions() {
    const page = usePage();

    if (permissionsInitialized.value) {
        return;
    }

    syncPermissionsFromPage(page.props.auth);
    permissionsInitialized.value = true;
}

export function updateLivePermissions({ permissions = [], role = null, userId = null }) {
    liveUserId.value = userId ?? liveUserId.value;
    livePermissions.value = permissions;
    liveRole.value = role;
    hasRealtimePermissions.value = true;
}

function syncPermissionsFromPage(auth = {}) {
    const pageUserId = auth?.user?.id ?? null;

    if (pageUserId !== liveUserId.value) {
        liveUserId.value = pageUserId;
        hasRealtimePermissions.value = false;
    }

    if (hasRealtimePermissions.value) {
        return;
    }

    livePermissions.value = auth?.permissions || [];
    liveRole.value = auth?.user?.role?.name || null;
}

export function usePermissions() {
    const page = usePage();

    initializePermissions();

    watch(
        () => page.props.auth,
        (auth) => syncPermissionsFromPage(auth),
        {
            immediate: true,
            deep: true,
        },
    );

    const permissions = computed(() => livePermissions.value);
    const role = computed(() => liveRole.value);

    const can = (permission) => permissions.value.includes(permission);

    const canAny = (permissionList = []) => {
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
