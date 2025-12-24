(function () {
    if (!window.MySeoTask) {
        window.MySeoTask = {};
    }

    function getGateConfig() {
        const cfg = window.__MYSEOTASK_CONFIG__ || {};
        const ui = cfg.ui || {};
        return ui.gate || {};
    }
    const gateCfg = getGateConfig();

    const Telemetry = window.MySeoTask.TaskTelemetry || null;

    let overlayEl = null;
    let titleEl = null;
    let progressTextEl = null;
    let iconWrapEl = null;

    let exampleWrapEl = null;

    let currentTask = null;

    // ===== Gate for navigator (BALANCED) =====
    let gateTimer = null;
    let gateEnabled = false;

    // fallback listeners: ghi nhận click/touch/keydown cho Telemetry (nếu cần)
    let onAnyInteractionBound = null;

    // ===== Scroll navigator =====
    let navEl = null;
    let navArrowEl = null;
    let navHintEl = null;
    let navUpdateTimer = 0;

    function diamondSvgHtml(uniqueSuffix) {
        const suf = uniqueSuffix || 'ov';
        const gradA = `ov-d-grad-a-${suf}`;
        const gradB = `ov-d-grad-b-${suf}`;

        // Same visual as existing overlay diamond, but unique ids to avoid collisions
        return `
<svg class="my-seo-task-overlay-diamond-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="${gradA}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#e0f2fe"/>
      <stop offset="35%" stop-color="#60a5fa"/>
      <stop offset="70%" stop-color="#a855f7"/>
      <stop offset="100%" stop-color="#22c55e"/>
    </linearGradient>
    <linearGradient id="${gradB}" x1="1" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="rgba(255,255,255,0.95)"/>
      <stop offset="55%" stop-color="rgba(255,255,255,0.2)"/>
      <stop offset="100%" stop-color="rgba(255,255,255,0.0)"/>
    </linearGradient>
  </defs>

  <path class="d-outer" d="M32 2 L50 14 L62 32 L50 50 L32 62 L14 50 L2 32 L14 14 Z" fill="url(#${gradA})"/>
  <path d="M32 2 L50 14 L32 22 L14 14 Z" fill="rgba(255,255,255,0.35)"/>
  <path d="M14 14 L32 22 L10 32 L2 32 Z" fill="rgba(255,255,255,0.18)"/>
  <path d="M50 14 L62 32 L54 32 L32 22 Z" fill="rgba(255,255,255,0.20)"/>
  <path d="M10 32 L32 22 L32 62 L14 50 Z" fill="rgba(255,255,255,0.14)"/>
  <path d="M54 32 L50 50 L32 62 L32 22 Z" fill="rgba(255,255,255,0.16)"/>
  <path d="M18 16 C26 10, 34 10, 44 16 C36 18, 28 20, 18 16 Z" fill="url(#${gradB})"/>
</svg>
        `.trim();
    }

    function shouldShowDiamondInOverlayExample(task) {
        return !!task && task.type === 'click_content_image_find_diamond';
    }

    function getIconHtmlForTask(task) {
        if (!task) return '';
        if (task.type === 'collect_diamond') return diamondSvgHtml('icon');
        if (task.type === 'click_content_image_find_diamond') return diamondSvgHtml('icon-find');
        if (task.type === 'return_to_previous') {
            return `<span class="my-seo-task-overlay-default-icon" aria-hidden="true">↩</span>`;
        }
        return `<span class="my-seo-task-overlay-default-icon" aria-hidden="true">!</span>`;
    }

    function redArrowMiniSvgHtml() {
        return `
<svg class="my-seo-task-overlay-link-arrow-mini" viewBox="0 0 140 140" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="ov-arrow-red-grad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#ff2d55"/>
      <stop offset="55%" stop-color="#ef4444"/>
      <stop offset="100%" stop-color="#7f1d1d"/>
    </linearGradient>
    <filter id="ov-arrow-red-glow" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="5" result="blur"/>
      <feColorMatrix in="blur" type="matrix"
        values="1 0 0 0 0
                0 0 0 0 0
                0 0 0 0 0
                0 0 0 0.95 0" result="glow"/>
      <feMerge>
        <feMergeNode in="glow"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  </defs>
  <path filter="url(#ov-arrow-red-glow)"
        d="M122 20
           C96 26, 76 44, 66 66
           C56 88, 60 110, 64 122
           C48 114, 34 101, 24 88
           L12 100
           L12 50
           L62 50
           L42 70
           C50 88, 66 102, 86 108
           C82 94, 82 78, 88 66
           C98 44, 114 30, 132 26 Z"
        fill="url(#ov-arrow-red-grad)"
        stroke="rgba(255,255,255,0.98)"
        stroke-width="5"
        stroke-linejoin="round"/>
</svg>
        `.trim();
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildInternalLinkExampleHtml() {
        const guided = document.querySelector('a.my-seo-task-link-highlight');
        const text = guided ? (guided.textContent || '').trim() : '';
        const href = guided ? (guided.getAttribute('href') || '') : '';

        const safeText = (text && text.length > 0) ? text : 'Một link nội bộ (ví dụ)';
        const safeHref = (href && href.length > 0) ? href : window.location.origin + '/...';

        return `
<div class="my-seo-task-overlay-example-inner">
  <div class="my-seo-task-overlay-example-label">Gợi ý: hãy click link đang nhấp nháy như bên dưới</div>
  <div class="my-seo-task-overlay-example-row">
    <div class="my-seo-task-overlay-example-arrow">
      ${redArrowMiniSvgHtml()}
    </div>
    <div class="my-seo-task-overlay-example-link" title="${escapeHtml(safeHref)}">
      <span class="my-seo-task-overlay-example-link-icon">🔗</span>
      <span class="my-seo-task-overlay-example-link-text">${escapeHtml(safeText)}</span>
    </div>
  </div>
</div>
        `.trim();
    }

    function buildContentImageExampleHtml(task) {
        const img = document.querySelector('img.my-seo-task-image-highlight');
        const alt = img ? (img.alt || '') : '';
        const safeAlt = (alt && alt.trim()) ? alt.trim() : 'Ảnh trong nội dung';

        const extra = shouldShowDiamondInOverlayExample(task)
            ? `
  <div class="my-seo-task-overlay-example-label" style="margin-top:8px;display:flex;align-items:center;gap:8px;">
    <span style="display:inline-flex;width:22px;height:22px;">${diamondSvgHtml('ex')}</span>
    <span>Trong ảnh sẽ có <b>kim cương</b> — hãy phóng to để tìm và click vào nó</span>
  </div>
            `.trim()
            : '';

        return `
<div class="my-seo-task-overlay-example-inner">
  <div class="my-seo-task-overlay-example-label">Gợi ý: hãy click ảnh đang nhấp nháy trên trang</div>
  <div class="my-seo-task-overlay-example-row">
    <div class="my-seo-task-overlay-example-arrow">
      ${redArrowMiniSvgHtml()}
    </div>
    <div class="my-seo-task-overlay-example-link" title="${escapeHtml(safeAlt)}">
      <span class="my-seo-task-overlay-example-link-icon">🖼️</span>
      <span class="my-seo-task-overlay-example-link-text">${escapeHtml(safeAlt)}</span>
    </div>
  </div>
  ${extra}
</div>
        `.trim();
    }

    function buildReturnToPreviousHtml(task) {
        const originUrl = task && task.config ? (task.config.originUrl || '') : '';
        const safeOrigin = originUrl ? escapeHtml(originUrl) : '';

        return `
<div class="my-seo-task-overlay-example-inner">
  <div class="my-seo-task-overlay-example-label">
    Trang hiện tại không đủ nội dung để tiếp tục nhiệm vụ (để tránh thao tác không tự nhiên).
    Hãy quay lại trang trước để tiếp tục.
  </div>

  <div style="margin-top:10px;display:flex;justify-content:flex-end;">
    <button type="button"
      class="myseo-return-btn"
      data-origin="${safeOrigin}"
      style="
        pointer-events:auto;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:10px 12px;
        border-radius:999px;
        border:1px solid rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.10);
        color:#fff;
        font-weight:800;
        cursor:pointer;">
      Quay lại trang trước
    </button>
  </div>
</div>
        `.trim();
    }

    function createOverlayIfNeeded() {
        if (overlayEl) return;

        overlayEl = document.createElement('div');
        overlayEl.className = 'my-seo-task-overlay';

        const box = document.createElement('div');
        box.className = 'my-seo-task-overlay-box';

        progressTextEl = document.createElement('div');
        progressTextEl.className = 'my-seo-task-overlay-progress-text';

        const headerRow = document.createElement('div');
        headerRow.className = 'my-seo-task-overlay-header-row';

        iconWrapEl = document.createElement('div');
        iconWrapEl.className = 'my-seo-task-overlay-icon';

        titleEl = document.createElement('div');
        titleEl.className = 'my-seo-task-overlay-title';

        headerRow.appendChild(iconWrapEl);
        headerRow.appendChild(titleEl);

        exampleWrapEl = document.createElement('div');
        exampleWrapEl.className = 'my-seo-task-overlay-example';
        exampleWrapEl.style.display = 'none';

        box.appendChild(progressTextEl);
        box.appendChild(headerRow);
        box.appendChild(exampleWrapEl);

        overlayEl.appendChild(box);
        document.body.appendChild(overlayEl);

        overlayEl.addEventListener('click', function (e) {
            const btn = e.target && e.target.closest ? e.target.closest('button.myseo-return-btn') : null;
            if (!btn) return;

            e.preventDefault();
            e.stopPropagation();

            const origin = btn.getAttribute('data-origin') || '';

            try {
                if (window.history && typeof window.history.back === 'function') {
                    window.history.back();
                    return;
                }
            } catch (_) { }

            if (origin) {
                window.location.href = origin;
            }
        }, true);
    }

    // =========================
    // Gate logic (BALANCED)
    // =========================
    function getDocMetrics() {
        if (Telemetry && typeof Telemetry.getDocMetrics === 'function') return Telemetry.getDocMetrics();

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

    function computeBaseGate(taskType) {
        const { scrollHeight, viewportH } = getDocMetrics();
        const pages = viewportH > 0 ? (scrollHeight / viewportH) : 1;

        const cfgMinSeconds       = typeof gateCfg.min_seconds === 'number' ? gateCfg.min_seconds : null;
        const cfgMinInteractions  = typeof gateCfg.min_interactions === 'number' ? gateCfg.min_interactions : null;
        const cfgMinScrollPx      = typeof gateCfg.min_scroll_px === 'number' ? gateCfg.min_scroll_px : null;
        const cfgMinDepthPercent  = typeof gateCfg.min_depth_percent === 'number' ? gateCfg.min_depth_percent : null;
        const cfgMinPauseMs       = typeof gateCfg.min_pause_ms === 'number' ? gateCfg.min_pause_ms : null;

        let baseSec;
        let minInteractions;
        let minDepthPercent;

        switch (taskType) {
            case 'collect_diamond':
                baseSec = cfgMinSeconds ?? 24;
                minInteractions = cfgMinInteractions ?? 8;
                minDepthPercent = cfgMinDepthPercent ?? 16;
                break;
            case 'click_internal_link':
            case 'click_content_image':
            case 'click_content_image_find_diamond':
                baseSec = cfgMinSeconds ?? 18;
                minInteractions = cfgMinInteractions ?? 6;
                minDepthPercent = cfgMinDepthPercent ?? 11;
                break;
            case 'scroll_to_percent':
                baseSec = cfgMinSeconds ?? 14;
                minInteractions = cfgMinInteractions ?? 5;
                minDepthPercent = cfgMinDepthPercent ?? 9;
                break;
            default:
                baseSec = cfgMinSeconds ?? 18;
                minInteractions = cfgMinInteractions ?? 6;
                minDepthPercent = cfgMinDepthPercent ?? 11;
                break;
        }

        const lengthFactor = Math.min(2.2, Math.max(0, (pages - 1.2) * 0.55));
        let gateSec = baseSec * (1 + lengthFactor * 0.35);
        gateSec = Math.max(12, Math.min(60, gateSec));

        const minScrollPx = cfgMinScrollPx ?? Math.round(Math.min(1500, Math.max(420, viewportH * (0.95 + lengthFactor * 0.55))));
        const minPauseMs = cfgMinPauseMs ?? Math.round(Math.min(10000, Math.max(2600, 2600 + lengthFactor * 1200)));

        return {
            baseGateMs: Math.round(gateSec * 1000),
            minInteractions,
            minScrollPx,
            minDepthPercent,
            minPauseMs,
        };
    }

    function computeEffectiveGateMs(task, baseGateMs) {
        const reduceFactor = 0.6;
        const minGateMs = Math.max(9000, Math.round(baseGateMs * reduceFactor));

        if (!Telemetry || typeof Telemetry.getState !== 'function') return baseGateMs;
        const s = Telemetry.getState();
        if (!task) return baseGateMs;

        if (task.type === 'collect_diamond') {
            if (s.maxDepthPercent >= 40 && s.diamondsCollected === 0) return minGateMs;
        }

        if (task.type === 'click_internal_link') {
            if (s.internalLinkClicksAttempted >= 3 && s.internalLinkClicksSuccess === 0) return minGateMs;
        }

        if (task.type === 'scroll_to_percent') {
            if (typeof s.targetScrollPercent === 'number' && s.targetScrollPercent > 0) {
                if (s.maxScrollPercentSeen >= (s.targetScrollPercent - 4)) return minGateMs;
            }
        }

        return baseGateMs;
    }

    function setNavGateEnabled(isEnabled) {
        if (gateCfg.enabled === false) {
            gateEnabled = false;
            setNavVisible(false);
            return;
        }
        gateEnabled = !!isEnabled;
        if (!gateEnabled) setNavVisible(false);
    }

    function cleanupGate() {
        if (gateTimer) {
            clearInterval(gateTimer);
            gateTimer = null;
        }

        if (onAnyInteractionBound) {
            window.removeEventListener('click', onAnyInteractionBound, true);
            window.removeEventListener('touchstart', onAnyInteractionBound, { passive: true });
            window.removeEventListener('keydown', onAnyInteractionBound, true);
            onAnyInteractionBound = null;
        }

        setNavGateEnabled(false);
    }

    function startGate(task) {
        cleanupGate();
        setNavGateEnabled(false);

        if (task && task.type === 'return_to_previous') return;

        const base = computeBaseGate(task.type);

        onAnyInteractionBound = function () {
            if (Telemetry && typeof Telemetry.onAnyInteraction === 'function') {
                Telemetry.onAnyInteraction();
            }
        };
        window.addEventListener('click', onAnyInteractionBound, true);
        window.addEventListener('touchstart', onAnyInteractionBound, { passive: true });
        window.addEventListener('keydown', onAnyInteractionBound, true);

        gateTimer = setInterval(function () {
            if (!currentTask) return;

            if (currentTask.type === 'return_to_previous') {
                setNavGateEnabled(false);
                return;
            }

            const s = Telemetry && typeof Telemetry.getState === 'function' ? Telemetry.getState() : null;
            if (!s) return;

            const now = Date.now();
            const elapsed = now - (s.startedAt || now);

            const effectiveGateMs = computeEffectiveGateMs(currentTask, base.baseGateMs);

            const enoughTime = elapsed >= effectiveGateMs;
            const enoughScroll = (s.maxScrollDistance || 0) >= base.minScrollPx;
            const enoughDepth = (s.maxDepthPercent || 0) >= base.minDepthPercent;

            const enoughInteractions = (s.interactions || 0) >= base.minInteractions;
            const enoughPauseOptional = (s.pauseMs || 0) >= base.minPauseMs;

            const allow = enoughTime && enoughScroll && enoughDepth && (enoughInteractions || enoughPauseOptional);

            if (allow) {
                setNavGateEnabled(true);
            }
        }, 1000);
    }

    // =========================
    // Scroll navigator
    // =========================
    function ensureNav() {
        if (navEl) return;

        navEl = document.createElement('div');
        navEl.className = 'my-seo-task-scroll-nav';

        const inner = document.createElement('div');
        inner.className = 'my-seo-task-scroll-nav-inner';

        navArrowEl = document.createElement('div');
        navArrowEl.className = 'my-seo-task-scroll-nav-arrow';
        navArrowEl.textContent = '↓';

        navHintEl = document.createElement('div');
        navHintEl.className = 'my-seo-task-scroll-nav-hint';
        navHintEl.textContent = 'Cuộn để tới nhiệm vụ';

        inner.appendChild(navArrowEl);
        navEl.appendChild(inner);
        navEl.appendChild(navHintEl);

        document.body.appendChild(navEl);
    }

    function setNavVisible(isVisible) {
        ensureNav();
        if (!navEl) return;
        if (isVisible) navEl.classList.add('is-visible');
        else navEl.classList.remove('is-visible');
    }

    function setNavDirection(direction) {
        if (!navEl) return;
        navEl.classList.remove('is-up', 'is-down');
        if (direction === 'up') navEl.classList.add('is-up');
        if (direction === 'down') navEl.classList.add('is-down');
    }

    function getViewportCenterY() {
        const vh = window.innerHeight || document.documentElement.clientHeight || 0;
        return vh / 2;
    }

    function getTargetRectForTask(task) {
        if (!task) return null;
        if (task.type === 'return_to_previous') return null;

        if (task.type === 'click_internal_link') {
            const a = document.querySelector('a.my-seo-task-link-highlight');
            return a ? a.getBoundingClientRect() : null;
        }

        if (task.type === 'click_content_image' || task.type === 'click_content_image_find_diamond') {
            const img = document.querySelector('img.my-seo-task-image-highlight');
            return img ? img.getBoundingClientRect() : null;
        }

        if (task.type === 'collect_diamond') {
            const d = document.querySelector('.my-seo-task-diamond');
            return d ? d.getBoundingClientRect() : null;
        }

        if (task.type === 'scroll_to_percent') {
            const need = (task.config && task.config.percent) || 50;
            const m = getDocMetrics();
            const maxScrollable = m.maxScrollable || 0;
            const targetScrollY = (need / 100) * maxScrollable;

            const currentY = m.scrollY || 0;
            const delta = targetScrollY - currentY;

            return {
                top: delta > 0 ? (window.innerHeight || 0) + 200 : -200,
                bottom: delta > 0 ? (window.innerHeight || 0) + 260 : -140,
            };
        }

        return null;
    }

    function updateNavForTask(task) {
        if (!task || task.type === 'return_to_previous') {
            setNavVisible(false);
            return;
        }

        if (!gateEnabled) {
            setNavVisible(false);
            return;
        }

        const rect = getTargetRectForTask(task);
        if (!rect) {
            setNavVisible(false);
            return;
        }

        const centerY = getViewportCenterY();

        if (rect.top > centerY + 40) {
            navArrowEl.textContent = '↓';
            navHintEl.textContent = 'Cuộn xuống để tới nhiệm vụ';
            setNavDirection('down');
            setNavVisible(true);
            return;
        }

        if (rect.bottom < centerY - 40) {
            navArrowEl.textContent = '↑';
            navHintEl.textContent = 'Cuộn lên để tới nhiệm vụ';
            setNavDirection('up');
            setNavVisible(true);
            return;
        }

        setNavVisible(false);
    }

    function startNavLoop(task) {
        stopNavLoop();
        ensureNav();

        if (task && task.type === 'return_to_previous') {
            setNavVisible(false);
            return;
        }

        setTimeout(function () {
            updateNavForTask(task);
        }, 260);

        navUpdateTimer = window.setInterval(function () {
            updateNavForTask(currentTask);
        }, 350);

        window.addEventListener('scroll', onNavScroll, { passive: true });
        window.addEventListener('resize', onNavResize, { passive: true });
    }

    function stopNavLoop() {
        if (navUpdateTimer) {
            clearInterval(navUpdateTimer);
            navUpdateTimer = 0;
        }
        window.removeEventListener('scroll', onNavScroll);
        window.removeEventListener('resize', onNavResize);
        setNavVisible(false);
    }

    function onNavScroll() {
        updateNavForTask(currentTask);
    }

    function onNavResize() {
        updateNavForTask(currentTask);
    }

    // =========================
    // Example UI
    // =========================
    function updateExample(task) {
        if (!exampleWrapEl) return;

        exampleWrapEl.innerHTML = '';
        exampleWrapEl.style.display = 'none';

        if (!task) return;

        if (task.type === 'return_to_previous') {
            exampleWrapEl.innerHTML = buildReturnToPreviousHtml(task);
            exampleWrapEl.style.display = 'block';
            return;
        }

        if (task.type === 'collect_diamond') return;

        if (task.type === 'click_internal_link') {
            setTimeout(function () {
                if (!currentTask || currentTask.type !== 'click_internal_link') return;
                try {
                    exampleWrapEl.innerHTML = buildInternalLinkExampleHtml();
                    exampleWrapEl.style.display = 'block';
                } catch (e) {
                    console.error('[MySeoTask] buildInternalLinkExampleHtml error', e);
                }
            }, 220);
            return;
        }

        if (task.type === 'click_content_image' || task.type === 'click_content_image_find_diamond') {
            setTimeout(function () {
                if (!currentTask) return;
                if (currentTask.type !== 'click_content_image' && currentTask.type !== 'click_content_image_find_diamond') return;

                try {
                    exampleWrapEl.innerHTML = buildContentImageExampleHtml(currentTask);
                    exampleWrapEl.style.display = 'block';
                } catch (e) {
                    console.error('[MySeoTask] buildContentImageExampleHtml error', e);
                }
            }, 220);
            return;
        }
    }

    // =========================
    // Public API
    // =========================
    function showTask(task, index, total) {
        createOverlayIfNeeded();
        currentTask = task;

        progressTextEl.textContent = 'Nhiệm vụ ' + (index + 1) + '/' + total;
        titleEl.textContent = (task && task.title) ? task.title : 'Nhiệm vụ';

        try {
            iconWrapEl.innerHTML = getIconHtmlForTask(task);
        } catch (e) {
            console.error('[MySeoTask] getIconHtmlForTask error', e);
            iconWrapEl.innerHTML = '<span class="my-seo-task-overlay-default-icon" aria-hidden="true">!</span>';
        }

        updateExample(task);

        startGate(task);
        startNavLoop(task);

        overlayEl.classList.add('is-visible');
    }

    function hideOverlay() {
        if (!overlayEl) return;
        overlayEl.classList.remove('is-visible');

        if (exampleWrapEl) {
            exampleWrapEl.innerHTML = '';
            exampleWrapEl.style.display = 'none';
        }

        currentTask = null;
        stopNavLoop();
        cleanupGate();
    }

    window.MySeoTask.TaskOverlay = {
        showTask,
        hideOverlay,
    };
})();