import assert from 'node:assert/strict';
import test from 'node:test';
import { createInventoryOperationKey } from '../../resources/js/utils/inventoryOperationKey.js';

test('retries keep their UUID after errors and changes to the capture', () => {
    const operation = createInventoryOperationKey();
    const capture = { quantity_delta: 5, expected_values: { status: 'ACTIVE' } };
    const firstKey = operation.get();

    assert.match(firstKey, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    assert.equal(operation.get(), firstKey, 'retrying the same capture must reuse its key');

    // Un error de validación permite corregir datos; una respuesta perdida puede haber confirmado.
    capture.quantity_delta = 3;
    capture.expected_values.status = 'INACTIVE';
    assert.equal(operation.get(), firstKey, 'changing capture data must not silently create a new operation');
});

test('an explicit reset starts a fresh intention and its retries share the new key', () => {
    const operation = createInventoryOperationKey();
    const firstKey = operation.get();

    operation.reset();
    const nextKey = operation.get();

    assert.notEqual(nextKey, firstKey);
    assert.equal(operation.get(), nextKey);
});

test('independent forms receive independent keys for identical captures', () => {
    const firstForm = createInventoryOperationKey();
    const secondForm = createInventoryOperationKey();

    assert.notEqual(firstForm.get(), secondForm.get());
    const retainedKey = firstForm.get();
    secondForm.reset();
    assert.equal(firstForm.get(), retainedKey);
});
