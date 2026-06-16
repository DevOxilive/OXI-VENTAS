import { computed } from "vue";

export function useToolbarConfig(props) {
    const visibleFilters = computed(() => {
        if (!props.filters) return [];

        return props.filters.filter((filter) => {
            if (typeof filter.visible === "function") {
                return filter.visible();
            }

            return filter.visible !== false;
        });
    });

    const visibleActions = computed(() => {
        if (!props.actions) return [];

        return props.actions.filter((action) => {
            if (typeof action.hidden === "function") {
                return !action.hidden();
            }

            return action.hidden !== true;
        });
    });

    const hasFilters = computed(() => visibleFilters.value.length > 0);

    const hasActions = computed(() => visibleActions.value.length > 0);

    const hasSearch = computed(() => props.showSearch !== false);

    const hasRecordsPerPage = computed(
        () => props.showRecordsPerPage !== false,
    );

    function getOptionLabel(option, filter) {
        if (typeof option === "string" || typeof option === "number") {
            return option;
        }

        return (
            option[filter.optionLabel || "label"] ?? option.name ?? option.label
        );
    }

    function getOptionValue(option, filter) {
        if (typeof option === "string" || typeof option === "number") {
            return option;
        }

        return (
            option[filter.optionValue || "value"] ?? option.id ?? option.value
        );
    }

    return {
        visibleFilters,
        visibleActions,
        hasFilters,
        hasActions,
        hasSearch,
        hasRecordsPerPage,
        getOptionLabel,
        getOptionValue,
    };
}

export default useToolbarConfig;
