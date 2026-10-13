<template>
  <div
    :id="'bracket-node-' + node.id"
    class="bracket-match-card card border shadow-sm mb-3 position-relative"
    :class="{
      'border-primary': isFinished,
      'border-warning': isLive,
      'opacity-75': isBye
    }"
    style="width: 260px; min-height: 110px;"
  >
    <!-- Card Header -->
    <div class="card-header py-1 px-2 bg-light d-flex justify-content-between align-items-center fs--2 border-bottom">
      <span class="fw-bold text-700 text-truncate me-1">
        {{ node.game?.match_label || `M#${node.match_order || node.id}` }}
      </span>
      <div class="d-flex align-items-center gap-1">
        <span v-if="isFinished" class="badge bg-success-subtle text-success py-0 px-1">Selesai</span>
        <span v-else-if="isLive" class="badge bg-warning-subtle text-warning py-0 px-1">Live</span>
        <span v-else-if="isBye" class="badge bg-secondary-subtle text-secondary py-0 px-1">BYE</span>
        <span v-else class="badge bg-light text-secondary border py-0 px-1">Jadwal</span>

        <button
          v-if="node.game && !isBye"
          type="button"
          class="btn btn-falcon-default btn-sm py-0 px-1 fs--2 ms-1"
          title="Input atau edit skor"
          @click.stop="$emit('open-score', node.game)"
        >
          <span class="fas fa-edit"></span>
        </button>
      </div>
    </div>

    <!-- Card Body -->
    <div class="card-body p-2 d-flex flex-column justify-content-center">
      <!-- Team A Row -->
      <div
        class="d-flex justify-content-between align-items-center py-1 px-2 rounded mb-1"
        :class="{
          'bg-success-subtle fw-bold text-success': isTeamAWinner,
          'bg-100': !isTeamAWinner
        }"
      >
        <div class="d-flex align-items-center text-truncate me-2">
          <span v-if="isTeamAWinner" class="fas fa-trophy text-warning fs--2 me-1"></span>
          <span class="fs--1 text-truncate" :title="teamAName">
            {{ teamAName }}
          </span>
        </div>
        <span
          class="badge fs--1 px-2 py-1"
          :class="isTeamAWinner ? 'bg-success text-white' : 'bg-200 text-dark'"
        >
          {{ scoreADisplay }}
        </span>
      </div>

      <!-- Team B Row -->
      <div
        class="d-flex justify-content-between align-items-center py-1 px-2 rounded"
        :class="{
          'bg-success-subtle fw-bold text-success': isTeamBWinner,
          'bg-100': !isTeamBWinner
        }"
      >
        <div class="d-flex align-items-center text-truncate me-2">
          <span v-if="isTeamBWinner" class="fas fa-trophy text-warning fs--2 me-1"></span>
          <span class="fs--1 text-truncate" :title="teamBName">
            {{ teamBName }}
          </span>
        </div>
        <span
          class="badge fs--1 px-2 py-1"
          :class="isTeamBWinner ? 'bg-success text-white' : 'bg-200 text-dark'"
        >
          {{ scoreBDisplay }}
        </span>
      </div>
    </div>

    <!-- Card Footer (Venue / Time) -->
    <div v-if="node.game?.game_time || node.game?.venue_court" class="card-footer py-1 px-2 bg-light border-top fs--2 text-muted d-flex justify-content-between">
      <span v-if="node.game?.venue_court" class="text-truncate me-1">
        <i class="fas fa-map-marker-alt me-1"></i>{{ node.game.venue_court.name }}
      </span>
      <span v-if="node.game?.game_time">
        <i class="fas fa-clock me-1"></i>{{ formatTime(node.game.game_time) }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
});

defineEmits(['open-score']);

const game = computed(() => props.node.game || null);

const isFinished = computed(() => game.value?.game_status === 'finished');
const isLive = computed(() => game.value?.game_status === 'live');
const isBye = computed(() => props.node.node_type === 'bye' || game.value?.is_bye);

const teamAName = computed(() => {
  return game.value?.team_a?.team_name || game.value?.team_a_label || props.node.source_label || 'TBD (Slot A)';
});

const teamBName = computed(() => {
  return game.value?.team_b?.team_name || game.value?.team_b_label || 'TBD (Slot B)';
});

const scoreADisplay = computed(() => {
  if (game.value?.score_a !== null && game.value?.score_a !== undefined) {
    return game.value.score_a;
  }
  return '-';
});

const scoreBDisplay = computed(() => {
  if (game.value?.score_b !== null && game.value?.score_b !== undefined) {
    return game.value.score_b;
  }
  return '-';
});

const isTeamAWinner = computed(() => {
  if (!isFinished.value || game.value?.score_a === null || game.value?.score_b === null) return false;
  return game.value.score_a > game.value.score_b;
});

const isTeamBWinner = computed(() => {
  if (!isFinished.value || game.value?.score_a === null || game.value?.score_b === null) return false;
  return game.value.score_b > game.value.score_a;
});

const formatTime = (timeStr) => {
  if (!timeStr) return '';
  const d = new Date(timeStr);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<style scoped>
.bracket-match-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.bracket-match-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}
</style>
