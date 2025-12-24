(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};
    const utils = M.utils;
    const telemetry = M.telemetryBridge;
    const state = M.state.get();
    const arrow = M.guidedArrow;

    function isImageTaskType(task) {
        return !!task && (task.type === 'click_content_image' || task.type === 'click_content_image_find_diamond');
    }
    function taskIsFindDiamond(task) { return !!task && task.type === 'click_content_image_find_diamond'; }

    function isGoodCandidateImage(img) {
        if (!img || img.tagName !== 'IMG') return false;
        if (!utils.isElementVisible(img)) return false;
        if (!utils.isInMainContent(img)) return false;
        if (utils.isInNavigationLike(img)) return false;
        const rect = img.getBoundingClientRect();
        if (rect.width < 90 || rect.height < 90) return false;
        return true;
    }

    function scoreImage(img) {
        const rect = img.getBoundingClientRect();
        const viewportH = window.innerHeight || 0;
        const centerY = viewportH / 2;
        const distToCenter = Math.abs((rect.top + rect.bottom) / 2 - centerY);
        const area = Math.min(900000, Math.max(1, rect.width * rect.height));
        const areaScore = Math.log(area) * 20;
        const viewportBonus = 120 - Math.min(110, distToCenter / 6);
        return viewportBonus + areaScore;
    }

    function pickSuggestedContentImage() {
        const imgsAll = Array.from(document.querySelectorAll('img')).filter(isGoodCandidateImage);
        if (imgsAll.length === 0) return null;
        const scored = imgsAll.map(img => ({ img, s: scoreImage(img) })).sort((a, b) => b.s - a.s);
        const topN = scored.slice(0, Math.min(8, scored.length));
        return utils.weightedPick(topN, (x) => x.s).img;
    }

    function setupGuidedContentImage() {
        arrow.clearGuidedHint();
        const suggested = pickSuggestedContentImage();
        if (!suggested) return;
        suggested.classList.add('my-seo-task-image-highlight');
        arrow.attachArrow(suggested);
    }

    function makeDiamondSvgHtml(uniqueId) {
        const aId = `myseo-d-grad-a-${uniqueId}`;
        const bId = `myseo-d-grad-b-${uniqueId}`;
        return `
<svg class="my-seo-task-overlay-diamond-svg myseo-imgv-diamond-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="${aId}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#e0f2fe"/>
      <stop offset="35%" stop-color="#60a5fa"/>
      <stop offset="70%" stop-color="#a855f7"/>
      <stop offset="100%" stop-color="#22c55e"/>
    </linearGradient>
    <linearGradient id="${bId}" x1="1" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="rgba(255,255,255,0.95)"/>
      <stop offset="55%" stop-color="rgba(255,255,255,0.2)"/>
      <stop offset="100%" stop-color="rgba(255,255,255,0.0)"/>
    </linearGradient>
  </defs>
  <path class="d-outer" d="M32 2 L50 14 L62 32 L50 50 L32 62 L14 50 L2 32 L14 14 Z" fill="url(#${aId})"/>
  <path d="M32 2 L50 14 L32 22 L14 14 Z" fill="rgba(255,255,255,0.35)"/>
  <path d="M14 14 L32 22 L10 32 L2 32 Z" fill="rgba(255,255,255,0.18)"/>
  <path d="M50 14 L62 32 L54 32 L32 22 Z" fill="rgba(255,255,255,0.20)"/>
  <path d="M10 32 L32 22 L32 62 L14 50 Z" fill="rgba(255,255,255,0.14)"/>
  <path d="M54 32 L50 50 L32 62 L32 22 Z" fill="rgba(255,255,255,0.16)"/>
  <path d="M18 16 C26 10, 34 10, 44 16 C36 18, 28 20, 18 16 Z" fill="url(#${bId})"/>
</svg>
        `.trim();
    }

    function applyViewerTransform() {
        if (!state.viewerImgEl) return;
        const t = `translate(calc(-50% + ${state.viewerTranslate.x}px), calc(-50% + ${state.viewerTranslate.y}px)) scale(${state.viewerScale})`;
        state.viewerImgEl.style.transform = t;
        if (state.viewerTargetLayerEl) state.viewerTargetLayerEl.style.transform = t;
    }
    function resetViewerTransform() {
        state.viewerScale = 1;
        state.viewerTranslate = { x: 0, y: 0 };
        applyViewerTransform();
    }
    function lockScroll() { document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; }
    function unlockScroll() { document.documentElement.style.overflow = ''; document.body.style.overflow = ''; }
    function clearViewerSearchSquare() {
        if (state.viewerSearchSquareLabelEl) { state.viewerSearchSquareLabelEl.remove(); state.viewerSearchSquareLabelEl = null; }
        if (state.viewerSearchSquareEl) { state.viewerSearchSquareEl.remove(); state.viewerSearchSquareEl = null; }
    }
    function clearViewerMiniGame() {
        state.viewerDiamondFound = false;
        if (state.viewerHintTimer) { clearTimeout(state.viewerHintTimer); state.viewerHintTimer = 0; }
        if (state.viewerDiamondEl) { state.viewerDiamondEl.remove(); state.viewerDiamondEl = null; }
        if (state.viewerTargetLayerEl) { state.viewerTargetLayerEl.remove(); state.viewerTargetLayerEl = null; }
        clearViewerSearchSquare();
        state.viewerZoomSteps = 0;
        state.viewerRequiredZoomSteps = 0;
        state.viewerLastNotifiedStep = 0;
    }
    function closeViewer() {
        if (!state.viewerOverlayEl) return;
        clearViewerMiniGame();
        state.viewerOverlayEl.remove();
        state.viewerOverlayEl = null;
        state.viewerImgEl = null;
        state.viewerScale = 1;
        state.viewerTranslate = { x: 0, y: 0 };
        state.pointers = new Map();
        state.gesture = {
            pinching: false,
            startDist: 0,
            startScale: 1,
            startCenter: { x: 0, y: 0 },
            panning: false,
            panPointerId: null,
            panStart: { x: 0, y: 0 },
            panOrigin: { x: 0, y: 0 },
        };
        unlockScroll();
    }

    function ensureSearchSquare(stageEl) {
        if (!state.viewerImgEl || !stageEl) return;
        utils.ensureViewerHintStyles();
        clearViewerSearchSquare();

        const natW = state.viewerImgEl.naturalWidth || 1;
        const natH = state.viewerImgEl.naturalHeight || 1;
        const squareSize = Math.max(110, Math.min(180, Math.round(Math.min(natW, natH) * 0.12)));
        const dx = (state.viewerDiamondPos.xNorm - 0.5) * natW;
        const dy = (state.viewerDiamondPos.yNorm - 0.5) * natH;

        if (!state.viewerTargetLayerEl) {
            state.viewerTargetLayerEl = document.createElement('div');
            state.viewerTargetLayerEl.className = 'myseo-imgv-target-layer';
            state.viewerTargetLayerEl.style.pointerEvents = 'none';
            stageEl.appendChild(state.viewerTargetLayerEl);
        }

        state.viewerSearchSquareEl = document.createElement('div');
        state.viewerSearchSquareEl.className = 'myseo-imgv-search-square';
        Object.assign(state.viewerSearchSquareEl.style, {
            position: 'absolute',
            left: dx + 'px',
            top: dy + 'px',
            width: squareSize + 'px',
            height: squareSize + 'px',
            transform: 'translate(-50%, -50%)',
        });

        state.viewerSearchSquareLabelEl = document.createElement('div');
        state.viewerSearchSquareLabelEl.className = 'myseo-imgv-search-square-label';
        state.viewerSearchSquareLabelEl.textContent = 'Phóng to ảnh ở đây';
        Object.assign(state.viewerSearchSquareLabelEl.style, {
            position: 'absolute',
            left: dx + 'px',
            top: (dy - (squareSize / 2) - 14) + 'px',
            transform: 'translate(-50%, -100%)',
        });

        state.viewerTargetLayerEl.appendChild(state.viewerSearchSquareEl);
        state.viewerTargetLayerEl.appendChild(state.viewerSearchSquareLabelEl);
        applyViewerTransform();
    }

    function maybeAdvanceZoomGate(toolbarHintEl, stageEl, onComplete) {
        if (!taskIsFindDiamond(state.activeTask)) return;
        if (state.viewerDiamondEl) return;
        const thresholds = [1.35, 1.85];
        let steps = 0;
        if (state.viewerScale >= thresholds[0]) steps++;
        if (state.viewerScale >= thresholds[1]) steps++;
        state.viewerZoomSteps = steps;

        if (state.viewerZoomSteps >= state.viewerRequiredZoomSteps) {
            clearViewerSearchSquare();
            spawnDiamondOnViewer(stageEl, onComplete);
            if (toolbarHintEl) toolbarHintEl.textContent = 'Hãy tìm kim cương trên ảnh và click vào nó';
            return;
        }
        if (toolbarHintEl && state.viewerLastNotifiedStep !== state.viewerZoomSteps) {
            state.viewerLastNotifiedStep = state.viewerZoomSteps;
            const remain = Math.max(0, state.viewerRequiredZoomSteps - state.viewerZoomSteps);
            toolbarHintEl.textContent = 'Phóng to ảnh để tìm kim cương (' + remain + ' nhịp zoom nữa)';
        }
    }

    function spawnDiamondOnViewer(stageEl, onComplete) {
        if (!state.viewerImgEl || !stageEl) return;
        utils.ensureViewerHintStyles();

        if (state.viewerHintTimer) { clearTimeout(state.viewerHintTimer); state.viewerHintTimer = 0; }
        if (state.viewerDiamondEl) { state.viewerDiamondEl.remove(); state.viewerDiamondEl = null; }

        state.viewerDiamondFound = false;

        if (!state.viewerTargetLayerEl) {
            state.viewerTargetLayerEl = document.createElement('div');
            state.viewerTargetLayerEl.className = 'myseo-imgv-target-layer';
            state.viewerTargetLayerEl.style.pointerEvents = 'none';
            stageEl.appendChild(state.viewerTargetLayerEl);
        }

        const natW = state.viewerImgEl.naturalWidth || 1;
        const natH = state.viewerImgEl.naturalHeight || 1;
        const dx = (state.viewerDiamondPos.xNorm - 0.5) * natW;
        const dy = (state.viewerDiamondPos.yNorm - 0.5) * natH;

        state.viewerDiamondEl = document.createElement('button');
        state.viewerDiamondEl.type = 'button';
        state.viewerDiamondEl.className = 'myseo-imgv-diamond';
        Object.assign(state.viewerDiamondEl.style, {
            left: dx + 'px',
            top: dy + 'px',
            pointerEvents: 'auto',
            touchAction: 'manipulation',
            zIndex: '6',
            width: '64px',         // tăng kích thước kim cương trong viewer
            height: '64px',
            transform: 'translate(-50%, -50%)',
            background: 'transparent',
            border: 'none',
            padding: '0',
        });

        const uid = (Date.now().toString(36) + Math.random().toString(36).slice(2));
        state.viewerDiamondEl.innerHTML = makeDiamondSvgHtml(uid);

        state.viewerDiamondEl.addEventListener('pointerdown', function (e) {
            e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            telemetry.onAnyInteraction && telemetry.onAnyInteraction();
            if (state.viewerDiamondFound) return;
            state.viewerDiamondFound = true;
            clearViewerMiniGame();
            if (onComplete) onComplete();
        }, true);

        state.viewerTargetLayerEl.appendChild(state.viewerDiamondEl);
        applyViewerTransform();
    }

    function openViewerForImage(img, withDiamond, onComplete) {
        if (!img) return;
        const src = img.currentSrc || img.src || '';
        if (!src) return;

        closeViewer();
        state.viewerOverlayEl = document.createElement('div');
        state.viewerOverlayEl.className = 'myseo-imgv-overlay';

        const panel = document.createElement('div');
        panel.className = 'myseo-imgv-panel';

        const toolbar = document.createElement('div');
        toolbar.className = 'myseo-imgv-toolbar';

        const left = document.createElement('div');
        left.className = 'myseo-imgv-hint';
        left.textContent = withDiamond ? 'Phóng to ảnh để tìm kim cương' : 'Kéo để di chuyển • Pinch/scroll để zoom';

        const right = document.createElement('div');
        const mkBtn = (text) => { const b = document.createElement('button'); b.type='button'; b.className='myseo-imgv-btn'; b.textContent=text; return b; };
        const btnMinus = mkBtn('−');
        const btnPlus = mkBtn('+');
        const btnReset = mkBtn('Reset');
        const btnClose = mkBtn('Đóng');
        right.append(btnMinus, btnPlus, btnReset, btnClose);
        toolbar.append(left, right);

        const stage = document.createElement('div');
        stage.className = 'myseo-imgv-stage';
        stage.style.pointerEvents = 'auto';

        state.viewerImgEl = document.createElement('img');
        state.viewerImgEl.className = 'myseo-imgv-img';
        state.viewerImgEl.src = src;
        state.viewerImgEl.alt = img.alt || '';

        stage.appendChild(state.viewerImgEl);
        panel.append(toolbar, stage);
        state.viewerOverlayEl.appendChild(panel);
        document.body.appendChild(state.viewerOverlayEl);

        lockScroll();
        resetViewerTransform();

        if (withDiamond) {
            state.viewerDiamondPos = utils.pickDiamondPositionNorm();
            state.viewerRequiredZoomSteps = (Math.random() < 0.5) ? 1 : 2;
            state.viewerZoomSteps = 0;
            state.viewerLastNotifiedStep = 0;
            const onReady = function () {
                ensureSearchSquare(stage);
                maybeAdvanceZoomGate(left, stage, onComplete);
            };
            if (state.viewerImgEl.complete && state.viewerImgEl.naturalWidth > 0) onReady();
            else state.viewerImgEl.addEventListener('load', onReady, { once: true });
        }

        function applyZoomScale(mult) {
            state.viewerScale = utils.clampScale(state.viewerScale * mult);
            applyViewerTransform();
            if (withDiamond) maybeAdvanceZoomGate(left, stage, onComplete);
        }

        btnPlus.addEventListener('click', function () { applyZoomScale(1.22); });
        btnMinus.addEventListener('click', function () { applyZoomScale(1 / 1.22); });
        btnReset.addEventListener('click', function () {
            resetViewerTransform();
            if (withDiamond) maybeAdvanceZoomGate(left, stage, onComplete);
        });
        btnClose.addEventListener('click', function () { closeViewer(); });

        state.viewerOverlayEl.addEventListener('click', function (e) { if (e.target === state.viewerOverlayEl) closeViewer(); });

        window.addEventListener('keydown', function onKey(e) {
            if (!state.viewerOverlayEl) { window.removeEventListener('keydown', onKey, true); return; }
            if (e.key === 'Escape') closeViewer();
        }, true);

        stage.addEventListener('wheel', function (e) {
            e.preventDefault();
            const delta = e.deltaY || 0;
            const factor = delta > 0 ? (1 / 1.18) : 1.18;
            state.viewerScale = utils.clampScale(state.viewerScale * factor);
            applyViewerTransform();
            if (withDiamond) maybeAdvanceZoomGate(left, stage, onComplete);
        }, { passive: false });

        stage.addEventListener('pointerdown', function (e) {
            if (state.viewerDiamondEl && e.target && state.viewerDiamondEl.contains(e.target)) return;
            e.preventDefault();
            stage.setPointerCapture(e.pointerId);
            state.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

            if (state.pointers.size === 1) {
                state.gesture.panning = true;
                state.gesture.panPointerId = e.pointerId;
                state.gesture.panStart = { x: e.clientX, y: e.clientY };
                state.gesture.panOrigin = { x: state.viewerTranslate.x, y: state.viewerTranslate.y };
            } else if (state.pointers.size === 2) {
                const pts = Array.from(state.pointers.values());
                state.gesture.pinching = true;
                state.gesture.panning = false;
                state.gesture.panPointerId = null;
                state.gesture.startDist = utils.distance(pts[0], pts[1]);
                state.gesture.startScale = state.viewerScale;
                state.gesture.startCenter = utils.centerPoint(pts[0], pts[1]);
            }
        }, { passive: false });

        stage.addEventListener('pointermove', function (e) {
            if (!state.pointers.has(e.pointerId)) return;
            state.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

            if (state.gesture.pinching && state.pointers.size >= 2) {
                const pts = Array.from(state.pointers.values()).slice(0, 2);
                const distNow = utils.distance(pts[0], pts[1]);
                const centerNow = utils.centerPoint(pts[0], pts[1]);
                if (state.gesture.startDist > 0) {
                    const ratio = distNow / state.gesture.startDist;
                    state.viewerScale = utils.clampScale(state.gesture.startScale * ratio);
                }
                state.viewerTranslate.x += (centerNow.x - state.gesture.startCenter.x) * 0.35;
                state.viewerTranslate.y += (centerNow.y - state.gesture.startCenter.y) * 0.35;
                state.gesture.startCenter = centerNow;
                applyViewerTransform();
                if (withDiamond) maybeAdvanceZoomGate(left, stage, onComplete);
                return;
            }

            if (state.gesture.panning && state.gesture.panPointerId === e.pointerId) {
                state.viewerTranslate.x = state.gesture.panOrigin.x + (e.clientX - state.gesture.panStart.x);
                state.viewerTranslate.y = state.gesture.panOrigin.y + (e.clientY - state.gesture.panStart.y);
                applyViewerTransform();
            }
        }, { passive: true });

        function endPointer(e) {
            if (state.pointers.has(e.pointerId)) state.pointers.delete(e.pointerId);
            if (state.pointers.size < 2) state.gesture.pinching = false;
            if (state.pointers.size === 0) {
                state.gesture.panning = false;
                state.gesture.panPointerId = null;
            }
            if (state.pointers.size === 1) {
                const onlyId = Array.from(state.pointers.keys())[0];
                const onlyPt = Array.from(state.pointers.values())[0];
                state.gesture.panning = true;
                state.gesture.panPointerId = onlyId;
                state.gesture.panStart = { x: onlyPt.x, y: onlyPt.y };
                state.gesture.panOrigin = { x: state.viewerTranslate.x, y: state.viewerTranslate.y };
            }
        }
        stage.addEventListener('pointerup', endPointer, { passive: true });
        stage.addEventListener('pointercancel', endPointer, { passive: true });

        if (!withDiamond && onComplete) {
            onComplete();
        }
    }

    function shouldBlockThemeImageClickTarget(target, activeTask) {
        if (!activeTask || !isImageTaskType(activeTask)) return false;
        if (!target) return false;
        const img = target.closest ? target.closest('img') : null;
        if (!img) return false;
        return isGoodCandidateImage(img);
    }

    M.image = {
        isImageTaskType,
        taskIsFindDiamond,
        isGoodCandidateImage,
        setupGuidedContentImage,
        openViewerForImage,
        closeViewer,
        shouldBlockThemeImageClickTarget,
    };
})();