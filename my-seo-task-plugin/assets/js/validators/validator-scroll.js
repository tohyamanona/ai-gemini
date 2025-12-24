(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};
    const utils = M.utils;
    const telemetry = M.telemetryBridge;

    function checkScrollTask(activeTask, onComplete) {
        if (!activeTask || activeTask.type !== 'scroll_to_percent') return;
        const need = (activeTask.config && activeTask.config.percent) || 50;
        const current = utils.getScrollPercent();
        if (telemetry) telemetry.setMaxScrollPercentSeen(current);
        if (current >= need) onComplete();
    }

    M.scroll = { checkScrollTask };
})();