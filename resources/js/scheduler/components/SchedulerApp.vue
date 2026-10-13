<template>
  <div class="scheduler-app">
    <!-- Top Action Bar & Category Filter -->
    <div class="card mb-3 shadow-sm border-0 d-print-none">
      <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
          <!-- Category Filter Tabs -->
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-bold fs--1 text-700 me-1">Kategori:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter Kategori">
              <button
                v-for="cat in state.categories"
                :key="cat.id"
                type="button"
                class="btn"
                :class="state.selectedCategoryId === cat.id ? 'btn-primary' : 'btn-falcon-default'"
                @click="selectCategory(cat.id)"
              >
                {{ cat.name }}
                <span class="badge ms-1" :class="state.selectedCategoryId === cat.id ? 'bg-white text-primary' : 'bg-200 text-dark'">
                  {{ cat.gender || 'mixed' }}
                </span>
              </button>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Setup / Tambah Fase -->
            <button
              type="button"
              class="btn btn-sm btn-primary"
              @click="showConfigModal = true"
              :disabled="state.loading || !state.selectedCategoryId"
            >
              <span class="fas fa-plus-circle me-1"></span>
              Setup Babak
            </button>

            <!-- Hitung Ulang Jadwal (Auto-Schedule) -->
            <button
              type="button"
              class="btn btn-sm btn-outline-primary"
              @click="recalculateSchedule"
              :disabled="state.actionLoading || state.games.length === 0"
              title="Hitung ulang alokasi lapangan dan jam pertandingan otomatis"
            >
              <span class="fas fa-calendar-alt me-1"></span>
              Otomatis Jadwal
            </button>

            <!-- Pengaturan Jadwal / Lapangan -->
            <button
              type="button"
              class="btn btn-sm btn-falcon-default"
              @click="showSettingsModal = true"
              title="Pengaturan jam main, durasi, dan lapangan"
            >
              <span class="fas fa-cog me-1"></span>
              Setting Lapangan
            </button>

            <!-- Export Standings CSV -->
            <a
              v-if="exportCsvUrl"
              :href="exportCsvUrl"
              class="btn btn-sm btn-falcon-success"
              download
              title="Download Standings CSV (UTF-8 BOM ;)"
            >
              <span class="fas fa-file-csv me-1 text-success"></span>
              Export Standings
            </a>

            <!-- Cetak Bracket Print Button -->
            <button
              type="button"
              class="btn btn-sm btn-falcon-default"
              @click="printBracket"
              title="Cetak Bracket (A4 Landscape Print)"
            >
              <span class="fas fa-print me-1"></span>
              Cetak Bracket
            </button>

            <!-- Reset All Tournament -->
            <button
              type="button"
              class="btn btn-sm btn-outline-danger"
              @click="confirmResetAll"
              :disabled="state.actionLoading || state.phases.length === 0"
              title="Hapus dan reset seluruh bagan turnamen kategori ini"
            >
              <span class="fas fa-trash-alt me-1"></span>
              Reset Turnamen
            </button>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="state.successMessage" class="alert alert-success alert-dismissible fade show p-2 mb-2 fs--1 d-flex align-items-center" role="alert">
          <span class="fas fa-check-circle me-2 fs-0"></span>
          <div>{{ state.successMessage }}</div>
          <button type="button" class="btn-close p-2" aria-label="Close" @click="state.successMessage = null"></button>
        </div>

        <div v-if="state.error" class="alert alert-danger alert-dismissible fade show p-2 mb-2 fs--1 d-flex align-items-center" role="alert">
          <span class="fas fa-exclamation-triangle me-2 fs-0"></span>
          <div>
            <strong>{{ state.error }}</strong>
            <ul v-if="Object.keys(state.validationErrors).length" class="mb-0 mt-1 ps-3">
              <li v-for="(errs, field) in state.validationErrors" :key="field">
                {{ Array.isArray(errs) ? errs.join(', ') : errs }}
              </li>
            </ul>
          </div>
          <button type="button" class="btn-close p-2" aria-label="Close" @click="state.error = null"></button>
        </div>

        <!-- Navigation Sub-Tabs -->
        <ul class="nav nav-pills fs--1 d-print-none">
          <li class="nav-item">
            <button
              class="nav-link py-1 px-3"
              :class="{ active: currentTab === 'bracket' }"
              @click="currentTab = 'bracket'"
            >
              <span class="fas fa-sitemap me-1"></span>
              Bagan Knockout (Bracket)
            </button>
          </li>
          <li v-if="poolPhase" class="nav-item">
            <button
              class="nav-link py-1 px-3"
              :class="{ active: currentTab === 'pools' }"
              @click="currentTab = 'pools'"
            >
              <span class="fas fa-layer-group me-1"></span>
              Babak Pool ({{ pools.length }} Pool)
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link py-1 px-3"
              :class="{ active: currentTab === 'schedule' }"
              @click="currentTab = 'schedule'"
            >
              <span class="fas fa-clock me-1"></span>
              Matriks Jadwal & Lapangan ({{ state.games.length }} Game)
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Live Standings Podium Strip -->
    <StandingsStrip
      :standings="state.standings"
      :pools="pools"
      :final-node="finalNode"
      :third-place-node="thirdPlaceNode"
    />

    <!-- Main Views -->
    <div v-if="state.loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Memuat data...</span>
      </div>
      <div class="mt-2 text-muted fs--1">Memuat data turnamen...</div>
    </div>

    <div v-else>
      <!-- View: Knockout Bracket -->
      <div v-show="currentTab === 'bracket'">
        <BracketCanvas
          :nodes="bracketNodes"
          @open-score="openScoreModal"
        />
      </div>

      <!-- View: Pool Stage -->
      <div v-show="currentTab === 'pools'">
        <PoolStage
          v-if="poolPhase"
          :phase="poolPhase"
          :games="state.games"
          :api-base="apiBase"
          @open-score="openScoreModal"
          @swap-seeds="handleSwapSeeds"
          @reset-phase="resetPhase"
        />
        <div v-else class="card p-5 text-center text-muted">
          Belum ada babak pool untuk kategori ini. Klik "Setup Babak" untuk menambahkan.
        </div>
      </div>

      <!-- View: Court Schedule Matrix (Native Drag-Drop + Accessible fallback) -->
      <div v-show="currentTab === 'schedule'" class="court-schedule-view d-print-none">
        <div class="card shadow-sm border mb-3">
          <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-900">
              <span class="fas fa-th me-2 text-primary"></span>
              Matriks Penjadwalan Lapangan & Waktu Pertandingan
            </h6>
            <span class="text-muted fs--2">Drag kartu pertandingan ke slot lapangan di bawah</span>
          </div>

          <div class="card-body p-3">
            <!-- Unscheduled / All Games Drawer -->
            <div class="mb-4">
              <h6 class="fs--1 fw-bold text-700 mb-2">Daftar Pertandingan (Drag & Drop ke Lapangan):</h6>
              <div class="d-flex flex-wrap gap-2 p-2 bg-light rounded border" style="min-height: 80px;">
                <div
                  v-for="game in state.games"
                  :key="game.id"
                  :draggable="true"
                  class="badge bg-white text-dark border p-2 shadow-sm d-flex align-items-center gap-2"
                  style="cursor: grab;"
                  @dragstart="onGameDragStart($event, game)"
                >
                  <span class="fas fa-grip-vertical text-400"></span>
                  <div class="text-start">
                    <div class="fw-bold fs--2 text-primary">{{ game.match_label || `Game #${game.id}` }}</div>
                    <div class="fs--2 text-truncate" style="max-width: 140px;">
                      {{ game.team_a?.team_name || game.team_a_label || 'TBD' }} vs {{ game.team_b?.team_name || game.team_b_label || 'TBD' }}
                    </div>
                  </div>
                  <button
                    type="button"
                    class="btn btn-sm btn-falcon-default py-0 px-1 fs--2 ms-1"
                    title="Atur Manual"
                    @click="openAssignCourtModal(game)"
                  >
                    <span class="fas fa-edit"></span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Court Lanes Grid -->
            <h6 class="fs--1 fw-bold text-700 mb-2">Alokasi per Lapangan:</h6>
            <div class="row g-3">
              <div v-for="court in state.courts" :key="court.id" class="col-12 col-md-6 col-xl-4">
                <div
                  class="card h-100 border shadow-none bg-light"
                  @dragover.prevent
                  @drop="onCourtDrop($event, court)"
                >
                  <div class="card-header bg-200 py-2 px-3 fw-bold fs--1 text-800 d-flex justify-content-between align-items-center">
                    <span>
                      <span class="fas fa-volleyball-ball text-primary me-1"></span>
                      {{ court.name }}
                    </span>
                    <span class="badge bg-white text-dark border fs--2">{{ getCourtGames(court.id).length }} Game</span>
                  </div>

                  <div class="card-body p-2 d-flex flex-column gap-2" style="min-height: 140px;">
                    <div
                      v-for="cGame in getCourtGames(court.id)"
                      :key="cGame.id"
                      class="card bg-white p-2 border shadow-sm"
                    >
                      <div class="d-flex justify-content-between align-items-center fs--2 mb-1">
                        <span class="fw-bold text-primary">{{ cGame.match_label || `Game #${cGame.id}` }}</span>
                        <span v-if="cGame.game_time" class="badge bg-light text-dark border">
                          <i class="fas fa-clock me-1"></i>{{ formatTime(cGame.game_time) }}
                        </span>
                      </div>
                      <div class="fs--1 text-truncate">
                        {{ cGame.team_a?.team_name || cGame.team_a_label || 'TBD' }} vs {{ cGame.team_b?.team_name || cGame.team_b_label || 'TBD' }}
                      </div>
                    </div>

                    <div v-if="getCourtGames(court.id).length === 0" class="text-center py-4 text-muted fs--2">
                      Drop game di sini untuk menempatkan ke {{ court.name }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Score Input -->
    <ScoreInput
      :show="showScoreModal"
      :game="selectedGameForScore"
      :is-knockout="isKnockoutGame(selectedGameForScore)"
      :loading="state.actionLoading"
      @close="showScoreModal = false"
      @submit="submitScore"
    />

    <!-- Modal: Setup / Config Phase -->
    <ConfigPanel
      :show="showConfigModal"
      :loading="state.actionLoading"
      :total-participants="state.participants.length"
      @close="showConfigModal = false"
      @submit="handleConfigSubmit"
    />

    <!-- Modal: Manual Court / Time Assignment -->
    <div v-if="showAssignModal" class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1050;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light py-2">
            <h5 class="modal-title fs-0 fw-bold">Jadwalkan Pertandingan</h5>
            <button type="button" class="btn-close" @click="showAssignModal = false"></button>
          </div>
          <div class="modal-body p-3">
            <div class="mb-3">
              <label class="form-label fs--1 fw-bold">Pilih Lapangan</label>
              <select v-model="assignCourtId" class="form-select form-select-sm">
                <option :value="null">-- Belum Ditentukan --</option>
                <option v-for="c in state.courts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fs--1 fw-bold">Waktu Pertandingan</label>
              <input v-model="assignGameTime" type="datetime-local" class="form-control form-control-sm" />
            </div>
          </div>
          <div class="modal-footer py-2 bg-light">
            <button type="button" class="btn btn-sm btn-falcon-default" @click="showAssignModal = false">Batal</button>
            <button type="button" class="btn btn-sm btn-primary" @click="saveManualSchedule">Simpan Jadwal</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Settings (Courts & Times) -->
    <div v-if="showSettingsModal" class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1050;">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light py-2">
            <h5 class="modal-title fs-0 fw-bold">Pengaturan Jadwal & Lapangan Turnamen</h5>
            <button type="button" class="btn-close" @click="showSettingsModal = false"></button>
          </div>
          <div class="modal-body p-4">
            <!-- Sync Courts -->
            <h6 class="fw-bold fs--1 text-700 mb-2">Sinkronisasi Lapangan Aktif</h6>
            <div class="row g-2 mb-4">
              <div v-for="ac in (state.allCourts.length ? state.allCourts : state.courts)" :key="ac.id" class="col-6 col-md-4">
                <div class="form-check">
                  <input
                    :id="'court-check-' + ac.id"
                    v-model="selectedCourtIds"
                    :value="ac.id"
                    type="checkbox"
                    class="form-check-input"
                  />
                  <label :for="'court-check-' + ac.id" class="form-check-label fs--1">{{ ac.name }}</label>
                </div>
              </div>
            </div>

            <!-- Time Settings -->
            <h6 class="fw-bold fs--1 text-700 mb-2">Jam Operasional Pertandingan Harian</h6>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="form-label fs--2 fw-bold">Jam Mulai</label>
                <input v-model="settingsForm.start_time" type="time" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fs--2 fw-bold">Jam Berakhir Harian</label>
                <input v-model="settingsForm.end_time_daily" type="time" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fs--2 fw-bold">Jeda Antar Game (Menit)</label>
                <input v-model.number="settingsForm.break_between_games" type="number" min="0" class="form-control form-control-sm" />
              </div>
            </div>

            <!-- Breaks List & Add Break -->
            <h6 class="fw-bold fs--1 text-700 mb-2">Waktu Istirahat (Break / Sholat)</h6>
            <div class="table-responsive mb-3">
              <table class="table table-sm table-bordered fs--1 mb-2">
                <thead>
                  <tr class="bg-200">
                    <th>Label</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="b in state.breaks" :key="b.id">
                    <td>{{ b.label || 'Istirahat' }}</td>
                    <td>{{ b.date }}</td>
                    <td>{{ b.start_time }} - {{ b.end_time }}</td>
                    <td>
                      <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="destroyBreak(b.id)">
                        <span class="fas fa-trash"></span>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!state.breaks.length">
                    <td colspan="4" class="text-center text-muted">Belum ada waktu istirahat tambahan.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex gap-2 align-items-end p-2 bg-light rounded border">
              <input v-model="newBreak.label" type="text" class="form-control form-control-sm" placeholder="Label (Mis: Isoma)" />
              <input v-model="newBreak.date" type="date" class="form-control form-control-sm" />
              <input v-model="newBreak.start_time" type="time" class="form-control form-control-sm" />
              <input v-model="newBreak.end_time" type="time" class="form-control form-control-sm" />
              <button type="button" class="btn btn-sm btn-primary text-nowrap" @click="addNewBreak">
                <span class="fas fa-plus me-1"></span> Tambah Break
              </button>
            </div>
          </div>
          <div class="modal-footer py-2 bg-light">
            <button type="button" class="btn btn-sm btn-falcon-default" @click="showSettingsModal = false">Tutup</button>
            <button type="button" class="btn btn-sm btn-primary" @click="saveAllSettings">Simpan Perubahan</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useScheduler } from '../composables/useScheduler';
import BracketCanvas from './BracketCanvas.vue';
import PoolStage from './PoolStage.vue';
import StandingsStrip from './StandingsStrip.vue';
import ScoreInput from './ScoreInput.vue';
import ConfigPanel from './ConfigPanel.vue';

const props = defineProps({
  apiBase: {
    type: String,
    required: true,
  },
  eventId: {
    type: [Number, String],
    required: true,
  },
});

const {
  state,
  poolPhase,
  knockoutPhase,
  pools,
  bracketNodes,
  loadConfig,
  selectCategory,
  createPhase,
  generatePhase,
  resetPhase,
  resetAll,
  swapSeeds,
  updateGame,
  finalizeGame,
  recalculateSchedule,
  saveSettings,
  storeBreak,
  destroyBreak,
  syncCourts,
} = useScheduler(props.apiBase, props.eventId);

const currentTab = ref('bracket');

// Modals
const showScoreModal = ref(false);
const selectedGameForScore = ref(null);

const showConfigModal = ref(false);
const showAssignModal = ref(false);
const showSettingsModal = ref(false);

const assignGameTarget = ref(null);
const assignCourtId = ref(null);
const assignGameTime = ref('');

const selectedCourtIds = ref([]);
const settingsForm = ref({
  start_time: '08:00',
  end_time_daily: '21:00',
  break_between_games: 15,
});

const newBreak = ref({
  label: '',
  date: '',
  start_time: '12:00',
  end_time: '13:00',
});

// Drag state for court lanes
const draggedGame = ref(null);

const exportCsvUrl = computed(() => {
  if (!props.apiBase) return '';
  const cleanBase = props.apiBase.replace(/\/+$/, '');
  const phaseId = poolPhase.value?.id;
  return phaseId
    ? `${cleanBase}/export/standings-csv?phase_id=${phaseId}`
    : `${cleanBase}/export/standings-csv`;
});

const finalNode = computed(() => {
  return bracketNodes.value.find(n => n.phase === 'knockout' && !n.next_node_id) || null;
});

const thirdPlaceNode = computed(() => {
  return bracketNodes.value.find(n => n.phase === 'third_place') || null;
});

const getCourtGames = (courtId) => {
  return state.games.filter(g => g.venue_court_id === courtId);
};

const formatTime = (timeStr) => {
  if (!timeStr) return '';
  const d = new Date(timeStr);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const isKnockoutGame = (game) => {
  if (!game) return false;
  return !poolPhase.value?.id || game.tournament_phase_id !== poolPhase.value.id;
};

// Score modal triggers
const openScoreModal = (game) => {
  selectedGameForScore.value = game;
  showScoreModal.value = true;
};

const submitScore = async ({ gameId, scoreA, scoreB }) => {
  try {
    await finalizeGame(gameId, scoreA, scoreB);
    showScoreModal.value = false;
  } catch {
    // Handled in composable
  }
};

// Seed swap trigger from PoolStage
const handleSwapSeeds = async ({ poolId, teamIdA, teamIdB }) => {
  await swapSeeds(poolId, teamIdA, teamIdB);
};

// Config submit trigger from ConfigPanel
const handleConfigSubmit = async (payload) => {
  try {
    await createPhase(payload);
    // Find newly created or updated phase and generate
    const createdPhase = state.phases.find(p => p.phase_type === payload.phase_type) || state.phases[state.phases.length - 1];
    if (createdPhase) {
      await generatePhase(createdPhase.id, {
        config: payload.config,
      });
    }
    if (payload.phase_type === 'pool') {
      currentTab.value = 'pools';
    } else if (payload.phase_type === 'knockout') {
      currentTab.value = 'bracket';
    }
    showConfigModal.value = false;
  } catch {
    // Handled in composable
  }
};

// Reset all confirmation
const confirmResetAll = async () => {
  if (confirm('PERINGATAN: Apakah Anda yakin ingin mereset seluruh fase dan jadwal turnamen untuk kategori ini? Tindakan ini tidak dapat dibatalkan.')) {
    await resetAll();
  }
};

// Print bracket
const printBracket = () => {
  window.print();
};

// Drag and drop for games into court lanes
const onGameDragStart = (e, game) => {
  draggedGame.value = game;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(game.id));
};

const onCourtDrop = async (e, court) => {
  if (!draggedGame.value) return;
  const game = draggedGame.value;
  draggedGame.value = null;
  await updateGame(game.id, {
    venue_court_id: court.id,
  });
};

// Manual assign court modal
const openAssignCourtModal = (game) => {
  assignGameTarget.value = game;
  assignCourtId.value = game.venue_court_id || null;
  assignGameTime.value = game.game_time ? game.game_time.substring(0, 16) : '';
  showAssignModal.value = true;
};

const saveManualSchedule = async () => {
  if (!assignGameTarget.value) return;
  await updateGame(assignGameTarget.value.id, {
    venue_court_id: assignCourtId.value,
    game_time: assignGameTime.value || null,
  });
  showAssignModal.value = false;
};

// Save schedule settings and court sync
const saveAllSettings = async () => {
  if (selectedCourtIds.value.length > 0) {
    await syncCourts(selectedCourtIds.value);
  }
  await saveSettings(settingsForm.value);
  showSettingsModal.value = false;
};

const addNewBreak = async () => {
  if (!newBreak.value.date || !newBreak.value.start_time || !newBreak.value.end_time) {
    alert('Tanggal, jam mulai, dan jam selesai istirahat wajib diisi.');
    return;
  }
  await storeBreak(newBreak.value);
  newBreak.value = { label: '', date: '', start_time: '12:00', end_time: '13:00' };
};

onMounted(async () => {
  await loadConfig();
  if (state.settings) {
    settingsForm.value = {
      start_time: state.settings.start_time || '08:00',
      end_time_daily: state.settings.end_time_daily || '21:00',
      break_between_games: state.settings.break_between_games ?? 15,
    };
  }
  selectedCourtIds.value = state.courts.map(c => c.id);
});
</script>

<style scoped>
.scheduler-app {
  min-height: 500px;
}
</style>
