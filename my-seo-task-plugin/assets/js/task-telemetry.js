(function () {
    if (!window.MySeoTask) window.MySeoTask = {};

    // Telemetry chỉ phục vụ gate/UX, không gửi server.
    // Reset mỗi lần startValidation().
    const state = {
        taskId: '',
        taskType: '',

        startedAt: 0,

        // human signals
        interactions: 0,
        scrollStartY: 0,
        maxScrollDistance: 0,
        maxDepthPercent: 0,
        pauseMs: 0,
        lastScrollAt: 0,

        // task-aware signals
        diamondsTotal: 0,
        diamondsCollected: 0,

        internalLinkClicksAttempted: 0, // click vào <a> bất kể internal/external (để đo effort)
        internalLinkClicksSuccess: 0,   // click internal link đúng task

        // for scroll_to_percent
        maxScrollPercentSeen: 0,
        targetScrollPercent: null,
    };

    function reset(task) {
        const now = Date.now();
        state.taskId = (task && task.id) || '';
        state.taskType = (task && task.type) || '';
        state.startedAt = now;

        state.interactions = 0;
        state.scrollStartY = window.pageYOffset || document.documentElement.scrollTop || 0;
        state.maxScrollDistance = 0;
        state.maxDepthPercent = 0;
        state.pauseMs = 0;
        state.lastScrollAt = now;

        state.diamondsTotal = 0;
        state.diamondsCollected = 0;

        state.internalLinkClicksAttempted = 0;
        state.internalLinkClicksSuccess = 0;

        state.maxScrollPercentSeen = 0;
        state.targetScrollPercent = null;
    }

    function getDocMetrics() {
        const doc = document.documentElement;
        const body = document.body;

        const viewportH = window.innerHeight || doc.clientHeight || 0;
        const scrollY = window.pageYOffset || doc.scrollTop || 0;

        const scrollHeight = Math.max(
            body.scrollHeight,
            doc.scrollHeight,
            body.offsetHeight,
            doc.offsetHeight,
            body.clientHeight,
            doc.clientHeight
        );

        const maxScrollable = Math.max(scrollHeight - viewportH, 0);
        const depthPercent = maxScrollable > 0 ? (scrollY / maxScrollable) * 100 : 100;

        return { viewportH, scrollY, scrollHeight, maxScrollable, depthPercent };
    }

    function onAnyInteraction() {
        state.interactions += 1;
    }

    function onScroll() {
        const now = Date.now();
        const { scrollY, depthPercent } = getDocMetrics();

        // accumulate pause time (time since last scroll)
        const dt = now - state.lastScrollAt;
        // nếu user có khoảng dừng > 120ms giữa các event scroll, vẫn tính pause theo dt
        if (dt > 120) state.pauseMs += dt;

        state.lastScrollAt = now;

        const dist = Math.abs(scrollY - state.scrollStartY);
        if (dist > state.maxScrollDistance) state.maxScrollDistance = dist;
        if (depthPercent > state.maxDepthPercent) state.maxDepthPercent = depthPercent;

        state.interactions += 1;
    }

    function setDiamondsTotal(n) {
        state.diamondsTotal = n || 0;
    }

    function setDiamondsCollected(n) {
        state.diamondsCollected = n || 0;
    }

    function recordLinkClickAttempt() {
        state.internalLinkClicksAttempted += 1;
    }

    function recordInternalLinkSuccess() {
        state.internalLinkClicksSuccess += 1;
    }

    function setScrollTaskTargetPercent(p) {
        state.targetScrollPercent = p;
    }

    function setMaxScrollPercentSeen(p) {
        if (typeof p === 'number' && p > state.maxScrollPercentSeen) {
            state.maxScrollPercentSeen = p;
        }
    }

    function getState() {
        return { ...state };
    }

    window.MySeoTask.TaskTelemetry = {
        reset,
        onAnyInteraction,
        onScroll,

        setDiamondsTotal,
        setDiamondsCollected,

        recordLinkClickAttempt,
        recordInternalLinkSuccess,

        setScrollTaskTargetPercent,
        setMaxScrollPercentSeen,

        getState,
        getDocMetrics,
    };
})();