<template>
  <div class="pool-stage">
    <!-- Header / Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
      <div class="d-flex align-items-center gap-2">
        <h5 class="mb-0 fw-bold text-900">
          <span class="fas fa-layer-group text-primary me-2"></span>
          Babak Pool / Round Robin ({{ phase?.name || 'Pool Stage' }})
        </h5>
        <span class="badge bg-primary-subtle text-primary">{{ pools.length }} Pool</span>
      </div>

      <div class="d-flex align-items-center gap-2">
        <!-- Export Standings CSV button -->
        <a
          v-if="exportCsvUrl"
          :href="exportCsvUrl"
          class="btn btn-sm btn-outline-success"
          download
          title="Ekspor klasemen ke CSV (UTF-8 BOM ;)"
        >
          <span class="fas fa-file-csv me-1"></span>
          Export Standings (CSV)
        </a>

        <!-- Reset Phase -->
        <button
          type="button"
          class="btn btn-sm btn-falcon-danger"
          @click="$emit('reset-phase', phase.id)"
          title="Reset babak ini"
        >
          <span class="fas fa-undo-alt me-1"></span>
          Reset Babak
        </button>
      </div>
    </div>

    <!-- Pools Grid -->
    <div class="row g-3">
      <div v-for="pool in pools" :key="pool.id" class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border">
          <!-- Pool Card Header -->
          <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="mb-0 fw-bold text-primary d-flex align-items-center">
              <span class="badge bg-primary text-white me-2">{{ pool.name }}</span>
              <span>Klasemen & Susunan Seed</span>
            </h6>
            <span class="badge bg-200 text-dark fs--2">{{ (pool.teams || pool.pool_teams || []).length }} Tim</span>
          </div>

          <!-- Standings Table & Seed Swap -->
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-striped table-hover mb-0 align-middle fs--1">
                <thead class="bg-200 text-800">
                  <tr>
                    <th style="width: 40px;" class="text-center">Seed</th>
                    <th>Tim Peserta</th>
                    <th style="width: 32px;" class="text-center" title="Main">M</th>
                    <th style="width: 32px;" class="text-center" title="Menang">W</th>
                    <th style="width: 32px;" class="text-center" title="Seri">D</th>
                    <th style="width: 32px;" class="text-center" title="Kalah">L</th>
                    <th style="width: 38px;" class="text-center" title="Poin Memasukkan">PF</th>
                    <th style="width: 38px;" class="text-center" title="Poin Kemasukan">PA</th>
                    <th style="width: 38px;" class="text-center" title="Selisih Poin">DIFF</th>
                    <th style="width: 44px;" class="text-center fw-bold text-primary" title="Poin Klasemen">PTS</th>
                    <th style="width: 70px;" class="text-center d-print-none">Atur</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(team, idx) in (pool.teams || pool.pool_teams || [])"
                    :key="team.id || team.event_participant_id"
                    :draggable="true"
                    @dragstart="onDragStart($event, team, pool)"
                    @dragover.prevent="onDragOver($event)"
                    @drop="onDrop($event, team, pool)"
                    :class="{
                      'table-active': draggedTeam && draggedTeam.id === team.id
                    }"
                    style="cursor: grab;"
                    title="Drag untuk menukar seed tim ini"
                  >
                    <!-- Seed badge -->
                    <td class="text-center fw-bold text-600">
                      <span class="badge bg-300 text-dark py-1 px-2">#{{ team.seed || (idx + 1) }}</span>
                    </td>

                    <!-- Team Name -->
                    <td>
                      <div class="fw-bold text-truncate" style="max-width: 170px;" :title="getTeamName(team)">
                        <span class="fas fa-grip-vertical text-400 me-1 d-print-none" style="font-size: 0.8rem;"></span>
                        {{ getTeamName(team) }}
                      </div>
                    </td>

                    <!-- Stats -->
                    <td class="text-center">{{ (team.wins || 0) + (team.draws || 0) + (team.losses || 0) }}</td>
                    <td class="text-center text-success fw-bold">{{ team.wins || 0 }}</td>
                    <td class="text-center text-secondary">{{ team.draws || 0 }}</td>
                    <td class="text-center text-danger">{{ team.losses || 0 }}</td>
                    <td class="text-center">{{ team.points_for || 0 }}</td>
                    <td class="text-center">{{ team.points_against || 0 }}</td>
                    <td class="text-center fw-bold" :class="(team.point_differential || 0) >= 0 ? 'text-success' : 'text-danger'">
                      {{ (team.point_differential || 0) > 0 ? '+' : '' }}{{ team.point_differential || 0 }}
                    </td>
                    <td class="text-center fw-bold fs-0 text-primary bg-primary-subtle">
                      {{ team.pool_points || 0 }}
                    </td>

                    <!-- Accessible Keyboard Fallback Buttons -->
                    <td class="text-center d-print-none">
                      <div class="btn-group btn-group-sm" role="group" aria-label="Aksesibilitas pindah seed">
                        <button
                          type="button"
                          class="btn btn-falcon-default py-0 px-1"
                          :disabled="idx === 0"
                          title="Pindah seed ke atas"
                          aria-label="Pindah seed ke atas"
                          @click.stop="moveSeed(pool, idx, -1)"
                        >
                          <span class="fas fa-chevron-up fs--2"></span>
                        </button>
                        <button
                          type="button"
                          class="btn btn-falcon-default py-0 px-1"
                          :disabled="idx === (pool.teams || pool.pool_teams || []).length - 1"
                          title="Pindah seed ke bawah"
                          aria-label="Pindah seed ke bawah"
                          @click.stop="moveSeed(pool, idx, 1)"
                        >
                          <span class="fas fa-chevron-down fs--2"></span>
                        </button>
                      </div>
                    </td>
                  </tr>

                  <tr v-if="!(pool.teams || pool.pool_teams || []).length">
                    <td colspan="11" class="text-center py-3 text-muted">
                      Belum ada tim yang ditempatkan di pool ini.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pool Matches Accordion / List -->
            <div v-if="getPoolGames(pool).length > 0" class="border-top p-2 bg-light">
              <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="fw-bold fs--2 text-700 text-uppercase">Pertandingan {{ pool.name }}</span>
                <span class="badge bg-secondary-subtle text-secondary fs--2">{{ getPoolGames(pool).length }} Game</span>
              </div>

              <div class="row g-2">
                <div v-for="game in getPoolGames(pool)" :key="game.id" class="col-12 col-md-6">
                  <div class="card border shadow-none bg-white p-2 h-100">
                    <div class="d-flex justify-content-between align-items-center fs--2 mb-1">
                      <span class="text-muted fw-bold">{{ game.match_label || `Game #${game.id}` }}</span>
                      <span v-if="game.game_status === 'finished'" class="badge bg-success py-0 px-1">Selesai</span>
                      <span v-else class="badge bg-light text-dark border py-0 px-1">Jadwal</span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center fs--1">
                      <div class="text-truncate me-1" style="max-width: 65%;">
                        <div :class="{ 'fw-bold text-success': game.score_a > game.score_b }" class="text-truncate">
                          {{ game.team_a?.team_name || game.team_a_label || 'Tim A' }}
                        </div>
                        <div :class="{ 'fw-bold text-success': game.score_b > game.score_a }" class="text-truncate">
                          {{ game.team_b?.team_name || game.team_b_label || 'Tim B' }}
                        </div>
                      </div>

                      <div class="d-flex flex-column align-items-end">
                        <span class="badge bg-200 text-dark mb-1">
                          {{ game.score_a !== null ? game.score_a : '-' }} : {{ game.score_b !== null ? game.score_b : '-' }}
                        </span>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary py-0 px-2 fs--2"
                          @click="$emit('open-score', game)"
                        >
                          Skor
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  phase: {
    type: Object,
    required: true,
  },
  games: {
    type: Array,
    default: () => [],
  },
  apiBase: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['open-score', 'swap-seeds', 'reset-phase']);

const draggedTeam = ref(null);
const draggedPool = ref(null);

const pools = computed(() => {
  return props.phase?.pools || [];
});

const exportCsvUrl = computed(() => {
  if (!props.apiBase || !props.phase?.id) return '';
  const cleanBase = props.apiBase.replace(/\/+$/, '');
  return `${cleanBase}/export/standings-csv?phase_id=${props.phase.id}`;
});

const getTeamName = (team) => {
  return team.event_participant?.team_name || team.team?.team_name || team.team_name || `Tim #${team.seed || team.id}`;
};

const getPoolGames = (pool) => {
  if (!props.games || props.games.length === 0) return [];
  // Filter games belonging to this phase and containing pool teams
  const poolTeamIds = new Set(
    (pool.teams || pool.pool_teams || []).map(t => t.event_participant_id || t.id)
  );
  return props.games.filter(g => {
    return poolTeamIds.has(g.team_a_id) && poolTeamIds.has(g.team_b_id);
  });
};

const onDragStart = (event, team, pool) => {
  draggedTeam.value = team;
  draggedPool.value = pool;
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(team.event_participant_id || team.id));
};

const onDragOver = (event) => {
  event.dataTransfer.dropEffect = 'move';
};

const onDrop = (event, targetTeam, targetPool) => {
  if (!draggedTeam.value || !draggedPool.value) return;
  if (draggedPool.value.id !== targetPool.id) {
    // Cross pool swap or move if permitted
    draggedTeam.value = null;
    draggedPool.value = null;
    return;
  }
  const sourceId = draggedTeam.value.event_participant_id || draggedTeam.value.id;
  const targetId = targetTeam.event_participant_id || targetTeam.id;

  if (sourceId !== targetId) {
    emit('swap-seeds', {
      poolId: targetPool.id,
      teamIdA: sourceId,
      teamIdB: targetId,
    });
  }
  draggedTeam.value = null;
  draggedPool.value = null;
};

// Accessible keyboard fallback to move seed up or down
const moveSeed = (pool, currentIndex, direction) => {
  const teams = pool.teams || pool.pool_teams || [];
  const targetIndex = currentIndex + direction;
  if (targetIndex < 0 || targetIndex >= teams.length) return;

  const currentTeam = teams[currentIndex];
  const targetTeam = teams[targetIndex];

  emit('swap-seeds', {
    poolId: pool.id,
    teamIdA: currentTeam.event_participant_id || currentTeam.id,
    teamIdB: targetTeam.event_participant_id || targetTeam.id,
  });
};
</script>
