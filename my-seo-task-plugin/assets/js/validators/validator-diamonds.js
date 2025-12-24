(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};
    const utils = M.utils;
    const telemetry = M.telemetryBridge;
    const state = M.state.get();
    const DiamondManager = NS.DiamondManager || null;

    function decideTotalDiamonds(task) {
        const cfg = (task && task.config) || {};
        const minD = cfg.minDiamonds || 2;
        const maxD = cfg.maxDiamonds || 3;
        if (minD >= maxD) return minD;
        return minD + Math.floor(Math.random() * (maxD - minD + 1));
    }

    function pickUniquePercents(count) {
        const pool = [20, 30, 40, 50, 60, 70, 80];
        for (let i = pool.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [pool[i], pool[j]] = [pool[j], pool[i]];
        }
        return pool.slice(0, Math.min(count, pool.length)).sort((a, b) => a - b);
    }

    function pickLeftForIndex(i, viewportWidth) {
        const isMobile = viewportWidth <= 768;
        const cols = isMobile ? [0.2, 0.5, 0.8] : [0.3, 0.5, 0.7];
        return viewportWidth * cols[i % cols.length];
    }

    function updatePartialProgress() {
        const fraction = state.totalDiamonds > 0 ? (state.collectedDiamonds / state.totalDiamonds) : 1;
        const mgr = NS.TaskFlowManager;
        if (mgr && typeof mgr.updatePartialProgressForCurrentTask === 'function') {
            mgr.updatePartialProgressForCurrentTask(fraction);
        }
    }

    function setupDiamonds(task, onComplete) {
        if (!DiamondManager) return;
        const { viewportHeight, viewportWidth, maxScrollable } = utils.getDocumentMetrics();
        const percents = pickUniquePercents(state.totalDiamonds);
        state.diamonds = [];

        for (let i = 0; i < state.totalDiamonds; i++) {
            const id = i + 1;
            const p = percents[i] || 50;
            const topPx = (p / 100) * maxScrollable + viewportHeight * 0.2;
            const leftPx = pickLeftForIndex(i, viewportWidth);

            const el = DiamondManager.createDiamondAtDocumentPosition(
                id,
                { topPx, leftPx },
                function () {
                    const d = state.diamonds.find(x => x.id === id);
                    if (!d || d.collected) return;
                    d.collected = true;
                    state.collectedDiamonds += 1;

                    if (telemetry) telemetry.setDiamondsCollected(state.collectedDiamonds);

                    DiamondManager.removeDiamond(d.el, id);
                    d.el = null;

                    updatePartialProgress();

                    if (state.collectedDiamonds >= state.totalDiamonds) {
                        onComplete();
                    }
                }
            );

            state.diamonds.push({ id, collected: false, el, percent: p });
        }
    }

    function clearDiamonds() {
        if (DiamondManager) DiamondManager.clearAllDiamonds();
        state.diamonds = [];
        state.totalDiamonds = 0;
        state.collectedDiamonds = 0;
    }

    M.diamonds = {
        decideTotalDiamonds,
        setupDiamonds,
        clearDiamonds,
        updatePartialProgress,
    };
})();