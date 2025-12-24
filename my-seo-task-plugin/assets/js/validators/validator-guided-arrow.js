(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};
    const state = NS.ValidatorModules.state.get();

    function ensureBigArrowAnimationStyle() {
        if (document.getElementById('myseo-big-arrow-style')) return;
        const style = document.createElement('style');
        style.id = 'myseo-big-arrow-style';
        style.textContent = `
@keyframes myseo_big_arrow_bob {
  0%, 100% { transform: translateY(0) scale(1); opacity: 1; }
  50%      { transform: translateY(-10px) scale(1.04); opacity: 1; }
}
        `.trim();
        document.head.appendChild(style);
    }

    function buildBigRedArrowEl() {
        const el = document.createElement('div');
        el.className = 'my-seo-task-link-arrow my-seo-task-link-arrow-absolute';
        el.innerHTML = `
<svg viewBox="0 0 140 140" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="myseo-big-red-grad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#ff2d55"/>
      <stop offset="55%" stop-color="#ef4444"/>
      <stop offset="100%" stop-color="#7f1d1d"/>
    </linearGradient>
    <filter id="myseo-red-glow" x="-50%" y="-50%" width="200%" height="200%">
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
  <path filter="url(#myseo-red-glow)"
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
        fill="url(#myseo-big-red-grad)"
        stroke="rgba(255,255,255,0.98)"
        stroke-width="5"
        stroke-linejoin="round"/>
</svg>
        `.trim();
        el.style.position = 'absolute';
        el.style.zIndex = '99998';
        el.style.width = '112px';
        el.style.height = '112px';
        el.style.pointerEvents = 'none';
        el.style.filter = 'drop-shadow(0 18px 34px rgba(15,23,42,0.45))';
        el.style.animation = 'myseo_big_arrow_bob 0.95s ease-in-out infinite';
        return el;
    }

    function updateArrowPositionAbsolute() {
        if (!state.guidedEl || !state.arrowEl) return;
        const rect = state.guidedEl.getBoundingClientRect();
        const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        const scrollX = window.pageXOffset || document.documentElement.scrollLeft || 0;
        const targetDocX = rect.left + scrollX + rect.width * 0.5;
        const targetDocY = rect.top + scrollY + rect.height * 0.5;
        const arrowLeft = targetDocX - 82;
        const arrowTop = targetDocY - 132;
        state.arrowEl.style.left = Math.round(arrowLeft) + 'px';
        state.arrowEl.style.top = Math.round(arrowTop) + 'px';
    }

    function startArrowLoop() {
        cancelArrowLoop();
        const tick = function () {
            updateArrowPositionAbsolute();
            state.arrowRaf = window.requestAnimationFrame(tick);
        };
        state.arrowRaf = window.requestAnimationFrame(tick);
    }

    function cancelArrowLoop() {
        if (state.arrowRaf) {
            window.cancelAnimationFrame(state.arrowRaf);
            state.arrowRaf = 0;
        }
    }

    function clearGuidedHint() {
        cancelArrowLoop();
        if (state.guidedEl) {
            state.guidedEl.classList.remove('my-seo-task-link-highlight');
            state.guidedEl.classList.remove('my-seo-task-image-highlight');
            state.guidedEl = null;
        }
        if (state.arrowEl && state.arrowEl.parentNode) state.arrowEl.parentNode.removeChild(state.arrowEl);
        state.arrowEl = null;
    }

    function attachArrow(guidedEl) {
        ensureBigArrowAnimationStyle();
        state.guidedEl = guidedEl;
        state.arrowEl = buildBigRedArrowEl();
        document.body.appendChild(state.arrowEl);
        updateArrowPositionAbsolute();
        startArrowLoop();
    }

    M.guidedArrow = {
        attachArrow,
        updateArrowPositionAbsolute,
        clearGuidedHint,
    };
})();