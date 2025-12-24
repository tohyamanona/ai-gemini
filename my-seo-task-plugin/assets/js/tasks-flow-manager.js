(function () {
    if (!window.MySeoTask) {
        window.MySeoTask = {};
    }

    const TaskGenerator = window.MySeoTask.TaskGenerator;
    const TaskOverlay = window.MySeoTask.TaskOverlay;
    const TaskValidator = window.MySeoTask.TaskValidator;
    const Progress = window.MySeoTask.Progress;
    const SessionManager = window.MySeoTask.SessionManager;

    const FLOW_STORAGE_KEY = 'MySeoTask_CurrentFlow';
    const TOTP_STORAGE_KEY = 'MySeoTask_TOTP_Popup_Config';

    let currentFlow = null;

    function getSessionId() {
        return SessionManager && typeof SessionManager.getSessionId === 'function'
            ? SessionManager.getSessionId()
            : null;
    }

    function getPageType() {
        return window.MySeoTask.pageType || 'generic';
    }

    function sendEvent(eventType, payload) {
        try {
            const body = Object.assign({}, payload || {}, { event_type: eventType });
            fetch('/wp-json/myseotask/v1/events', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            }).catch(() => {});
        } catch (e) {
            // silent
        }
    }

    function loadFlowFromStorage() {
        try {
            const raw = localStorage.getItem(FLOW_STORAGE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function saveFlowToStorage(flow) {
        try {
            localStorage.setItem(FLOW_STORAGE_KEY, JSON.stringify(flow));
        } catch (e) { }
    }

    function clearFlow() {
        currentFlow = null;
        try {
            localStorage.removeItem(FLOW_STORAGE_KEY);
        } catch (e) { }

        if (TaskOverlay) TaskOverlay.hideOverlay();
        if (TaskValidator) TaskValidator.clearValidation();
    }

    function showReturnToPreviousOverlay(originUrl) {
        if (!TaskOverlay || typeof TaskOverlay.showTask !== 'function') return;

        const task = {
            id: 'return_to_previous',
            type: 'return_to_previous',
            config: { originUrl: originUrl || '' },
            title: 'Quay lại trang trước để tiếp tục nhiệm vụ',
        };

        const idx = (currentFlow && typeof currentFlow.currentIndex === 'number') ? currentFlow.currentIndex : 0;
        const total = (currentFlow && typeof currentFlow.total === 'number') ? currentFlow.total : 3;

        TaskOverlay.showTask(task, idx, total);
    }

    function canContinueOnThisPage() {
        if (TaskGenerator && typeof TaskGenerator.canContinueFlowOnThisPage === 'function') {
            try {
                return !!TaskGenerator.canContinueFlowOnThisPage(window.MySeoTask.pageType || 'generic');
            } catch (e) {
                console.warn('[MySeoTask] canContinueFlowOnThisPage failed, fallback', e);
            }
        }

        if (TaskGenerator && typeof TaskGenerator.canStartFlow === 'function') {
            try {
                return !!TaskGenerator.canStartFlow(window.MySeoTask.pageType || 'generic');
            } catch (e) { }
        }

        return true;
    }

    function ensureFlowStartedAt(flow) {
        if (!flow) return;
        if (!flow.flowStartedAt || typeof flow.flowStartedAt !== 'number') {
            flow.flowStartedAt = Date.now();
        }
    }

    function startNewFlow(pageType) {
        const tasks = TaskGenerator && TaskGenerator.pickRandomTasks ? TaskGenerator.pickRandomTasks(pageType, 3) : [];
        if (!tasks || tasks.length === 0) {
            console.warn('[MySeoTask] No tasks available for pageType', pageType);
            return;
        }

        currentFlow = {
            tasks,
            currentIndex: 0,
            total: tasks.length,
            partialProgressForTask: 0,
            sessionId: getSessionId(),
            originUrl: window.location.href,
            flowStartedAt: Date.now(),
            taskStartedAt: Date.now(),
        };

        saveFlowToStorage(currentFlow);

        // Emit flow_start
        sendEvent('flow_start', {
            session_id: currentFlow.sessionId,
            page_type: getPageType(),
            task_id: null,
        });

        if (Progress && Progress.start) Progress.start();

        showCurrentTask();
    }

    function showCurrentTask() {
        if (!currentFlow || !TaskOverlay) return;

        if (!canContinueOnThisPage()) {
            // Ghi nhận fail do không đủ điều kiện trang hiện tại
            if (TaskValidator && typeof TaskValidator.clearValidation === 'function') {
                TaskValidator.clearValidation('return_to_previous');
            }
            showReturnToPreviousOverlay(currentFlow.originUrl || '');
            return;
        }

        ensureFlowStartedAt(currentFlow);

        const { tasks, currentIndex, total } = currentFlow;
        const task = tasks[currentIndex];
        if (!task) return;

        currentFlow.partialProgressForTask = 0;
        currentFlow.taskStartedAt = Date.now();
        saveFlowToStorage(currentFlow);

        // Emit task_show
        sendEvent('task_show', {
            session_id: currentFlow.sessionId,
            page_type: getPageType(),
            task_id: task.id || null,
        });

        const isLastTask = currentIndex === (total - 1);
        const timeOnSiteMs = Date.now() - (currentFlow.flowStartedAt || Date.now());

        if (TaskValidator && TaskValidator.startValidation) {
            TaskValidator.startValidation(task, function () {
                completeCurrentTask();
            }, {
                flowIndex: currentIndex,
                flowTotal: total,
                isLastTask,
                timeOnSiteMs,
            });
        }

        TaskOverlay.showTask(task, currentIndex, total);
    }

    function updatePartialProgressForCurrentTask(fraction) {
        if (!currentFlow || !Progress || !Progress.update) return;

        const { currentIndex, total } = currentFlow;

        const clamped = Math.max(0, Math.min(1, fraction || 0));
        const overallRatio = (currentIndex + clamped) / total;
        const percent = Math.round(overallRatio * 100);

        currentFlow.partialProgressForTask = clamped;
        saveFlowToStorage(currentFlow);

        Progress.update(percent);
    }

    // --- Popup helpers ---
    function scheduleTotpPopup(options) {
        try {
            sessionStorage.setItem(TOTP_STORAGE_KEY, JSON.stringify(options || {}));
        } catch (e) { }
    }

    function triggerTotpPopup(options) {
        if (window.MySeoTask._totpShown) return;
        if (window.MySeoTask && typeof window.MySeoTask.showTotpPopup === 'function') {
            window.MySeoTask._totpShown = true;
            try { sessionStorage.removeItem(TOTP_STORAGE_KEY); } catch (e) { }
            window.MySeoTask.showTotpPopup(options || {});
        }
    }

    function maybeShowTotpPopupFromStorage() {
        if (window.MySeoTask && window.MySeoTask._totpShown) return;
        let cfg = null;
        try {
            const raw = sessionStorage.getItem(TOTP_STORAGE_KEY);
            if (raw) cfg = JSON.parse(raw);
        } catch (e) { cfg = null; }
        if (cfg) {
            // đợi trang ổn định một chút rồi mới bật
            setTimeout(() => triggerTotpPopup(cfg), 400);
        }
    }

    function completeCurrentTask() {
        if (!currentFlow) return;

        if (TaskValidator) TaskValidator.clearValidation();

        const { currentIndex, total, tasks, flowStartedAt, taskStartedAt } = currentFlow;
        const task = tasks ? tasks[currentIndex] : null;
        const nextIndex = currentIndex + 1;

        const overallRatio = nextIndex / total;
        const percent = Math.round(overallRatio * 100);
        if (Progress && Progress.update) Progress.update(percent);

        // Emit task_complete
        const durationMs = taskStartedAt ? (Date.now() - taskStartedAt) : null;
        sendEvent('task_complete', {
            session_id: currentFlow.sessionId,
            page_type: getPageType(),
            task_id: task && task.id ? task.id : null,
            duration_ms: durationMs,
        });

        if (nextIndex >= total) {
            const timeOnSiteMs = flowStartedAt ? (Date.now() - flowStartedAt) : 0;
            const waitSeconds = Math.max(0, Math.ceil((60000 - timeOnSiteMs) / 1000));

            const popupCfg = {
                aiTimeApi: 'https://khungtranhtreotuong.com/api-time.php',
                secret: 'SZTK5F2BA5I',
                waitSeconds
            };

            const navTypes = [
                'click_internal_link',
                'click_pagination',
                'click_add_to_cart_allow_redirect',
                'click_place_order',
            ];
            const isNavTask = task && navTypes.includes(task.type);

            scheduleTotpPopup(popupCfg);

            if (!isNavTask) {
                triggerTotpPopup(popupCfg);
            }

            // Emit flow_complete
            sendEvent('flow_complete', {
                session_id: currentFlow.sessionId,
                page_type: getPageType(),
                task_id: task && task.id ? task.id : null,
                duration_ms: flowStartedAt ? (Date.now() - flowStartedAt) : null,
            });

            clearFlow();
            return;
        }

        currentFlow.currentIndex = nextIndex;
        currentFlow.partialProgressForTask = 0;
        currentFlow.taskStartedAt = Date.now();
        saveFlowToStorage(currentFlow);

        showCurrentTask();
    }

    function resumeFlowIfAny() {
        const stored = loadFlowFromStorage();
        if (!stored || !stored.tasks || stored.tasks.length === 0) {
            setTimeout(maybeShowTotpPopupFromStorage, 200);
            return;
        }

        currentFlow = stored;
        ensureFlowStartedAt(currentFlow);

        saveFlowToStorage(currentFlow);

        if (Progress && Progress.update) {
            const { currentIndex, total, partialProgressForTask } = currentFlow;
            const ratio = (currentIndex + (partialProgressForTask || 0)) / total;
            const percent = Math.round(ratio * 100);
            Progress.update(percent);
        }

        if (!canContinueOnThisPage()) {
            if (TaskValidator) TaskValidator.clearValidation('return_to_previous');
            showReturnToPreviousOverlay(currentFlow.originUrl || '');
            return;
        }

        showCurrentTask();
    }

    window.MySeoTask.TaskFlowManager = {
        startNewFlow,
        resumeFlowIfAny,
        clearFlow,
        updatePartialProgressForCurrentTask,
    };

    setTimeout(maybeShowTotpPopupFromStorage, 200);
})();