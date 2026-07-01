import { router } from '@inertiajs/vue3'

export function useGlobalTablePagination(options = {}) {
    function handlePageChange(url) {
        if (!url) return

        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            ...options,
        })
    }

    return {
        handlePageChange,
    }
}
