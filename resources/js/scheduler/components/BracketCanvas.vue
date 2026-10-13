<template>
  <div
    id="bracket-canvas-container"
    ref="containerRef"
    class="bracket-canvas-container card shadow-sm border mb-3 overflow-hidden position-relative bg-light"
    :class="{ 'is-fullscreen': isFullscreen }"
    style="min-height: 580px; height: 680px;"
  >
    <!-- Canvas Floating Toolbar (Hidden on print) -->
    <div class="canvas-toolbar d-print-none position-absolute top-0 end-0 m-3 d-flex align-items-center gap-2 p-1 bg-white rounded shadow-sm border" style="z-index: 20;">
      <!-- Zoom Out -->
      <button
        type="button"
        class="btn btn-falcon-default btn-sm px-2 py-1"
        title="Perkecil (Zoom Out)"
        @click="zoomOut"
      >
        <span class="fas fa-search-minus"></span>
      </button>

      <!-- Zoom Level indicator -->
      <span class="badge bg-light text-dark border fs--2 px-2 py-1" style="min-width: 48px; text-align: center;">
        {{ Math.round(scale * 100) }}%
      </span>

      <!-- Zoom In -->
      <button
        type="button"
        class="btn btn-falcon-default btn-sm px-2 py-1"
        title="Perbesar (Zoom In)"
        @click="zoomIn"
      >
        <span class="fas fa-search-plus"></span>
      </button>

      <!-- Reset Zoom / Pan -->
      <button
        type="button"
        class="btn btn-falcon-default btn-sm px-2 py-1"
        title="Reset Posisi (100%)"
        @click="resetTransform"
      >
        <span class="fas fa-compress-arrows-alt"></span>
      </button>

      <div class="vr my-1"></div>

      <!-- Fullscreen Toggle -->
      <button
        type="button"
        class="btn btn-falcon-default btn-sm px-2 py-1"
        :title="isFullscreen ? 'Keluar Layar Penuh' : 'Layar Penuh (Maximize)'"
        @click="toggleFullscreen"
      >
        <span :class="isFullscreen ? 'fas fa-compress' : 'fas fa-expand'"></span>
      </button>

      <!-- Cetak Bracket Button -->
      <button
        type="button"
        class="btn btn-sm btn-primary px-3 py-1 d-flex align-items-center gap-1"
        title="Cetak Bracket (A4 Landscape)"
        @click="printBracket"
      >
        <span class="fas fa-print"></span>
        <span class="d-none d-sm-inline">Cetak Bracket</span>
      </button>
    </div>

    <!-- Canvas Info Header -->
    <div class="position-absolute top-0 start-0 m-3 d-print-none" style="z-index: 10;">
      <h6 class="mb-0 fw-bold text-900 d-flex align-items-center gap-2">
        <span class="fas fa-sitemap text-primary"></span>
        <span>Bagan Pertandingan (Knockout Bracket)</span>
      </h6>
      <div class="text-muted fs--2">Gunakan mouse drag untuk menggeser & roda mouse / tombol untuk zoom</div>
    </div>

    <!-- Draggable & Zoomable Viewport -->
    <div
      class="canvas-viewport w-100 h-100 position-relative"
      :style="{ cursor: isPanning ? 'grabbing' : 'grab' }"
      @mousedown="startPan"
      @mousemove="onPan"
      @mouseup="endPan"
      @mouseleave="endPan"
      @wheel.prevent="onWheel"
    >
      <!-- Scaled / Translated Board Content -->
      <div
        ref="boardRef"
        class="canvas-board position-absolute p-5 d-flex align-items-center gap-5"
        :style="{
          transform: `translate(${panX}px, ${panY}px) scale(${scale})`,
          transformOrigin: '0 0',
        }"
      >
        <!-- Dynamic SVG Connector Overlay -->
        <svg
          class="connector-svg position-absolute top-0 start-0 w-100 h-100 pointer-events-none"
          style="z-index: 1; pointer-events: none; overflow: visible;"
        >
          <path
            v-for="(path, idx) in connectorPaths"
            :key="idx"
            :d="path.d"
            :stroke="path.highlight ? '#2c7be5' : '#cbd5e1'"
            :stroke-width="path.highlight ? 2.5 : 2"
            fill="none"
            stroke-linecap="round"
          />
        </svg>

        <!-- Round Columns -->
        <div
          v-for="round in roundColumns"
          :key="round.round"
          class="round-column d-flex flex-column align-items-center justify-content-around h-100 position-relative"
          style="z-index: 2; min-width: 280px;"
        >
          <!-- Round Header -->
          <div class="round-header mb-4 text-center">
            <span class="badge bg-200 text-800 fw-bold px-3 py-2 fs--1 rounded-pill shadow-sm border">
              {{ getRoundTitle(round.round, roundColumns.length) }}
            </span>
          </div>

          <!-- Match Nodes in this Round -->
          <div class="d-flex flex-column justify-content-around flex-grow-1 w-100 gap-4">
            <BracketMatchCard
              v-for="node in round.nodes"
              :key="node.id"
              :node="node"
              @open-score="$emit('open-score', $event)"
            />
          </div>
        </div>

        <!-- Third Place Match Column (if exists) -->
        <div
          v-if="thirdPlaceNodes.length > 0"
          class="round-column d-flex flex-column align-items-center justify-content-around h-100 position-relative ms-4"
          style="z-index: 2; min-width: 280px;"
        >
          <div class="round-header mb-4 text-center">
            <span class="badge bg-warning-subtle text-warning-emphasis fw-bold px-3 py-2 fs--1 rounded-pill shadow-sm border border-warning-subtle">
              Perebutan Juara 3
            </span>
          </div>
          <div class="d-flex flex-column justify-content-center flex-grow-1 w-100 gap-4">
            <BracketMatchCard
              v-for="node in thirdPlaceNodes"
              :key="node.id"
              :node="node"
              @open-score="$emit('open-score', $event)"
            />
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="roundColumns.length === 0" class="text-center py-5 w-100 text-muted">
          <span class="fas fa-sitemap fs-4 mb-2 d-block"></span>
          Belum ada bagan pertandingan knockout yang digenerate.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import BracketMatchCard from './BracketMatchCard.vue';

const props = defineProps({
  nodes: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['open-score']);

const containerRef = ref(null);
const boardRef = ref(null);

// Transform / Zoom / Pan state
const scale = ref(0.95);
const panX = ref(40);
const panY = ref(60);
const isPanning = ref(false);
const startMouseX = ref(0);
const startMouseY = ref(0);
const startPanX = ref(0);
const startPanY = ref(0);

const isFullscreen = ref(false);
const connectorPaths = ref([]);

// Organize nodes by round
const roundColumns = computed(() => {
  const knockoutNodes = props.nodes.filter(n => n.phase === 'knockout' || !n.phase);
  if (knockoutNodes.length === 0) return [];

  const grouped = {};
  knockoutNodes.forEach((node) => {
    const r = node.round || 1;
    if (!grouped[r]) grouped[r] = [];
    grouped[r].push(node);
  });

  // Sort rounds ascending
  return Object.keys(grouped)
    .sort((a, b) => Number(a) - Number(b))
    .map((r) => ({
      round: Number(r),
      nodes: grouped[r].sort((a, b) => (a.match_order || 0) - (b.match_order || 0)),
    }));
});

const thirdPlaceNodes = computed(() => {
  return props.nodes.filter(n => n.phase === 'third_place');
});

const getRoundTitle = (round, totalRounds) => {
  const diff = totalRounds - round;
  if (diff === 0) return 'Final';
  if (diff === 1) return 'Semifinal';
  if (diff === 2) return 'Perempat Final (QF)';
  if (diff === 3) return 'Babak 16 Besar';
  return `Babak ${round}`;
};

// Pan & Zoom controls
const zoomIn = () => {
  scale.value = Math.min(2.0, Number((scale.value + 0.15).toFixed(2)));
};

const zoomOut = () => {
  scale.value = Math.max(0.4, Number((scale.value - 0.15).toFixed(2)));
};

const resetTransform = () => {
  scale.value = 0.95;
  panX.value = 40;
  panY.value = 60;
  nextTick(calculateConnectors);
};

const onWheel = (e) => {
  const zoomFactor = 0.08;
  if (e.deltaY < 0) {
    scale.value = Math.min(2.0, Number((scale.value + zoomFactor).toFixed(2)));
  } else {
    scale.value = Math.max(0.4, Number((scale.value - zoomFactor).toFixed(2)));
  }
};

const startPan = (e) => {
  // Only start pan if clicking background / not on a button/input
  if (e.target.closest('button, input, select, a')) return;
  isPanning.value = true;
  startMouseX.value = e.clientX;
  startMouseY.value = e.clientY;
  startPanX.value = panX.value;
  startPanY.value = panY.value;
};

const onPan = (e) => {
  if (!isPanning.value) return;
  const dx = e.clientX - startMouseX.value;
  const dy = e.clientY - startMouseY.value;
  panX.value = startPanX.value + dx;
  panY.value = startPanY.value + dy;
};

const endPan = () => {
  isPanning.value = false;
};

// Native Fullscreen API
const toggleFullscreen = async () => {
  if (!containerRef.value) return;
  try {
    if (!document.fullscreenElement) {
      await containerRef.value.requestFullscreen();
      isFullscreen.value = true;
    } else {
      await document.exitFullscreen();
      isFullscreen.value = false;
    }
  } catch {
    // fallback or ignore permission error
  }
};

const onFullscreenChange = () => {
  isFullscreen.value = !!document.fullscreenElement;
  nextTick(calculateConnectors);
};

// Cetak Bracket action
// ponytail: window.print() + CSS @media print A4 landscape. Upgrade path: barryvdh/laravel-dompdf or headless Chromium server-side export for automated batch printing.
const printBracket = () => {
  window.print();
};

// Compute dynamic SVG bezier connectors between node cards
const calculateConnectors = () => {
  if (!boardRef.value || props.nodes.length === 0) {
    connectorPaths.value = [];
    return;
  }

  const boardRect = boardRef.value.getBoundingClientRect();
  const currentScale = scale.value;
  const paths = [];

  props.nodes.forEach((node) => {
    if (!node.next_node_id) return;

    const fromEl = document.getElementById(`bracket-node-${node.id}`);
    const toEl = document.getElementById(`bracket-node-${node.next_node_id}`);

    if (fromEl && toEl) {
      const fromRect = fromEl.getBoundingClientRect();
      const toRect = toEl.getBoundingClientRect();

      // Coordinates relative to unscaled board origin
      const x1 = (fromRect.right - boardRect.left) / currentScale;
      const y1 = (fromRect.top + fromRect.height / 2 - boardRect.top) / currentScale;

      const x2 = (toRect.left - boardRect.left) / currentScale;
      const y2 = (toRect.top + toRect.height / 2 - boardRect.top) / currentScale;

      const cx1 = x1 + (x2 - x1) * 0.55;
      const cx2 = x2 - (x2 - x1) * 0.45;

      const isCompleted = node.game?.game_status === 'finished';

      paths.push({
        d: `M ${x1} ${y1} C ${cx1} ${y1}, ${cx2} ${y2}, ${x2} ${y2}`,
        highlight: isCompleted,
      });
    }
  });

  connectorPaths.value = paths;
};

watch(
  () => [props.nodes, scale.value],
  () => {
    nextTick(calculateConnectors);
  },
  { deep: true }
);

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullscreenChange);
  window.addEventListener('resize', calculateConnectors);
  nextTick(() => {
    calculateConnectors();
  });
});

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange);
  window.removeEventListener('resize', calculateConnectors);
});
</script>

<style scoped>
.bracket-canvas-container {
  user-select: none;
}
.bracket-canvas-container.is-fullscreen {
  height: 100vh !important;
  border-radius: 0 !important;
  border: none !important;
}
</style>

<style>
/* Print CSS: A4 landscape, only BracketCanvas visible */
@media print {
  @page {
    size: A4 landscape;
    margin: 8mm;
  }

  body * {
    visibility: hidden !important;
  }

  #bracket-canvas-container,
  #bracket-canvas-container * {
    visibility: visible !important;
  }

  #bracket-canvas-container {
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    border: none !important;
    background: white !important;
    box-shadow: none !important;
    overflow: visible !important;
  }

  .canvas-viewport {
    overflow: visible !important;
  }

  .canvas-board {
    transform: none !important;
    padding: 10px !important;
  }

  .d-print-none,
  .canvas-toolbar {
    display: none !important;
  }
}
</style>
