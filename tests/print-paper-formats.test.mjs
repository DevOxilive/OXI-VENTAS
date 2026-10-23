import test from 'node:test';
import assert from 'node:assert/strict';
import {paperFormatsFor, applyPaperFormat} from '../resources/js/config/printPaperFormats.js';
import {createVisualLayout} from '../resources/js/config/visualPrintLayout.js';

test('paper catalog separates physical roll from printable reference width', () => {
    const formats=paperFormatsFor('sales');
    assert.deepEqual(formats.map(f=>[f.paperWidth,f.width]),[[80,72],[58,52.5]]);
    assert.equal(paperFormatsFor('label')[0].paperWidth,62);
});
test('changing format keeps elements in bounds without changing original layout', () => {
    const original=createVisualLayout('sales');
    const saved=JSON.stringify(original);
    const next=applyPaperFormat(original,'receipt-58');
    assert.equal(next.width,52.5);
    assert.equal(next.paper_format,'receipt-58');
    assert.equal(next.auto_height,true);
    for(const e of next.elements) assert.ok(e.x>=0 && e.x+e.width<=52.51);
    assert.equal(JSON.stringify(original),saved);
    const custom=applyPaperFormat(next,'custom');
    assert.equal(custom.width,52.5);
    assert.deepEqual(custom.elements,next.elements);
});
test('continuous 62 mm media has variable length and preserves design length', () => {
    const layout=createVisualLayout('label');
    const result=applyPaperFormat(layout,'continuous-62');
    assert.equal(result.width,62);
    assert.equal(result.height,layout.height);
    assert.equal(result.auto_height,true);
});
