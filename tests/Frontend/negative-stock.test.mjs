import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import { parse as parseVue } from '@vue/compiler-sfc';
import { parse as parseJavaScript } from '@babel/parser';

// Ejecuta las funciones reales del carrito, aislando únicamente sus dependencias de pantalla.
const source = parseVue(readFileSync(new URL('../../resources/js/Pages/Ventas/Home.vue', import.meta.url), 'utf8')).descriptor.scriptSetup.content;
const names = ['addProduct', 'increaseQuantity', 'decreaseQuantity', 'updateCartQuantity', 'setCartPresentation', 'cartItemAvailableQuantity', 'saleQuantityStep'];
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

test('kilogramos admiten decimales sobre stock negativo y conservan cantidades de venta positivas', () => {
    const context = cartContext();
    context.addProduct({ branch_product_id: 1, stock: -1, inventory_unit: 'kg' });
    context.updateCartQuantity(0, 0.125);
    context.increaseQuantity(0);
    assert.equal(context.cart.value[0].quantity, 0.126);
    context.updateCartQuantity(0, -3);
    assert.equal(context.cart.value[0].quantity, 0.001);
    context.decreaseQuantity(0);
    assert.equal(context.cart.value.length, 0);
});
