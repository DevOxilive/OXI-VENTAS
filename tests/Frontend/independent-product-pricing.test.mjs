import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import { parse as parseVue } from '@vue/compiler-sfc';
import { parse as parseJavaScript } from '@babel/parser';
import { reactive, ref, computed, watch, nextTick, effectScope } from 'vue';

// Run the actual form and its watchers; only replace screen/network dependencies.
const source = parseVue(readFileSync(new URL('../../resources/js/Components/Inventory/ProductModal.vue', import.meta.url), 'utf8')).descriptor.scriptSetup.content;
const script = parseJavaScript(source, { sourceType: 'module' }).program.body
    .filter(node => node.type !== 'ImportDeclaration')
    .map(node => source.slice(node.start, node.end)).join('\n');

function openForm(t, product = null) {
    const props = reactive({ mode: product ? 'edit' : 'create', product, branch: { id: 1 }, canManagePricing: true });
    const scope = effectScope();
    t.after(() => scope.stop());
    const context = vm.createContext({
        ref, computed, watch, File: class File {}, URL,
        defineProps: () => props, defineEmits: () => () => {}, onBeforeUnmount() {},
        useForm: values => reactive({ ...values, errors: {}, reset() {}, clearErrors() {} }),
    });
    return scope.run(() => vm.runInContext(`${script}\n({ form, captureFormSnapshot, restoreFormSnapshot, marginBelowMinimum, handlePresentationMargin, handlePresentationSalePrice, displayedMargin });`, context));
}

test('changing box cost or percentage never overwrites a manual piece price', async t => {
    const { form } = openForm(t);
    Object.assign(form, { has_box_presentation: true, pieces_per_box: 16, cost_per_piece: 0, sale_price_per_piece: 18,
        cost_per_box: 180, box_pricing_mode: 'percentage', box_margin_percentage: 30 });
    await nextTick();
    assert.equal(form.sale_price_per_box, '234');
    assert.equal(form.sale_price_per_piece, 18);
    form.cost_per_box = 200;
    await nextTick();
    assert.equal(form.sale_price_per_box, '260');
    form.sale_price_per_piece = 20;
    form.box_margin_percentage = 40;
    await nextTick();
    assert.equal(form.sale_price_per_piece, 20);
    assert.equal(form.sale_price_per_box, '280');
});

test('each percentage controls only its presentation and manual mode freezes the price', async t => {
    const { form } = openForm(t);
    Object.assign(form, { cost_per_piece: 10, piece_pricing_mode: 'percentage', piece_margin_percentage: 80,
        cost_per_box: 180, box_pricing_mode: 'percentage', box_margin_percentage: 30 });
    await nextTick();
    assert.equal(form.sale_price_per_piece, '18');
    assert.equal(form.sale_price_per_box, '234');
    form.piece_pricing_mode = 'manual';
    form.cost_per_piece = 12;
    await nextTick();
    assert.equal(form.sale_price_per_piece, '18');
    assert.equal(form.sale_price_per_box, '234');
});

test('reopening and recovering a rejected form retain both pricing configurations', async t => {
    const modal = openForm(t, { cost_per_piece: 0, sale_price_per_piece: 20, cost_per_box: 180, sale_price_per_box: 234,
        has_box_presentation: true, piece_pricing_mode: 'manual', box_pricing_mode: 'percentage', box_margin_percentage: 30 });
    await nextTick();
    const snapshot = modal.captureFormSnapshot();
    modal.form.sale_price_per_piece = 99;
    modal.form.box_margin_percentage = 60;
    await nextTick();
    modal.restoreFormSnapshot(snapshot);
    await nextTick();
    assert.equal(modal.form.sale_price_per_piece, 20);
    assert.equal(Number(modal.form.sale_price_per_box), 234);
    assert.equal(modal.form.box_pricing_mode, 'percentage');
    assert.equal(modal.marginBelowMinimum.value, false);
});

test('low margin is checked independently, while zero cost has no percentage', async t => {
    const { form, marginBelowMinimum } = openForm(t);
    Object.assign(form, { has_box_presentation: true, cost_per_piece: 0, sale_price_per_piece: 18,
        cost_per_box: 180, sale_price_per_box: 185 });
    await nextTick();
    assert.equal(marginBelowMinimum.value, true);
    form.sale_price_per_box = 234;
    assert.equal(marginBelowMinimum.value, false);
});

test('typing a sale price calculates its percentage and typing a percentage calculates its sale price', async t => {
    const modal = openForm(t);
    Object.assign(modal.form, { cost_per_piece: 2, cost_per_box: 180, has_box_presentation: true });
    modal.handlePresentationSalePrice('piece', '30');
    modal.handlePresentationMargin('box', '30');
    await nextTick();
    assert.equal(modal.displayedMargin('piece'), '1400');
    assert.equal(modal.form.sale_price_per_box, '234');
    modal.handlePresentationMargin('piece', '50');
    await nextTick();
    assert.equal(modal.form.sale_price_per_piece, '3');
    modal.handlePresentationSalePrice('box', '270');
    await nextTick();
    assert.equal(modal.displayedMargin('box'), '50');
    assert.equal(modal.form.sale_price_per_piece, '3');
});

test('zero purchase cost preserves the entered sale price without inventing a percentage', async t => {
    const modal = openForm(t);
    modal.form.cost_per_piece = 2;
    modal.handlePresentationMargin('piece', '50');
    await nextTick();
    modal.form.cost_per_piece = 0;
    modal.handlePresentationSalePrice('piece', '10');
    await nextTick();
    assert.equal(modal.form.sale_price_per_piece, '10');
    assert.equal(modal.displayedMargin('piece'), '');
    assert.equal(modal.form.piece_pricing_mode, 'manual');
});
