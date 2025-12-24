(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};

    const Telemetry = NS.TaskTelemetry || null;

    function reset(task) { if (Telemetry && Telemetry.reset) Telemetry.reset(task); }
    function onScroll() { if (Telemetry && Telemetry.onScroll) Telemetry.onScroll(); }
    function onAnyInteraction() { if (Telemetry && Telemetry.onAnyInteraction) Telemetry.onAnyInteraction(); }

    function setDiamondsTotal(n) { if (Telemetry && Telemetry.setDiamondsTotal) Telemetry.setDiamondsTotal(n); }
    function setDiamondsCollected(n) { if (Telemetry && Telemetry.setDiamondsCollected) Telemetry.setDiamondsCollected(n); }

    function setMaxScrollPercentSeen(p) { if (Telemetry && Telemetry.setMaxScrollPercentSeen) Telemetry.setMaxScrollPercentSeen(p); }
    function setScrollTaskTargetPercent(p) { if (Telemetry && Telemetry.setScrollTaskTargetPercent) Telemetry.setScrollTaskTargetPercent(p); }

    function getState() { return Telemetry && Telemetry.getState ? Telemetry.getState() : null; }
    function getDocMetrics() { return Telemetry && Telemetry.getDocMetrics ? Telemetry.getDocMetrics() : null; }

    M.telemetryBridge = {
        reset, onScroll, onAnyInteraction,
        setDiamondsTotal, setDiamondsCollected,
        setMaxScrollPercentSeen, setScrollTaskTargetPercent,
        getState, getDocMetrics,
    };
})();