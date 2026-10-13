<template>
  <div v-if="show" class="modal d-block" tabindex="-1" role="dialog" style="background: rgba(0, 0, 0, 0.5); z-index: 1050;">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
      <div class="modal-content shadow border-0">
        <div class="modal-header bg-light py-2 px-3">
          <h5 class="modal-title fs-0 fw-bold d-flex align-items-center">
            <span class="fas fa-sliders-h text-primary me-2"></span>
            Setup & Konfigurasi Fase Turnamen
          </h5>
          <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="modal-body p-4">
            <!-- Phase Type Selector -->
            <div class="mb-3">
              <label class="form-label fw-bold fs--1">Jenis Fase Turnamen <span class="text-danger">*</span></label>
              <div class="row g-2">
                <div class="col-4">
                  <div
                    class="card p-2 text-center border h-100 cursor-pointer"
                    :class="{ 'border-primary bg-primary-subtle text-primary fw-bold': phaseType === 'pool' }"
                    style="cursor: pointer;"
                    @click="phaseType = 'pool'"
                  >
                    <span class="fas fa-layer-group fs-2 mb-1"></span>
                    <span class="fs--1">Babak Pool (Grup)</span>
                  </div>
                </div>
                <div class="col-4">
                  <div
                    class="card p-2 text-center border h-100 cursor-pointer"
                    :class="{ 'border-primary bg-primary-subtle text-primary fw-bold': phaseType === 'knockout' }"
                    style="cursor: pointer;"
                    @click="phaseType = 'knockout'"
                  >
                    <span class="fas fa-sitemap fs-2 mb-1"></span>
                    <span class="fs--1">Babak Knockout (Gugur)</span>
                  </div>
                </div>
                <div class="col-4">
                  <div
                    class="card p-2 text-center border h-100 cursor-pointer"
                    :class="{ 'border-primary bg-primary-subtle text-primary fw-bold': phaseType === 'placement' }"
                    style="cursor: pointer;"
                    @click="phaseType = 'placement'"
                  >
                    <span class="fas fa-list-ol fs-2 mb-1"></span>
                    <span class="fs--1">Placement (Peringkat)</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Phase Name -->
            <div class="mb-3">
              <label class="form-label fw-bold fs--1" for="phaseName">Nama Babak / Fase <span class="text-danger">*</span></label>
              <input
                id="phaseName"
                v-model="phaseName"
                type="text"
                class="form-control form-control-sm"
                placeholder="Contoh: Babak Penyisihan Pool A-D"
                required
              />
            </div>

            <!-- Pool Config Fields -->
            <div v-if="phaseType === 'pool'" class="bg-light p-3 rounded border mb-3">
              <h6 class="fw-bold fs--1 text-700 mb-3 border-bottom pb-2">Parameter Babak Pool</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fs--2 fw-bold" for="poolCount">Jumlah Pool</label>
                  <select id="poolCount" v-model.number="poolCount" class="form-select form-select-sm">
                    <option :value="2">2 Pool</option>
                    <option :value="4">4 Pool</option>
                    <option :value="8">8 Pool</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fs--2 fw-bold" for="teamsPerPool">Tim per Pool</label>
                  <input
                    id="teamsPerPool"
                    v-model.number="teamsPerPool"
                    type="number"
                    min="2"
                    max="16"
                    class="form-control form-control-sm"
                  />
                </div>

                <div class="col-md-6">
                  <label class="form-label fs--2 fw-bold" for="advancePerPool">Tim Lolos per Pool</label>
                  <select id="advancePerPool" v-model.number="advancePerPool" class="form-select form-select-sm">
                    <option :value="1">Juara Pool Saja (1 Tim)</option>
                    <option :value="2">Juara & Runner-up (2 Tim)</option>
                    <option :value="3">3 Tim Teratas</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fs--2 fw-bold" for="roundRobinCount">Putaran Round Robin</label>
                  <select id="roundRobinCount" v-model.number="roundRobinCount" class="form-select form-select-sm">
                    <option :value="1">1 Putaran (Single Round Robin)</option>
                    <option :value="2">2 Putaran (Home & Away)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Knockout Config Fields -->
            <div v-if="phaseType === 'knockout'" class="bg-light p-3 rounded border mb-3">
              <h6 class="fw-bold fs--1 text-700 mb-3 border-bottom pb-2">Parameter Babak Gugur (Knockout)</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fs--2 fw-bold" for="bracketSize">Ukuran Bagan (Jumlah Slot Tim)</label>
                  <select id="bracketSize" v-model.number="bracketSize" class="form-select form-select-sm">
                    <option :value="4">4 Tim (Semifinal & Final)</option>
                    <option :value="8">8 Tim (Perempat Final s/d Final)</option>
                    <option :value="16">16 Tim (Babak 16 Besar s/d Final)</option>
                    <option :value="32">32 Tim (Babak 32 Besar)</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fs--2 fw-bold" for="seedingMethod">Metode Seeding</label>
                  <select id="seedingMethod" v-model="seedingMethod" class="form-select form-select-sm">
                    <option value="standings">Otomatis dari Klasemen Pool (Cross-Pool)</option>
                    <option value="manual">Manual / Sesuai Seed Terdaftar</option>
                  </select>
                </div>

                <div class="col-12">
                  <div class="form-check form-switch mb-2">
                    <input
                      id="thirdPlaceMatch"
                      v-model="thirdPlaceMatch"
                      class="form-check-input"
                      type="checkbox"
                    />
                    <label class="form-check-label fs--1" for="thirdPlaceMatch">
                      Sertakan Perebutan Juara 3 (Third Place Match)
                    </label>
                  </div>

                  <div class="form-check form-switch">
                    <input
                      id="crossPool"
                      v-model="crossPool"
                      class="form-check-input"
                      type="checkbox"
                    />
                    <label class="form-check-label fs--1" for="crossPool">
                      Cross-Pool Seeding (Juara Pool vs Runner-up Pool Lain di Babak 1)
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Placement Config Fields -->
            <div v-if="phaseType === 'placement'" class="bg-light p-3 rounded border mb-3">
              <h6 class="fw-bold fs--1 text-700 mb-3 border-bottom pb-2">Parameter Penentuan Peringkat (Placement)</h6>
              <div class="mb-2">
                <label class="form-label fs--2 fw-bold" for="positions">Target Posisi (pisahkan koma)</label>
                <input
                  id="positions"
                  v-model="positionsString"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="3, 5, 7"
                />
                <div class="form-text fs--2">Misal: 5, 7 untuk perebutan tempat ke-5 dan ke-7.</div>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light py-2 px-3">
            <button type="button" class="btn btn-sm btn-falcon-default" @click="$emit('close')" :disabled="loading">
              Batal
            </button>
            <button type="submit" class="btn btn-sm btn-primary px-4" :disabled="loading">
              <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status"></span>
              <span class="fas fa-magic me-1"></span>
              Simpan & Generate Bagan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalParticipants: {
    type: Number,
    default: 8,
  },
});

const emit = defineEmits(['close', 'submit']);

const phaseType = ref('pool');
const phaseName = ref('Babak Pool');

// Pool options
const poolCount = ref(4);
const teamsPerPool = ref(4);
const advancePerPool = ref(2);
const roundRobinCount = ref(1);

// Knockout options
const bracketSize = ref(8);
const thirdPlaceMatch = ref(true);
const crossPool = ref(true);
const seedingMethod = ref('standings');

// Placement options
const positionsString = ref('3, 5, 7');

watch(
  phaseType,
  (newType) => {
    if (newType === 'pool') phaseName.value = 'Babak Pool';
    if (newType === 'knockout') phaseName.value = 'Babak Knockout';
    if (newType === 'placement') phaseName.value = 'Babak Placement';
  }
);

const handleSubmit = () => {
  let config = {};

  if (phaseType.value === 'pool') {
    config = {
      pool_count: poolCount.value,
      teams_per_pool: teamsPerPool.value,
      team_count: poolCount.value * teamsPerPool.value,
      advance_per_pool: advancePerPool.value,
      round_robin_count: roundRobinCount.value,
      point_win: 2,
      point_draw: 1,
      point_lose: 0,
    };
  } else if (phaseType.value === 'knockout') {
    config = {
      bracket_size: bracketSize.value,
      team_count: bracketSize.value,
      third_place_match: thirdPlaceMatch.value,
      cross_pool: crossPool.value,
      seeding_method: seedingMethod.value,
    };
  } else if (phaseType.value === 'placement') {
    const pos = positionsString.value
      .split(',')
      .map(p => Number(p.trim()))
      .filter(p => !isNaN(p) && p > 0);
    config = {
      positions: pos.length > 0 ? pos : [3, 5, 7],
    };
  }

  emit('submit', {
    name: phaseName.value,
    phase_type: phaseType.value,
    config,
  });
};
</script>
