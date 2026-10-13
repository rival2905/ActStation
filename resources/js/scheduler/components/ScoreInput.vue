<template>
  <div v-if="show" class="modal d-block" tabindex="-1" role="dialog" style="background: rgba(0, 0, 0, 0.5); z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content shadow border-0">
        <div class="modal-header bg-100 py-2">
          <h5 class="modal-title fs-0 fw-bold">
            <span class="fas fa-edit me-1 text-primary"></span>
            Input Skor Pertandingan
          </h5>
          <button type="button" class="btn-close" aria-label="Close" @click="onClose"></button>
        </div>

        <form @submit.prevent="onSubmit">
          <div class="modal-body p-4">
            <div class="text-center mb-3">
              <span class="badge bg-secondary mb-2">{{ game?.match_label || game?.round_label || 'Pertandingan' }}</span>
              <div v-if="game?.game_time || game?.venue_court?.name" class="text-secondary fs--1">
                <span v-if="game?.venue_court?.name" class="me-2">
                  <i class="fas fa-map-marker-alt me-1"></i>{{ game.venue_court.name }}
                </span>
                <span v-if="game?.game_time">
                  <i class="fas fa-clock me-1"></i>{{ formatTime(game.game_time) }}
                </span>
              </div>
            </div>

            <div class="row align-items-center g-3">
              <!-- Team A -->
              <div class="col-5 text-center">
                <div class="fw-bold fs-0 text-truncate mb-2" :title="teamAName">
                  {{ teamAName }}
                </div>
                <input
                  v-model.number="scoreA"
                  type="number"
                  min="0"
                  class="form-control form-control-lg text-center fw-bold fs-2"
                  placeholder="0"
                  required
                  autofocus
                  aria-label="Skor Tim A"
                />
              </div>

              <!-- VS Divider -->
              <div class="col-2 text-center text-muted fw-bold fs-1">
                VS
              </div>

              <!-- Team B -->
              <div class="col-5 text-center">
                <div class="fw-bold fs-0 text-truncate mb-2" :title="teamBName">
                  {{ teamBName }}
                </div>
                <input
                  v-model.number="scoreB"
                  type="number"
                  min="0"
                  class="form-control form-control-lg text-center fw-bold fs-2"
                  placeholder="0"
                  required
                  aria-label="Skor Tim B"
                />
              </div>
            </div>

            <!-- Validation Error Alert -->
            <div v-if="localError" class="alert alert-danger fs--1 mb-0 mt-3 p-2 d-flex align-items-center">
              <span class="fas fa-exclamation-triangle me-2"></span>
              <div>{{ localError }}</div>
            </div>
          </div>

          <div class="modal-footer bg-100 py-2">
            <button type="button" class="btn btn-sm btn-falcon-default" @click="onClose" :disabled="loading">
              Batal
            </button>
            <button type="submit" class="btn btn-sm btn-primary px-4" :disabled="loading || !canSubmit">
              <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status"></span>
              <span class="fas fa-check me-1"></span>
              Simpan & Finalisasi
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  game: {
    type: Object,
    default: null,
  },
  show: {
    type: Boolean,
    default: false,
  },
  isKnockout: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'submit']);

const scoreA = ref(0);
const scoreB = ref(0);
const localError = ref(null);

watch(
  () => props.game,
  (newGame) => {
    if (newGame) {
      scoreA.value = newGame.score_a !== null && newGame.score_a !== undefined ? newGame.score_a : 0;
      scoreB.value = newGame.score_b !== null && newGame.score_b !== undefined ? newGame.score_b : 0;
      localError.value = null;
    }
  },
  { immediate: true }
);

const teamAName = computed(() => {
  if (!props.game) return 'Tim A';
  return props.game.team_a?.team_name || props.game.team_a_label || 'TBD (Tim A)';
});

const teamBName = computed(() => {
  if (!props.game) return 'Tim B';
  return props.game.team_b?.team_name || props.game.team_b_label || 'TBD (Tim B)';
});

const canSubmit = computed(() => {
  return scoreA.value >= 0 && scoreB.value >= 0;
});

const formatTime = (isoString) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const onClose = () => {
  localError.value = null;
  emit('close');
};

const onSubmit = () => {
  localError.value = null;
  if (scoreA.value < 0 || scoreB.value < 0) {
    localError.value = 'Skor tidak boleh bernilai negatif.';
    return;
  }
  if (props.isKnockout && scoreA.value === scoreB.value) {
    localError.value = 'Pertandingan babak knockout tidak boleh berakhir imbang / seri.';
    return;
  }
  emit('submit', {
    gameId: props.game.id,
    scoreA: scoreA.value,
    scoreB: scoreB.value,
  });
};
</script>
