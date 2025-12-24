(function () {
    if (!window.MySeoTask) window.MySeoTask = {};

    const NS = window.MySeoTask;
    const M = NS.ValidatorModules = NS.ValidatorModules || {};
    const utils = M.utils;
    const state = M.state.get();
    const telemetry = M.telemetryBridge;
    const arrow = M.guidedArrow;
    const diamonds = M.diamonds;
    const internalLink = M.internalLink;
    const image = M.image;
    const scroll = M.scroll;

    // Đọc cấu hình Gate (chống cuộn nhanh)
    const cfg = window.__MYSEOTASK_CONFIG__ || {};
    const gateCfg = (cfg.ui && cfg.ui.gate) ? cfg.ui.gate : {};
    const ANTI_FAST_SCROLL_MODE = gateCfg.anti_fast_scroll_mode || 'alert';

    const DiamondManager = NS.DiamondManager || null;

    const MAX_SCROLL_SPEED_PX_PER_MS = 4;

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

    function getSessionId() {
        const SM = NS.SessionManager;
        return SM && typeof SM.getSessionId === 'function' ? SM.getSessionId() : null;
    }
    function getPageType() { return window.MySeoTask.pageType || 'generic'; }
    function getActiveTaskId() { return state.activeTask && state.activeTask.id ? state.activeTask.id : null; }

    function fireComplete() {
        if (!state.activeTask) return;
        const task = state.activeTask;
        state.activeTask = null;

        if (DiamondManager) DiamondManager.clearAllDiamonds();
        arrow.clearGuidedHint();

        const cb = state.completeCallback;
        state.completeCallback = null;
        if (typeof cb === 'function') cb(task);
    }

    function fireFail(reason) {
        if (!state.activeTask) return;
        const task = state.activeTask;

        sendEvent('task_fail', {
            session_id: getSessionId(),
            page_type: getPageType(),
            task_id: getActiveTaskId(),
            reason_fail: reason || 'unknown',
        });

        state.activeTask = null;
        if (DiamondManager) DiamondManager.clearAllDiamonds();
        arrow.clearGuidedHint();

        const cb = state.completeCallback;
        state.completeCallback = null;
        // không gọi cb, vì fail
    }

    function onScroll() {
        if (!state.activeTask) return;

        const now = performance.now ? performance.now() : Date.now();
        const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;

        if (state.lastScrollTime && state.lastScrollY != null) {
            const dy = Math.abs(scrollY - state.lastScrollY);
            const dt = now - state.lastScrollTime;
            if (dt > 0) {
                const speed = dy / dt;
                if (speed > MAX_SCROLL_SPEED_PX_PER_MS && !state.hasShownFastScrollWarning) {
                    state.hasShownFastScrollWarning = true;
                    if (ANTI_FAST_SCROLL_MODE === 'alert') {
                        alert('Bạn đang kéo quá nhanh, vui lòng kéo tự nhiên để tiếp tục nhiệm vụ.');
                    } else if (ANTI_FAST_SCROLL_MODE === 'strict') {
                        fireFail('fast_scroll');
                    } // mode "none": không làm gì
                }
            }
        }
        state.lastScrollY = scrollY;
        state.lastScrollTime = now;

        telemetry.onScroll && telemetry.onScroll();

        if (state.activeTask.type === 'scroll_to_percent') {
            scroll.checkScrollTask(state.activeTask, fireComplete);
        }
    }

    function onClick(e) {
        if (!state.activeTask) return;
        telemetry.onAnyInteraction && telemetry.onAnyInteraction();

        // Internal link
        if (state.activeTask.type === 'click_internal_link') {
            const a = e.target.closest('a');
            if (!a) return;
            if (!utils.isRealNavigationalInternalLink(a)) return;
            fireComplete();
            return;
        }

        // Pagination
        if (state.activeTask.type === 'click_pagination') {
            const a = e.target.closest('a, button');
            if (!a) return;
            if (!internalLink.isPaginationLinkCandidate(a)) return;
            fireComplete();
            return;
        }

        // Add to cart (simple)
        if (state.activeTask.type === 'click_add_to_cart') {
            const btn = e.target.closest('a, button');
            if (!btn) return;
            if (!internalLink.isSimpleAddToCartButton(btn)) return;

            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

            internalLink.performAddToCart(btn).then((ok) => {
                if (ok) fireComplete();
            });
            return;
        }

        // Image tasks
        if (image.isImageTaskType(state.activeTask)) {
            const imgEl = e.target.closest('img');
            if (!imgEl) return;
            if (!image.isGoodCandidateImage(imgEl)) return;

            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

            const withDiamond = image.taskIsFindDiamond(state.activeTask);
            image.openViewerForImage(imgEl, withDiamond, fireComplete);
        }
    }

    function onPointerDownCapture(e) {
        if (!image.shouldBlockThemeImageClickTarget(e.target, state.activeTask)) return;
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }

    function onResize() { arrow.updateArrowPositionAbsolute(); }

    function startValidation(task, onComplete, context) {
        state.activeTask = task || null;
        state.completeCallback = onComplete || null;
        state.activeContext = context || null;

        M.state.resetFastScroll();
        M.state.clearDiamondsState();
        M.state.clearGuidedState();

        // Reset cờ cảnh báo cuộn nhanh
        state.hasShownFastScrollWarning = false;
        state.lastScrollTime = null;
        state.lastScrollY = null;

        arrow.clearGuidedHint();
        if (!state.activeTask) return;

        telemetry.reset && telemetry.reset(state.activeTask);

        // Emit task_start
        sendEvent('task_start', {
            session_id: getSessionId(),
            page_type: getPageType(),
            task_id: getActiveTaskId(),
        });

        if (state.activeTask.type === 'collect_diamond') {
            state.totalDiamonds = diamonds.decideTotalDiamonds(state.activeTask);
            state.collectedDiamonds = 0;
            diamonds.updatePartialProgress();
            telemetry && telemetry.setDiamondsTotal && telemetry.setDiamondsTotal(state.totalDiamonds);
            diamonds.setupDiamonds(state.activeTask, fireComplete);
            return;
        }

        if (state.activeTask.type === 'scroll_to_percent') {
            telemetry && telemetry.setScrollTaskTargetPercent && telemetry.setScrollTaskTargetPercent((state.activeTask.config && state.activeTask.config.percent) || 50);
            setTimeout(() => scroll.checkScrollTask(state.activeTask, fireComplete), 50);
            return;
        }

        if (state.activeTask.type === 'click_internal_link') {
            setTimeout(function () {
                if (!state.activeTask || state.activeTask.type !== 'click_internal_link') return;
                internalLink.setupGuidedInternalLink();
            }, 120);
            return;
        }

        if (state.activeTask.type === 'click_pagination') {
            setTimeout(function () {
                if (!state.activeTask || state.activeTask.type !== 'click_pagination') return;
                internalLink.setupGuidedPaginationLink();
            }, 120);
            return;
        }

        if (state.activeTask.type === 'click_add_to_cart') {
            setTimeout(function () {
                if (!state.activeTask || state.activeTask.type !== 'click_add_to_cart') return;
                internalLink.setupGuidedAddToCartButton();
            }, 120);
            return;
        }

        if (image.isImageTaskType(state.activeTask)) {
            setTimeout(function () {
                if (!state.activeTask || !image.isImageTaskType(state.activeTask)) return;
                image.setupGuidedContentImage();
            }, 120);
            return;
        }
    }

    function clearValidation(reasonFail) {
        if (reasonFail && state.activeTask) {
            fireFail(reasonFail);
        }
        state.activeTask = null;
        state.completeCallback = null;
        state.activeContext = null;

        diamonds.clearDiamonds();
        arrow.clearGuidedHint();
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('click', onClick, true);
    window.addEventListener('pointerdown', onPointerDownCapture, true);
    window.addEventListener('resize', onResize, { passive: true });

    NS.TaskValidator = {
        startValidation,
        clearValidation,
    };
})();