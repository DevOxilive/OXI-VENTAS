import test from 'node:test';
import assert from 'node:assert/strict';
import {createVisualLayout, createVisualElement, resolveVisualGeometry, elementText, sampleVisualData, DOCUMENT_KINDS} from '../resources/js/config/visualPrintLayout.js';
import {buildEscPosTicketData, normalizeTicketTemplate} from '../resources/js/config/ticketTemplate.js';

test('each document has an independent editable layout within the paper', () => {
  for (const {key} of DOCUMENT_KINDS) {
    const layout = createVisualLayout(key);
    assert.equal(layout.version, 2);
    assert.equal(new Set(layout.elements.map(e=>e.id)).size,layout.elements.length);
    for (const e of layout.elements) {
      assert.ok(e.x+e.width<=layout.width);
      assert.ok(e.y+e.height<=layout.height);
    }
  }
});
test('long details shift totals and footer preserving their spacing', () => {
  const layout=createVisualLayout('sales');
  const detail=layout.elements.find(e=>e.type==='items');
  const total=layout.elements.find(e=>e.binding==='total');
  const geometry=resolveVisualGeometry(layout,sampleVisualData('sales',40),()=>100);
  const result=geometry.elements.find(e=>e.id===total.id);
  assert.equal(result.y,total.y+100-detail.height);
  assert.ok(geometry.height>layout.height);
  assert.equal(geometry.overflow,false);
  assert.equal(total.y,84);
  const fixed=resolveVisualGeometry({...layout,auto_height:false},sampleVisualData('sales'),()=>100);
  assert.equal(fixed.overflow,true);
});
test('alignment and positions survive independently and preserve money and dynamic content', () => {
  const layout=createVisualLayout('sales');
  layout.elements[0].x=12;
  layout.elements[1].align='right';
  const resolved=resolveVisualGeometry(layout,sampleVisualData('sales'),()=>5);
  assert.equal(resolved.elements[0].x,12);
  assert.equal(resolved.elements[1].align,'right');
  assert.equal(elementText(createVisualElement('field',{binding:'price',text:'Precio: '}),{price:74.5}),'Precio: $74.50');
});
test('published layouts select the visual renderer; legacy jobs remain raw', () => {
  const layout=createVisualLayout('sales');
  const job=sampleVisualData('sales');
  const settings=normalizeTicketTemplate({visual_layout:layout});
  const output=buildEscPosTicketData(settings,job);
  assert.equal(output[0].type,'visual-layout');
  assert.deepEqual(output[0].layout,layout);
  assert.equal(buildEscPosTicketData({},job)[0],'\x1B\x40');
  const override=buildEscPosTicketData(settings,{...job,template_settings:{}});
  assert.equal(override[0],'\x1B\x40');
});
