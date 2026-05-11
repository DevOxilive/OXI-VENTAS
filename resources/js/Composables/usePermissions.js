import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => {
        return (
            page.props.auth?.user?.permissions?.map(
                (permission) => permission.name,
            ) || []
        );
    });

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
        can,
        canAny,
    };
}
