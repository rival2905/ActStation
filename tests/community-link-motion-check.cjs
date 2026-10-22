// Run: node tests/community-link-motion-check.cjs
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/community-links/perinatologi-care.js'), 'utf8');
for (const mode of ['missing-three', 'failed-webgl']) {
    const make = () => ({hidden: true, style: {}, events: {}, addEventListener(k, fn) { this.events[k] = fn; }, removeAttribute() {}, setAttribute(k, v) { this[k] = v; }});
    const hero = make(), bg = make(), fallback = make(), toggle = make(), label = make();
    fallback.hidden = false;
    const elements = {'hero-3d-canvas': hero, 'bg-3d-canvas': bg, 'hero-fallback': fallback, 'motion-toggle': toggle, 'motion-label': label};
    const document = {body: {dataset: {}, classList: {contains: () => true}}, getElementById: id => elements[id], querySelectorAll: () => [], addEventListener() {}, hidden: false};
    const window = {matchMedia: () => ({matches: false, addEventListener() {}})};
    if (mode === 'failed-webgl') window.THREE = {Clock: class {}, WebGLRenderer: class { constructor() { throw new Error('WebGL unavailable'); } }};
    vm.runInNewContext(source, {document, window, THREE: window.THREE, navigator: {}, cancelAnimationFrame() {}});
    assert.equal(document.body.dataset.motion, 'static', mode);
    assert.equal(hero.hidden, true);
    assert.equal(fallback.hidden, false);
    assert.equal(toggle['aria-pressed'], 'false');
    toggle.events.click();
    assert.equal(document.body.dataset.motion, 'static', 'failed renderer cannot claim active animation');
}
for (const [reduced, saveData, expected] of [[false, false, true], [true, false, false], [false, true, false]]) {
    const initializer = source.match(/let motionEnabled = ([^;]+);/)[1];
    assert.equal(vm.runInNewContext(initializer, {document: {body: {dataset: {}}}, isSaveData: saveData, reducedMotionQuery: {matches: reduced}}), expected);
}
// Configured motion false (normal true) before JS change
{
    const initializer = source.match(/let motionEnabled = ([^;]+);/)[1];
    const docWithMotionFalse = { body: { dataset: { motionDefault: '0' } } };
    const result = vm.runInNewContext(initializer, {
        isSaveData: false,
        reducedMotionQuery: { matches: false },
        document: docWithMotionFalse
    });
    assert.equal(result, false, 'motionDefault "0" must disable initial animation');
}
const materialCode = source.slice(source.indexOf('const customColor ='), source.indexOf('heartMesh = new THREE.Mesh'));
for (const [color, expected] of [['#f43f5e', '#be123c'], ['#F43F5E', '#be123c'], ['#cc3366', '#cc3366']]) {
    let emissive = '#be123c';
    vm.runInNewContext(materialCode, {document: {body: {dataset: {emblemColor: color}}}, heartMat: {color: {set() {}}, emissive: {set(value) { emissive = value; }}}});
    assert.equal(emissive, expected, 'default heart must retain its original emissive shading');
}
console.log('PASS: missing THREE and failed WebGL retain static fallback without throwing or claiming active motion.');
