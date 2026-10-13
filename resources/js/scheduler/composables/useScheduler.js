// resources/js/scheduler/composables/useScheduler.js
import { reactive, computed } from 'vue';
import { useApi } from './useApi';

export function useScheduler(apiBase, eventId) {
    const api = useApi(apiBase);

    const state = reactive({
        loading: false,
        actionLoading: false,
        error: null,
        validationErrors: {},
        successMessage: null,

        event: { id: eventId, title: '' },
        categories: [],
        selectedCategoryId: null,

        phases: [],
        games: [],
        courts: [],
        allCourts: [],
        settings: {
            start_time: '08:00',
            end_time_daily: '21:00',
            break_between_games: 15,
            durations: { pool: 60, r16: 60, qf: 70, sf: 80, third_place: 80, final: 120 },
        },
        breaks: [],
        officials: [],
        participants: [],
    });

    const activeCategory = computed(() => {
        return state.categories.find(c => c.id === state.selectedCategoryId) || null;
    });

    const poolPhase = computed(() => {
        return state.phases.find(p => p.phase_type === 'pool' && (!p.event_category_id || p.event_category_id === state.selectedCategoryId)) || null;
    });

    const knockoutPhase = computed(() => {
        return state.phases.find(p => p.phase_type === 'knockout' && (!p.event_category_id || p.event_category_id === state.selectedCategoryId)) || null;
    });

    const placementPhase = computed(() => {
        return state.phases.find(p => p.phase_type === 'placement' && (!p.event_category_id || p.event_category_id === state.selectedCategoryId)) || null;
    });

    const pools = computed(() => {
        if (!poolPhase.value || !poolPhase.value.pools) return [];
        return poolPhase.value.pools;
    });

    const bracketNodes = computed(() => {
        if (!knockoutPhase.value) return [];
        const nodes = knockoutPhase.value.bracket_nodes || knockoutPhase.value.nodes || [];
        return Array.isArray(nodes) ? nodes : [];
    });

    const clearMessages = () => {
        state.error = null;
        state.validationErrors = {};
        state.successMessage = null;
    };

    const handleError = (err) => {
        state.error = err.message || 'Terjadi kesalahan sistem.';
        state.validationErrors = err.errors || {};
        setTimeout(() => {
            if (state.error === err.message) state.error = null;
        }, 8000);
    };

    const handleSuccess = (msg) => {
        state.successMessage = msg;
        setTimeout(() => {
            if (state.successMessage === msg) state.successMessage = null;
        }, 5000);
    };

    const loadConfig = async (categoryId = null) => {
        clearMessages();
        state.loading = true;
        try {
            const catId = categoryId || state.selectedCategoryId;
            const params = catId ? { category_id: catId } : {};
            const res = await api.get('/config', params);
            const data = res.data !== undefined ? res.data : res;

            if (data.event) state.event = data.event;
            if (Array.isArray(data.categories)) {
                state.categories = data.categories;
                if (!state.selectedCategoryId && data.categories.length > 0) {
                    state.selectedCategoryId = data.categories[0].id;
                }
            }
            if (data.active_category_id) {
                state.selectedCategoryId = data.active_category_id;
            }

            state.phases = Array.isArray(data.phases) ? data.phases : [];

            // Ambil games dari top-level data.games atau kumpulkan dari phase.games
            let loadedGames = Array.isArray(data.games) ? data.games : [];
            if (loadedGames.length === 0 && state.phases.length > 0) {
                state.phases.forEach(ph => {
                    if (Array.isArray(ph.games)) {
                        loadedGames = loadedGames.concat(ph.games);
                    }
                });
            }
            state.games = loadedGames;
            state.courts = Array.isArray(data.courts) ? data.courts : [];
            if (Array.isArray(data.all_courts)) state.allCourts = data.all_courts;
            if (data.settings) state.settings = { ...state.settings, ...data.settings };
            state.breaks = Array.isArray(data.breaks) ? data.breaks : [];
            state.officials = Array.isArray(data.officials) ? data.officials : [];
            state.participants = Array.isArray(data.participants) ? data.participants : [];
        } catch (err) {
            handleError(err);
        } finally {
            state.loading = false;
        }
    };

    const selectCategory = async (categoryId) => {
        if (state.selectedCategoryId === categoryId) return;
        state.selectedCategoryId = categoryId;
        await loadConfig(categoryId);
    };

    const createPhase = async (payload) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post('/phases', {
                event_category_id: state.selectedCategoryId,
                ...payload,
            });
            handleSuccess('Fase berhasil ditambahkan.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const updatePhase = async (phaseId, payload) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.put(`/phases/${phaseId}`, payload);
            handleSuccess('Konfigurasi fase berhasil diperbarui.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const deletePhase = async (phaseId) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.del(`/phases/${phaseId}`);
            handleSuccess('Fase berhasil dihapus.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const generatePhase = async (phaseId, payload = {}) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post(`/phases/${phaseId}/generate`, payload);
            handleSuccess('Bagan / jadwal turnamen berhasil digenerate.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const resetPhase = async (phaseId) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post(`/phases/${phaseId}/reset`);
            handleSuccess('Fase berhasil direset.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const resetAll = async () => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.del(`/reset-all?category_id=${state.selectedCategoryId}`);
            handleSuccess('Semua fase & bagan berhasil direset.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const assignTeams = async (phaseId, teams, crossPool = true) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post('/assign-teams', {
                phase_id: phaseId,
                teams: teams,
                cross_pool: crossPool,
            });
            handleSuccess('Penempatan tim berhasil diperbarui.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const swapSeeds = async (poolId, teamIdA, teamIdB) => {
        clearMessages();
        state.actionLoading = true;
        try {
            const pool = pools.value.find(p => p.id === poolId);
            if (!pool) return;
            const teams = [...(pool.teams || pool.pool_teams || [])];
            const idxA = teams.findIndex(t => (t.event_participant_id || t.id) === teamIdA);
            const idxB = teams.findIndex(t => (t.event_participant_id || t.id) === teamIdB);
            if (idxA === -1 || idxB === -1) return;

            const temp = teams[idxA];
            teams[idxA] = teams[idxB];
            teams[idxB] = temp;

            const orderedTeamIds = teams.map(t => t.event_participant_id || t.id);
            if (poolPhase.value) {
                await assignTeams(poolPhase.value.id, orderedTeamIds);
            }
        } catch (err) {
            handleError(err);
        } finally {
            state.actionLoading = false;
        }
    };

    const updateGame = async (gameId, payload) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.put(`/games/${gameId}`, payload);
            handleSuccess('Jadwal pertandingan berhasil disimpan.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const finalizeGame = async (gameId, scoreA, scoreB) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post(`/finalize-game/${gameId}`, {
                score_a: Number(scoreA),
                score_b: Number(scoreB),
            });
            handleSuccess('Skor pertandingan berhasil difinalisasi.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const recalculateSchedule = async () => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post('/schedule/recalculate', {
                category_id: state.selectedCategoryId,
            });
            handleSuccess('Jadwal pertandingan berhasil dihitung ulang secara otomatis.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const saveSettings = async (payload) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post('/schedule-settings', payload);
            handleSuccess('Pengaturan waktu turnamen berhasil disimpan.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const storeBreak = async (payload) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post('/schedule-breaks', payload);
            handleSuccess('Waktu istirahat berhasil ditambahkan.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const destroyBreak = async (breakId) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.del(`/schedule-breaks/${breakId}`);
            handleSuccess('Waktu istirahat berhasil dihapus.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const syncCourts = async (courtIds) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post('/courts/sync', { venue_court_ids: courtIds });
            handleSuccess('Daftar lapangan berhasil diperbarui.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    const assignOfficial = async (gameId, userId, duty) => {
        clearMessages();
        state.actionLoading = true;
        try {
            await api.post(`/games/${gameId}/officials`, {
                officials: [{ user_id: userId, duty }],
            });
            handleSuccess('Petugas pertandingan berhasil ditetapkan.');
            await loadConfig();
        } catch (err) {
            handleError(err);
            throw err;
        } finally {
            state.actionLoading = false;
        }
    };

    return {
        state,
        activeCategory,
        poolPhase,
        knockoutPhase,
        placementPhase,
        pools,
        bracketNodes,
        clearMessages,
        loadConfig,
        selectCategory,
        createPhase,
        updatePhase,
        deletePhase,
        generatePhase,
        resetPhase,
        resetAll,
        assignTeams,
        swapSeeds,
        updateGame,
        finalizeGame,
        recalculateSchedule,
        saveSettings,
        storeBreak,
        destroyBreak,
        syncCourts,
        assignOfficial,
    };
}
