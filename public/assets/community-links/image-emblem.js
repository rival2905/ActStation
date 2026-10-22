/**
 * Pure bounded silhouette-to-contours algorithm for 3D image emblems.
 * Exports window.CommunityImageEmblem.trace(alpha, width, height)
 */
(function (global) {
    'use strict';

    function signedArea(pts) {
        let sum = 0;
        for (let i = 0, n = pts.length; i < n; i++) {
            const p1 = pts[i];
            const p2 = pts[(i + 1) % n];
            sum += (p1[0] * p2[1] - p2[0] * p1[1]);
        }
        return sum / 2;
    }

    function simplifyCollinear(pts) {
        if (pts.length <= 3) return pts;
        const n = pts.length;
        const res = [];
        for (let i = 0; i < n; i++) {
            const prev = pts[(i - 1 + n) % n];
            const curr = pts[i];
            const next = pts[(i + 1) % n];
            const dx1 = curr[0] - prev[0];
            const dy1 = curr[1] - prev[1];
            const dx2 = next[0] - curr[0];
            const dy2 = next[1] - curr[1];
            if (dx1 * dy2 - dy1 * dx2 === 0) {
                continue;
            }
            res.push(curr);
        }
        if (res.length < pts.length && res.length >= 3) {
            return simplifyCollinear(res);
        }
        return res;
    }

    function pointInPolygon(px, py, polygon) {
        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i][0], yi = polygon[i][1];
            const xj = polygon[j][0], yj = polygon[j][1];
            const intersect = ((yi > py) !== (yj > py)) &&
                (px < (xj - xi) * (py - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    const CommunityImageEmblem = {
        trace(alpha, width, height) {
            if (!Number.isInteger(width) || !Number.isInteger(height) || width <= 0 || width > 128 || height <= 0 || height > 128) {
                throw new Error('Dimensi grid gambar tidak valid (maksimal 128x128 piksel).');
            }
            if (!alpha || typeof alpha.length !== 'number' || alpha.length !== width * height) {
                throw new Error('Data alpha tidak valid.');
            }

            let occupiedCount = 0;
            for (let i = 0; i < alpha.length; i++) {
                if (alpha[i] >= 128) occupiedCount++;
            }

            if (occupiedCount === 0) {
                throw new Error('Gambar kosong atau tidak ada siluet yang terdeteksi.');
            }
            if (occupiedCount === width * height) {
                throw new Error('Gambar terlalu penuh atau tidak memiliki transparansi latar.');
            }

            const isOccupied = (c, r) => {
                if (c < 0 || c >= width || r < 0 || r >= height) return false;
                return alpha[r * width + c] >= 128;
            };

            const edges = [];
            const outMap = new Map();

            function addEdge(x1, y1, x2, y2, dir, emptyCol, emptyRow) {
                const fromKey = (y1 << 8) | x1;
                const toKey = (y2 << 8) | x2;
                const edge = {
                    fromKey,
                    toKey,
                    dir,
                    fromPt: [x1, y1],
                    toPt: [x2, y2],
                    emptyNeighbor: [emptyCol, emptyRow],
                    visited: false
                };
                edges.push(edge);
                let list = outMap.get(fromKey);
                if (!list) {
                    list = [];
                    outMap.set(fromKey, list);
                }
                list.push(edge);
            }

            // Grid edges: 0 = Right (+x), 1 = Down (+y_img), 2 = Left (-x), 3 = Up (-y_img)
            for (let r = 0; r < height; r++) {
                for (let c = 0; c < width; c++) {
                    if (!isOccupied(c, r)) continue;

                    // Top edge
                    if (!isOccupied(c, r - 1)) {
                        addEdge(c, r, c + 1, r, 0, c, r - 1);
                    }
                    // Right edge
                    if (!isOccupied(c + 1, r)) {
                        addEdge(c + 1, r, c + 1, r + 1, 1, c + 1, r);
                    }
                    // Bottom edge
                    if (!isOccupied(c, r + 1)) {
                        addEdge(c + 1, r + 1, c, r + 1, 2, c, r + 1);
                    }
                    // Left edge
                    if (!isOccupied(c - 1, r)) {
                        addEdge(c, r + 1, c, r, 3, c - 1, r);
                    }
                }
            }

            const rawLoops = [];

            for (let i = 0; i < edges.length; i++) {
                const firstEdge = edges[i];
                if (firstEdge.visited) continue;

                const loopPts = [];
                const emptyNeighbors = [];
                let curr = firstEdge;
                const startVertex = firstEdge.fromKey;

                while (curr) {
                    curr.visited = true;
                    loopPts.push(curr.fromPt);
                    if (curr.emptyNeighbor[0] >= 0 && curr.emptyNeighbor[0] < width &&
                        curr.emptyNeighbor[1] >= 0 && curr.emptyNeighbor[1] < height) {
                        emptyNeighbors.push(curr.emptyNeighbor);
                    }

                    const nextVertex = curr.toKey;
                    if (nextVertex === startVertex) {
                        break;
                    }

                    const candidates = (outMap.get(nextVertex) || []).filter(e => !e.visited);
                    if (candidates.length === 0) {
                        break;
                    }

                    if (candidates.length === 1) {
                        curr = candidates[0];
                    } else {
                        // At diagonal/junction: prefer same cell right turn to avoid bowtie
                        const desiredDir = (curr.dir + 1) % 4;
                        const preferred = candidates.find(e => e.dir === desiredDir);
                        curr = preferred || candidates[0];
                    }
                }

                if (loopPts.length >= 4) {
                    rawLoops.push({ pts: loopPts, emptyNeighbors });
                }
            }

            const normPt = (pt) => {
                const nx = (pt[0] / width) * 2 - 1;
                const ny = 1 - (pt[1] / height) * 2;
                const rx = Math.round(nx * 1e6) / 1e6 || 0;
                const ry = Math.round(ny * 1e6) / 1e6 || 0;
                return [rx, ry];
            };

            const outers = [];
            const holes = [];

            for (const item of rawLoops) {
                const simplified = simplifyCollinear(item.pts);
                if (simplified.length < 3) continue;

                const normLoop = simplified.map(normPt);
                const area = signedArea(normLoop);
                if (Math.abs(area) < 1e-9) continue;

                if (area < 0) {
                    // Outer contour (clockwise in y-up)
                    outers.push({
                        outer: normLoop,
                        holes: [],
                        area: Math.abs(area)
                    });
                } else {
                    // Hole contour (counter-clockwise in y-up)
                    let testPoint = null;
                    if (item.emptyNeighbors.length > 0) {
                        const [ec, er] = item.emptyNeighbors[0];
                        testPoint = normPt([ec + 0.5, er + 0.5]);
                    } else {
                        // Midpoint shifted slightly inside hole
                        const p0 = normLoop[0];
                        const p1 = normLoop[1];
                        const mx = (p0[0] + p1[0]) / 2;
                        const my = (p0[1] + p1[1]) / 2;
                        const dx = p1[0] - p0[0];
                        const dy = p1[1] - p0[1];
                        const len = Math.hypot(dx, dy) || 1;
                        // In counter-clockwise, left normal (-dy, dx) points inside hole
                        testPoint = [mx - (dy / len) * 0.001, my + (dx / len) * 0.001];
                    }

                    holes.push({
                        polygon: normLoop,
                        testPoint,
                        area: Math.abs(area)
                    });
                }
            }

            // Associate holes with direct enclosing outer island
            for (const hole of holes) {
                let enclosingOuter = null;
                let minArea = Infinity;

                for (const out of outers) {
                    if (pointInPolygon(hole.testPoint[0], hole.testPoint[1], out.outer)) {
                        if (out.area < minArea) {
                            minArea = out.area;
                            enclosingOuter = out;
                        }
                    }
                }

                if (enclosingOuter) {
                    enclosingOuter.holes.push(hole.polygon);
                } else if (outers.length > 0) {
                    // Fallback to first outer if outside rounding
                    outers[0].holes.push(hole.polygon);
                }
            }

            // Cap limits: islands <= 128, total contour points <= 4096
            let totalPoints = 0;
            const islands = outers.map(o => {
                totalPoints += o.outer.length;
                for (const h of o.holes) {
                    totalPoints += h.length;
                }
                return { outer: o.outer, holes: o.holes };
            });

            if (islands.length > 128 || totalPoints > 4096) {
                throw new Error('Siluet terlalu kompleks (melebihi batas 128 pulau atau 4096 titik kontur).');
            }

            return islands;
        }
    };

    if (typeof window !== 'undefined') window.CommunityImageEmblem = CommunityImageEmblem;
    if (typeof globalThis !== 'undefined') globalThis.CommunityImageEmblem = CommunityImageEmblem;
    if (typeof module !== 'undefined' && module.exports) module.exports = CommunityImageEmblem;
})(typeof window !== 'undefined' ? window : globalThis);
