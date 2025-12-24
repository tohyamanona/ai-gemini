(function () {
    if (!window.MySeoTask) {
        window.MySeoTask = {};
    }

    let diamondRoot = null;
    let hintTimeouts = new Map(); // id -> timeoutId

    function ensureRoot() {
        if (diamondRoot) return diamondRoot;
        diamondRoot = document.createElement('div');
        diamondRoot.className = 'my-seo-task-diamond-root';
        document.body.appendChild(diamondRoot);
        return diamondRoot;
    }

    function clearHintTimeout(id) {
        const t = hintTimeouts.get(id);
        if (t) clearTimeout(t);
        hintTimeouts.delete(id);
    }

    function removeDiamond(el, id) {
        if (!el) return;
        clearHintTimeout(id);
        if (el.parentNode) el.parentNode.removeChild(el);
    }

    function scheduleHint(el, id) {
        clearHintTimeout(id);
        const timeoutId = setTimeout(function () {
            if (el && el.parentNode) el.classList.add('show-hint');
        }, 2500);
        hintTimeouts.set(id, timeoutId);
    }

    // SVG facet kim cương (inline). ViewBox 0 0 64 64
    function buildDiamondSvg() {
        const wrap = document.createElement('div');
        wrap.className = 'my-seo-task-diamond-icon';

        wrap.innerHTML = `
<svg class="my-seo-task-diamond-svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="d-grad-a" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#e0f2fe"/>
      <stop offset="35%" stop-color="#60a5fa"/>
      <stop offset="70%" stop-color="#a855f7"/>
      <stop offset="100%" stop-color="#22c55e"/>
    </linearGradient>

    <linearGradient id="d-grad-b" x1="1" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="rgba(255,255,255,0.95)"/>
      <stop offset="55%" stop-color="rgba(255,255,255,0.2)"/>
      <stop offset="100%" stop-color="rgba(255,255,255,0.0)"/>
    </linearGradient>

    <filter id="d-soft-glow" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="2.2" result="blur"/>
      <feColorMatrix in="blur" type="matrix"
        values="1 0 0 0 0
                0 1 0 0 0
                0 0 1 0 0
                0 0 0 0.85 0" result="glow"/>
      <feMerge>
        <feMergeNode in="glow"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  </defs>

  <!-- Outer diamond -->
  <path class="d-outer" d="M32 2 L50 14 L62 32 L50 50 L32 62 L14 50 L2 32 L14 14 Z" fill="url(#d-grad-a)"/>
  <!-- Inner facets -->
  <path class="d-f1" d="M32 2 L50 14 L32 22 L14 14 Z" fill="rgba(255,255,255,0.35)"/>
  <path class="d-f2" d="M14 14 L32 22 L10 32 L2 32 Z" fill="rgba(255,255,255,0.18)"/>
  <path class="d-f3" d="M50 14 L62 32 L54 32 L32 22 Z" fill="rgba(255,255,255,0.20)"/>
  <path class="d-f4" d="M10 32 L32 22 L32 62 L14 50 Z" fill="rgba(255,255,255,0.14)"/>
  <path class="d-f5" d="M54 32 L50 50 L32 62 L32 22 Z" fill="rgba(255,255,255,0.16)"/>

  <!-- Specular highlight -->
  <path class="d-highlight" d="M18 16 C26 10, 34 10, 44 16 C36 18, 28 20, 18 16 Z" fill="url(#d-grad-b)"/>

  <!-- Sparkles (small stars) -->
  <g class="d-sparkles" fill="rgba(255,255,255,0.95)">
    <circle cx="22" cy="28" r="1.1"/>
    <circle cx="42" cy="26" r="0.9"/>
    <circle cx="38" cy="44" r="1.0"/>
    <circle cx="26" cy="46" r="0.8"/>
  </g>
</svg>
        `.trim();

        return wrap;
    }

    /**
     * Create a diamond at DOCUMENT coordinate (topPx/leftPx)
     */
    function createDiamondAtDocumentPosition(id, position, onClick) {
        ensureRoot();

        const diamond = document.createElement('div');
        diamond.className = 'my-seo-task-diamond';
        diamond.setAttribute('data-diamond-id', String(id));
        diamond.style.top = position.topPx + 'px';
        diamond.style.left = position.leftPx + 'px';

        const icon = buildDiamondSvg();

        const hint = document.createElement('div');
        hint.className = 'my-seo-task-diamond-hint';

        const hintArrow = document.createElement('div');
        hintArrow.className = 'my-seo-task-diamond-hint-arrow';

        const hintText = document.createElement('div');
        hintText.className = 'my-seo-task-diamond-hint-text';
        hintText.textContent = 'Nhấn để thu thập';

        hint.appendChild(hintArrow);
        hint.appendChild(hintText);

        diamond.appendChild(icon);
        diamond.appendChild(hint);

        diamond.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            if (typeof onClick === 'function') onClick();
        });

        diamondRoot.appendChild(diamond);

        requestAnimationFrame(function () {
            diamond.classList.add('is-visible');
        });

        scheduleHint(diamond, id);

        return diamond;
    }

    function clearAllDiamonds() {
        if (!diamondRoot) return;
        hintTimeouts.forEach((t) => clearTimeout(t));
        hintTimeouts.clear();
        diamondRoot.innerHTML = '';
    }

    window.MySeoTask.DiamondManager = {
        createDiamondAtDocumentPosition,
        removeDiamond,
        clearAllDiamonds,
    };
})();