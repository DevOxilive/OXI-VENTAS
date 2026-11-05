import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import { parse as parseVue } from '@vue/compiler-sfc';
import { parse as parseJavaScript } from '@babel/parser';

// Ejecuta las funciones reales del carrito, aislando únicamente sus dependencias de pantalla.
const source = parseVue(readFileSync(new URL('../../resources/js/Pages/Ventas/Home.vue', import.meta.url), 'utf8')).descriptor.scriptSetup.content;
const names = ['addProduct', 'increaseQuantity', 'decreaseQuantity', 'updateCartQuantity', 'setCartPresentation', 'cartItemAvailableQuantity', 'saleQuantityStep', 'bulkQuantityFromInput', 'bulkQuantityInput'];
const functions = parseJavaScript(source, { sourceType: 'module' }).program.body
    .filter((node) => node.type === 'FunctionDeclaration' && names.includes(node.id.name))
    .map((node) => source.slice(node.start, node.end)).join('\n');

function cartContext() {
    const context = vm.createContext({
        cart: { value: [] }, canCreateSale: { value: true }, search: { value: '' },
        highlightedSuggestionIndex: { value: 0 }, focusSearch() {},
        WarningAlert() { throw new Error('La operación fue bloqueada'); },
        ErrorAlert() { throw new Error('La operación fue bloqueada'); },
    });
    vm.runInContext(functions, context);
    return context;
}

for (const stock of [0, -5, 1]) {
    test(`agregar, escanear y aumentar con existencia ${stock}`, () => {
        const context = cartContext();
        const product = { branch_product_id: 1, name: 'Prueba', stock, price: 10, inventory_unit: 'pza' };
        context.addProduct(product);
        context.addProduct(product);
        context.increaseQuantity(0);
        assert.equal(context.cart.value[0].quantity, 3);
        context.updateCartQuantity(0, 20);
        assert.equal(context.cart.value[0].quantity, 20);
    });
}

test('cambiar a cajas no limita la venta por existencias', () => {
    const context = cartContext();
    context.addProduct({ branch_product_id: 1, stock: -5, has_box_presentation: true, pieces_per_box: 12, inventory_unit: 'pza' });
    context.updateCartQuantity(0, 4);
    context.setCartPresentation(0, 'box');
    assert.equal(context.cart.value[0].quantity, 4);
    context.increaseQuantity(0);
    assert.equal(context.cart.value[0].quantity, 5);
});

test('kilogramos admiten fracciones de gramo y el contador aumenta de 50 gramos', () => {
    const context = cartContext();
    context.addProduct({ branch_product_id: 1, stock: -1, inventory_unit: 'kg' });
    assert.equal(context.cart.value[0].quantity, 0.05);
    context.updateCartQuantity(0, 0.125);
    context.increaseQuantity(0);
    assert.equal(context.cart.value[0].quantity, 0.175);
    context.updateCartQuantity(0, -3);
    assert.equal(context.cart.value[0].quantity, 0.001);
    context.decreaseQuantity(0);
    assert.equal(context.cart.value.length, 0);
});

test('la captura de granel interpreta cualquier peso en gramos, sin casos fijos', () => {
    const context = cartContext();
    context.addProduct({ branch_product_id: 1, stock: 5, inventory_unit: 'kg' });

    context.updateCartQuantity(0, '.90');
    assert.equal(context.cart.value[0].quantity, 0.09);
    assert.equal(context.cart.value[0].quantity_input, '.90');
    context.updateCartQuantity(0, '.30');
    assert.equal(context.cart.value[0].quantity, 0.03);
    context.updateCartQuantity(0, '900');
    assert.equal(context.cart.value[0].quantity, 0.9);
    assert.equal(context.cart.value[0].quantity_input, '900');
    context.updateCartQuantity(0, '.125');
    assert.equal(context.cart.value[0].quantity, 0.125);
    context.updateCartQuantity(0, '233');
    assert.equal(context.cart.value[0].quantity, 0.233);
    context.updateCartQuantity(0, '699');
    assert.equal(context.cart.value[0].quantity, 0.699);
    context.updateCartQuantity(0, '999999');
    assert.equal(context.cart.value[0].quantity, 999.999);
});
