(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};

    // Chèn style hỗ trợ hint viewer (search square, blink)
    function ensureViewerHintStyles() {
        if (document.getElementById('myseo-imgv-hint-style')) return;
        const style = document.createElement('style');
        style.id = 'myseo-imgv-hint-style';
        style.textContent = `
@keyframes myseo_imgv_blink {
  0%, 100% { opacity: 1; }
  50% { opacity: .55; }
}
.myseo-imgv-search-square {
  pointer-events: none;
  border-radius: 9px;
  background: transparent;
  border: 6px solid rgba(255,255,255,0.98);
  box-shadow:
    inset 0 0 0 3px rgba(239,68,68,1),
    0 16px 34px rgba(0,0,0,0.45);
  animation: myseo_imgv_blink 0.95s ease-in-out infinite;
}
.myseo-imgv-search-square-label {
  pointer-events: none;
  white-space: nowrap;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  font-size: 12px;
  font-weight: 900;
  color: rgba(255,255,255,0.98);
  background: rgba(239,68,68,0.92);
  border: 3px solid rgba(255,255,255,0.98);
  box-shadow: inset 0 0 0 2px rgba(239,68,68,1), 0 16px 30px rgba(0,0,0,0.45);
  border-radius: 999px;
  padding: 6px 10px;
  animation: myseo_imgv_blink 0.95s ease-in-out infinite;
}
.myseo-imgv-diamond {
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
}
.myseo-imgv-diamond svg { display: block; width: 100%; height: 100%; }
        `.trim();
        document.head.appendChild(style);
    }

    function getDocumentMetrics() {
        const doc = document.documentElement;
        const body = document.body;
        const viewportHeight = window.innerHeight || doc.clientHeight || 0;
        const viewportWidth = window.innerWidth || doc.clientWidth || 0;
        const scrollY = window.pageYOffset || doc.scrollTop || 0;
        const scrollHeight = Math.max(
            body.scrollHeight, doc.scrollHeight,
            body.offsetHeight, doc.offsetHeight,
            body.clientHeight, doc.clientHeight
        );
        const maxScrollable = Math.max(scrollHeight - viewportHeight, 0);
        const depthPercent = maxScrollable > 0 ? (scrollY / maxScrollable) * 100 : 100;
        return { viewportHeight, viewportWidth, scrollHeight, maxScrollable, scrollY, depthPercent };
    }

    function getScrollPercent() {
        const { maxScrollable, scrollY } = getDocumentMetrics();
        if (maxScrollable <= 0) return 100;
        return (scrollY / maxScrollable) * 100;
    }

    function normalizeUrl(href) {
        try { return new URL(href, window.location.origin); } catch (_) { return null; }
    }

    function isRealNavigationalInternalLink(a) {
        if (!a) return false;
        const hrefAttr = a.getAttribute('href') || '';
        const href = hrefAttr.trim();
        if (!href || href === '#' || href.toLowerCase().startsWith('javascript:')) return false;
        const url = normalizeUrl(href);
        if (!url) return false;
        if (url.origin !== window.location.origin) return false;
        if (url.pathname === window.location.pathname && url.hash) return false;
        if (url.protocol === 'mailto:' || url.protocol === 'tel:') return false;
        return true;
    }

    function isElementVisible(el) {
        if (!el) return false;
        const style = window.getComputedStyle(el);
        if (style) {
            if (style.display === 'none' || style.visibility === 'hidden') return false;
        }
        const ariaHidden = el.closest('[aria-hidden="true"]');
        if (ariaHidden) return false;

        if (el.offsetParent === null) {
            const rect = el.getBoundingClientRect();
            const fixedVisible =
                style && style.position === 'fixed' &&
                rect.width > 10 && rect.height > 10 &&
                rect.bottom > 0 && rect.top < (window.innerHeight || 0);
            if (!fixedVisible) return false;
        }

        const rect = el.getBoundingClientRect();
        if (rect.width < 20 || rect.height < 10) return false;
        return true;
    }

    function isInMainContent(el) {
        return !!el.closest(
            'main, article, .entry-content, .post-content, .content, .woocommerce-product-details__short-description, .product, .single, .page-content'
        );
    }

    function isInNavigationLike(el) {
        return !!el.closest(
            'nav, header, footer, .menu, .navbar, .site-header, .site-footer, .mobile-menu, .offcanvas, .drawer, [role="navigation"]'
        );
    }

    function weightedPick(items, weightFn) {
        let total = 0;
        const weights = items.map((it) => {
            const w = Math.max(0.0001, Number(weightFn(it)) || 0.0001);
            total += w;
            return w;
        });
        let r = Math.random() * total;
        for (let i = 0; i < items.length; i++) {
            r -= weights[i];
            if (r <= 0) return items[i];
        }
        return items[items.length - 1] || null;
    }

    function clampScale(s) { return Math.max(0.6, Math.min(6, s)); }
    function distance(a, b) { const dx = a.x - b.x, dy = a.y - b.y; return Math.sqrt(dx*dx + dy*dy); }
    function centerPoint(a, b) { return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }; }

    // Chọn vị trí trong hộp trung tâm (tránh mép): 32%–68% cho cả X/Y
    function pickDiamondPositionNorm() {
        const min = 0.32;
        const max = 0.68;
        const x = min + Math.random() * (max - min);
        const y = min + Math.random() * (max - min);
        return { xNorm: x, yNorm: y };
    }

    M.utils = {
        ensureViewerHintStyles,
        getDocumentMetrics,
        getScrollPercent,
        normalizeUrl,
        isRealNavigationalInternalLink,
        isElementVisible,
        isInMainContent,
        isInNavigationLike,
        weightedPick,
        clampScale,
        distance,
        centerPoint,
        pickDiamondPositionNorm,
    };
})();