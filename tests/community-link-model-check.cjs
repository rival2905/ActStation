// Run: node tests/community-link-model-check.cjs
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/assets/community-links/perinatologi-care.js'), 'utf8');
const imageMethod = source.slice(source.indexOf('    async function loadImageEmblem()'), source.indexOf('    function replaceEmblem('));
async function imageRejection(url, transparent) {
    let fetched = 0, closed = false;
    const rgba = new Uint8ClampedArray(4 * 4 * 4).fill(255);
    if (transparent) rgba[4 * 5 + 3] = 0;
    const context = {URL, location: {origin: 'https://actstation.net', href: 'https://actstation.net/c/test'}, imageEmblemUrl: url,
        window: {CommunityImageEmblem: {trace() { throw Error('Complex silhouette'); }}}, AbortSignal, Uint8Array,
        fetch: async () => { fetched++; return {ok: true, blob: async () => ({size: 10, type: 'image/png'})}; },
        createImageBitmap: async () => ({width: 2, height: 2, close() { closed = true; }}),
        document: {createElement: () => ({getContext: () => ({drawImage() {}, getImageData: () => ({data: rgba})})})}};
    vm.createContext(context);
    vm.runInContext(imageMethod, context);
    await assert.rejects(vm.runInContext('loadImageEmblem()', context));
    return {fetched, closed};
}
const method = source.slice(source.indexOf('    function replaceEmblem('), source.indexOf('    function startLoop()'));
async function run(url, active = true, fail = false) {
    let fetched = 0, parsed = 0, modifier, ready = false;
    class Group { constructor() { this.scale = {setScalar() {}}; } add() {} }
    class LoadingManager { setURLModifier(fn) { modifier = fn; } }
    class GLTFLoader { parse(bytes, path, ok) { parsed++; ok({scene: {position: {sub() {}}}}); } }
    class Box3 { setFromObject() { return this; } getSize() { return {x: 1, y: 1, z: 1}; } getCenter() { return {}; } }
    const context = {URL, location: {href: 'https://actstation.net/c/test', origin: 'https://actstation.net'}, AbortSignal,
        THREE: {Group, LoadingManager, GLTFLoader, Box3, Vector3: class {}},
        fetch: async () => { fetched++; if (fail) throw Error('offline'); return {ok: true, arrayBuffer: async () => new ArrayBuffer(12)}; },
        imageEmblemUrl: null, modelUrl: url, modelLoading: false, modelReady: false, motionEnabled: active, webGlReady: true,
        emblemGroup: {children: [], add() {}}, motionLabel: {}, updateMotionUI() { ready = true; }};
    vm.createContext(context);
    vm.runInContext(method, context);
    await vm.runInContext('loadModel()', context);
    return {fetched, parsed, ready, modifier, context};
}
(async () => {
    assert.equal((await imageRejection('https://evil.test/logo.png', true)).fetched, 0);
    assert.equal((await imageRejection('/logo.png', false)).closed, true);
    assert.equal((await imageRejection('/logo.png', true)).closed, true);
    const valid = await run('/c/test/model/uuid');
    assert.equal(valid.fetched, 1); assert.equal(valid.parsed, 1); assert.equal(valid.ready, true);
    assert.equal(valid.modifier('blob:https://actstation.net/local'), 'blob:https://actstation.net/local');
    for (const resource of ['https://evil.test/a.png', 'data:image/png;base64,x', '/private/image']) assert.throws(() => valid.modifier(resource));
    assert.equal((await run('https://evil.test/model.glb')).fetched, 0);
    assert.equal((await run('/model', false)).fetched, 0, 'motion-off must not download GLB');
    const failed = await run('/model', true, true);
    assert.equal(failed.ready, false); assert.match(failed.context.motionLabel.textContent, /gagal/);
    console.log('PASS: GLB lazy load, same-origin fetch, embedded-only resources, normalized model and safe failure.');
})().catch(error => { console.error(error); process.exitCode = 1; });
