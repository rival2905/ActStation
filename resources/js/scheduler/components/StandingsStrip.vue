<template>
  <div v-if="hasData" class="card shadow-sm border-0 mb-3 bg-light overflow-hidden">
    <div class="card-body p-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <!-- Title & Live badge -->
        <div class="d-flex align-items-center gap-2">
          <div class="bg-primary text-white rounded p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
            <span class="fas fa-medal"></span>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2">
              <h6 class="mb-0 fw-bold text-900">Klasemen & Podium</h6>
              <span class="badge bg-success-subtle text-success border border-success-subtle fs--2">Live</span>
            </div>
            <div class="fs--2 text-600">Peringkat akumulatif performa turnamen</div>
          </div>
        </div>

        <!-- Podium Items -->
        <div class="d-flex flex-wrap align-items-center gap-2">
          <!-- 1st Place -->
          <div v-if="firstPlace" class="d-flex align-items-center bg-white px-3 py-1 rounded shadow-sm border">
            <span class="fas fa-trophy text-warning fs-0 me-2" title="Juara 1"></span>
            <div>
              <div class="fs--2 text-muted fw-bold text-uppercase">Juara 1</div>
              <div class="fw-bold fs--1 text-dark text-truncate" style="max-width: 140px;" :title="firstPlace.team_name || firstPlace.name">
                {{ firstPlace.team_name || firstPlace.name }}
              </div>
            </div>
          </div>

          <!-- 2nd Place -->
          <div v-if="secondPlace" class="d-flex align-items-center bg-white px-3 py-1 rounded shadow-sm border">
            <span class="fas fa-medal text-secondary fs-0 me-2" title="Juara 2"></span>
            <div>
              <div class="fs--2 text-muted fw-bold text-uppercase">Juara 2</div>
              <div class="fw-bold fs--1 text-dark text-truncate" style="max-width: 140px;" :title="secondPlace.team_name || secondPlace.name">
                {{ secondPlace.team_name || secondPlace.name }}
              </div>
            </div>
          </div>

          <!-- 3rd Place -->
          <div v-if="thirdPlace" class="d-flex align-items-center bg-white px-3 py-1 rounded shadow-sm border">
            <span class="fas fa-medal text-warning-emphasis fs-0 me-2" style="color: #cd7f32 !important;" title="Juara 3"></span>
            <div>
              <div class="fs--2 text-muted fw-bold text-uppercase">Juara 3</div>
              <div class="fw-bold fs--1 text-dark text-truncate" style="max-width: 140px;" :title="thirdPlace.team_name || thirdPlace.name">
                {{ thirdPlace.team_name || thirdPlace.name }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pool Leaders Preview -->
      <div v-if="poolLeaders.length > 0" class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top fs--2">
        <span class="text-600 fw-bold align-self-center me-1">Pemuncak Pool:</span>
        <span
          v-for="leader in poolLeaders"
          :key="leader.pool_name"
          class="badge bg-white text-dark border px-2 py-1 shadow-sm d-flex align-items-center gap-1"
        >
          <span class="text-primary fw-bold">{{ leader.pool_name }}:</span>
          <span class="text-truncate" style="max-width: 120px;">{{ leader.team_name }}</span>
          <span class="text-muted">({{ leader.points }} pts)</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  standings: {
    type: Array,
    default: () => [],
  },
  pools: {
    type: Array,
    default: () => [],
  },
  finalNode: {
    type: Object,
    default: null,
  },
  thirdPlaceNode: {
    type: Object,
    default: null,
  },
});

const firstPlace = computed(() => {
  // Check final match if finished
  if (props.finalNode?.game?.game_status === 'finished') {
    const g = props.finalNode.game;
    if (g.score_a > g.score_b) return g.team_a;
    if (g.score_b > g.score_a) return g.team_b;
  }
  return props.standings[0]?.team || props.standings[0] || null;
});

const secondPlace = computed(() => {
  if (props.finalNode?.game?.game_status === 'finished') {
    const g = props.finalNode.game;
    if (g.score_a > g.score_b) return g.team_b;
    if (g.score_b > g.score_a) return g.team_a;
  }
  return props.standings[1]?.team || props.standings[1] || null;
});

const thirdPlace = computed(() => {
  if (props.thirdPlaceNode?.game?.game_status === 'finished') {
    const g = props.thirdPlaceNode.game;
    if (g.score_a > g.score_b) return g.team_a;
    if (g.score_b > g.score_a) return g.team_b;
  }
  return props.standings[2]?.team || props.standings[2] || null;
});

const poolLeaders = computed(() => {
  if (!props.pools || props.pools.length === 0) return [];
  const leaders = [];
  props.pools.forEach((pool) => {
    const teams = pool.teams || pool.pool_teams || [];
    if (teams.length > 0) {
      // Sort by pool_points, differential, points_for
      const sorted = [...teams].sort((a, b) => {
        if ((b.pool_points ?? 0) !== (a.pool_points ?? 0)) {
          return (b.pool_points ?? 0) - (a.pool_points ?? 0);
        }
        return (b.point_differential ?? 0) - (a.point_differential ?? 0);
      });
      const top = sorted[0];
      const teamObj = top.event_participant || top.team || top;
      leaders.push({
        pool_name: pool.name,
        team_name: teamObj.team_name || teamObj.name || `Tim #${top.seed || 1}`,
        points: top.pool_points ?? 0,
      });
    }
  });
  return leaders;
});

const hasData = computed(() => {
  return firstPlace.value || secondPlace.value || thirdPlace.value || poolLeaders.value.length > 0;
});
</script>
