import { computed, ref } from "vue";

export function useEditBatchModal(props) {
    const showEditBatchModal = ref(false);
    const selectedBatchId = ref(null);

    function normalizeDateForInput(date) {
        if (!date) return "";

        return String(date).slice(0, 10);
    }

    const liveSelectedBatch = computed(() => {
        if (!selectedBatchId.value) return null;

        const liveBatch = props.product.batches?.find((batch) => {
            return batch.id === selectedBatchId.value;
        });

        if (!liveBatch) return null;

        return {
            ...liveBatch,
            expiration_date: normalizeDateForInput(liveBatch.expiration_date),
            received_at: normalizeDateForInput(liveBatch.received_at),
            original_quantity: Number(liveBatch.quantity || 0),
            quantity: Number(liveBatch.quantity || 0),
        };
    });

    function editBatch(batch) {
        selectedBatchId.value = batch.id;
        showEditBatchModal.value = true;
    }

    function closeEditBatchModal() {
        showEditBatchModal.value = false;
        selectedBatchId.value = null;
    }

    return {
        showEditBatchModal,
        liveSelectedBatch,
        editBatch,
        closeEditBatchModal,
    };
}
