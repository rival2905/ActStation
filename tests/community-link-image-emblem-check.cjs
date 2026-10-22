// Run: node tests/community-link-image-emblem-check.cjs
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');

const filePath = path.join(__dirname, '../public/assets/community-links/image-emblem.js');
const source = fs.readFileSync(filePath, 'utf8');

const window = {};
const context = { window, globalThis: window };
vm.createContext(context);
vm.runInContext(source, context);

const CommunityImageEmblem = context.window.CommunityImageEmblem;
assert.ok(CommunityImageEmblem, 'CommunityImageEmblem must be defined on window');
assert.equal(typeof CommunityImageEmblem.trace, 'function', 'trace must be a function');

function signedArea(pts) {
    let sum = 0;
    for (let i = 0, n = pts.length; i < n; i++) {
        const p1 = pts[i];
        const p2 = pts[(i + 1) % n];
        sum += (p1[0] * p2[1] - p2[0] * p1[1]);
    }
    return sum / 2;
}

// 1. Size bounds & input validation
assert.throws(() => CommunityImageEmblem.trace(new Uint8Array(0), 0, 10), /dimensi|grid|tidak valid/i);
assert.throws(() => CommunityImageEmblem.trace(new Uint8Array(129 * 10), 129, 10), /dimensi|grid|maksimal/i);
assert.throws(() => CommunityImageEmblem.trace(new Uint8Array(10 * 129), 10, 129), /dimensi|grid|maksimal/i);
assert.throws(() => CommunityImageEmblem.trace(new Uint8Array(10), 10, 10), /data alpha|tidak valid/i);
assert.throws(() => CommunityImageEmblem.trace(null, 10, 10), /data alpha|tidak valid/i);

// 2. Empty & full opaque rejection
const empty10 = new Uint8Array(100);
assert.throws(() => CommunityImageEmblem.trace(empty10, 10, 10), /kosong|tidak ada siluet/i);

const full10 = new Uint8Array(100).fill(255);
assert.throws(() => CommunityImageEmblem.trace(full10, 10, 10), /terlalu penuh|transparansi/i);

// 3. Padded rectangle on non-square grid (10x8) checking exact UV normalization
// 10 width, 8 height, cols 2..5 (x: 2..6), rows 2..5 (y: 2..6)
const rectAlpha = new Uint8Array(10 * 8);
for (let r = 2; r <= 5; r++) {
    for (let c = 2; c <= 5; c++) {
        rectAlpha[r * 10 + c] = 200;
    }
}
const rectResult = CommunityImageEmblem.trace(rectAlpha, 10, 8);
assert.equal(rectResult.length, 1, 'Single island for rectangle');
assert.equal(rectResult[0].holes.length, 0, 'No holes for solid rectangle');
assert.equal(rectResult[0].outer.length, 4, 'Rectangle simplified to 4 corners');
for (const [x, y] of rectResult[0].outer) {
    assert.ok(x >= -1 && x <= 1, `x (${x}) must be normalized within [-1, 1]`);
    assert.ok(y >= -1 && y <= 1, `y (${y}) must be normalized within [-1, 1]`);
}
// Check exact expected normalized coordinates:
// gx in [2, 6] -> x = (gx / 10) * 2 - 1 => [ -0.6, 0.2 ]
// gy in [2, 6] -> y = 1 - (gy / 8) * 2 => [ 0.5, -0.5 ]
const expectedXs = new Set(rectResult[0].outer.map(p => p[0]));
const expectedYs = new Set(rectResult[0].outer.map(p => p[1]));
assert.ok(expectedXs.has(-0.6) && expectedXs.has(0.2), 'Expected exact x bounds for 10-wide grid');
assert.ok(expectedYs.has(0.5) && expectedYs.has(-0.5), 'Expected exact y bounds for 8-high grid');
// Outer clockwise in y-up: signed area < 0
assert.ok(signedArea(rectResult[0].outer) < 0, 'Outer contour must be clockwise (negative signed area in y-up)');

// 4. Donut hole
// 8x8 grid: 6x6 filled rectangle (cols 1..6, rows 1..6) with 2x2 hole (cols 3..4, rows 3..4)
const donutAlpha = new Uint8Array(64);
for (let r = 1; r <= 6; r++) {
    for (let c = 1; c <= 6; c++) {
        if (r >= 3 && r <= 4 && c >= 3 && c <= 4) continue;
        donutAlpha[r * 8 + c] = 255;
    }
}
const donutResult = CommunityImageEmblem.trace(donutAlpha, 8, 8);
assert.equal(donutResult.length, 1, 'Single island with donut');
assert.equal(donutResult[0].holes.length, 1, 'Exactly one hole in donut');
assert.equal(donutResult[0].outer.length, 4, 'Outer simplified to 4 corners');
assert.equal(donutResult[0].holes[0].length, 4, 'Hole simplified to 4 corners');
assert.ok(signedArea(donutResult[0].outer) < 0, 'Outer contour clockwise');
assert.ok(signedArea(donutResult[0].holes[0]) > 0, 'Hole contour counter-clockwise');

// 5. Islands & diagonal touch (prefer same cell right turn to avoid bowtie)
// 4x4 grid: diagonal pixels at (1,1) and (2,2)
const diagAlpha = new Uint8Array(16);
diagAlpha[1 * 4 + 1] = 255;
diagAlpha[2 * 4 + 2] = 255;
const diagResult = CommunityImageEmblem.trace(diagAlpha, 4, 4);
assert.equal(diagResult.length, 2, 'Diagonal touching pixels must form 2 separate islands (no bowtie)');
assert.equal(diagResult[0].outer.length, 4, 'First island has 4 corners');
assert.equal(diagResult[1].outer.length, 4, 'Second island has 4 corners');
assert.equal(diagResult[0].holes.length, 0);
assert.equal(diagResult[1].holes.length, 0);

// 6. Too complex rejection (> 128 islands)
// 32x32 checkerboard pattern -> 16 * 16 = 256 isolated pixels
const complexAlpha = new Uint8Array(32 * 32);
for (let r = 0; r < 32; r += 2) {
    for (let c = 0; c < 32; c += 2) {
        complexAlpha[r * 32 + c] = 255;
    }
}
assert.throws(() => CommunityImageEmblem.trace(complexAlpha, 32, 32), /kompleks/i);

// Actual shipped Three.js triangulation must preserve hole, not fill its center.
const threeContext = {};
vm.createContext(threeContext);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/assets/community-links/three-r128.min.js'), 'utf8'), threeContext);
const THREE = threeContext.THREE;
const shape = new THREE.Shape(donutResult[0].outer.map(([x, y]) => new THREE.Vector2(x, y)));
shape.holes.push(new THREE.Path(donutResult[0].holes[0].map(([x, y]) => new THREE.Vector2(x, y))));
const geometry = new THREE.ExtrudeGeometry(shape, {depth: 0.35, bevelEnabled: false, steps: 1});
assert.ok(geometry.attributes.position.count > 0);
geometry.computeBoundingBox();
assert.ok(Math.abs(geometry.boundingBox.max.z - 0.35) < 1e-6);
const outerPoints = shape.getPoints();
const holePoints = shape.holes.map(h => h.getPoints());
const faces = THREE.ShapeUtils.triangulateShape(outerPoints, holePoints);
const points = [...outerPoints, ...holePoints[0]];
const area = faces.reduce((sum, [a, b, c]) => sum + Math.abs(signedArea([points[a], points[b], points[c]].map(p => [p.x, p.y]))), 0);
assert.ok(Math.abs(area - 2) < 1e-6, 'donut area 2.25 minus hole 0.25 must remain 2');
geometry.dispose();
console.log('PASS: community-link-image-emblem-check');
