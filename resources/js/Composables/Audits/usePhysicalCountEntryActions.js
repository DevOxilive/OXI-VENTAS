import { ref } from 'vue'

export function usePhysicalCountEntryActions() {
    const showModal = ref(false)
    const modalMode = ref('view')
    const selectedEntry = ref(null)

    const openViewModal = (entry) => {
        modalMode.value = 'view'
        selectedEntry.value = entry
        showModal.value = true
    }

    const openEditModal = (entry) => {
        modalMode.value = 'edit'
        selectedEntry.value = entry
        showModal.value = true
    }

    const openDeleteModal = (entry) => {
        modalMode.value = 'delete'
        selectedEntry.value = entry
        showModal.value = true
    }

    const closeModal = () => {
        showModal.value = false
        selectedEntry.value = null
    }

    return {
        showModal,
        modalMode,
        selectedEntry,
        openViewModal,
        openEditModal,
        openDeleteModal,
        closeModal,
    }
}