(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};
    const utils = M.utils;
    const state = M.state.get();
    const arrow = M.guidedArrow;

    function isGoodCandidateLink(a) {
        if (!a) return false;
        if (!utils.isRealNavigationalInternalLink(a)) return false;
        if (!utils.isElementVisible(a)) return false;
        return true;
    }

    function scoreLinkInContent(a) {
        const rect = a.getBoundingClientRect();
        const text = (a.textContent || '').trim();

        const viewportH = window.innerHeight || 0;
        const centerY = viewportH / 2;
        const distToCenter = Math.abs((rect.top + rect.bottom) / 2 - centerY);

        const hasText = text.length >= 3;
        const textScore = hasText ? Math.min(35, text.length) : -25;

        const mainBonus = 120;
        const penalty = utils.isInNavigationLike(a) ? 90 : 0;
        const viewportBonus = 90 - Math.min(85, distToCenter / 8);

        return viewportBonus + textScore + mainBonus - penalty;
    }

    function pickSuggestedInternalLink() {
        const anchorsAll = Array.from(document.querySelectorAll('a[href]')).filter(isGoodCandidateLink);
        if (anchorsAll.length === 0) return null;

        const inContentText = anchorsAll.filter(a =>
            utils.isInMainContent(a) &&
            !utils.isInNavigationLike(a) &&
            (a.textContent || '').trim().length >= 3
        );

        if (inContentText.length > 0) {
            const scored = inContentText
                .map(a => ({ a, s: scoreLinkInContent(a) }))
                .sort((x, y) => y.s - x.s);
            const topN = scored.slice(0, Math.min(8, scored.length));
            return utils.weightedPick(topN, (x) => x.s).a;
        }

        const inContent = anchorsAll.filter(a => utils.isInMainContent(a) && !utils.isInNavigationLike(a));
        if (inContent.length > 0) return inContent[0];

        return anchorsAll[0] || null;
    }

    // --- Pagination ---
    function isPaginationLinkCandidate(a) {
        if (!a) return false;
        if (!utils.isElementVisible(a)) return false;

        // Button: giữ logic cũ + aria-label
        if (a.tagName === 'BUTTON') {
            const txt = (a.textContent || '').trim().toLowerCase();
            const aria = (a.getAttribute('aria-label') || '').toLowerCase();
            if (/page|trang|next|prev|sau|trước/.test(txt) || /page|trang|next|prev|sau|trước/.test(aria)) return true;
            return false;
        }

        // Anchor
        const href = (a.getAttribute('href') || '').trim();
        if (!href || href === '#') return false;
        if (a.classList.contains('current') || a.classList.contains('disabled')) return false;
        if (a.getAttribute('aria-current') === 'page') return false;

        const rel   = (a.getAttribute('rel') || '').toLowerCase();
        const cls   = (a.className || '').toLowerCase();
        const textRaw = (a.textContent || '').replace(/\s+/g, ' ').trim();
        const text = textRaw.toLowerCase();
        const aria = (a.getAttribute('aria-label') || '').toLowerCase();

        const hasPageClass   = /(page-number|page-numbers|pagination|nav-pagination|paginate)/.test(cls);
        const isPrevNextCls  = /\bprev\b|\bnext\b/.test(cls);
        const isNumericText  = /^\d+$/.test(textRaw);

        if (rel === 'next' || rel === 'prev') return true;
        if (hasPageClass || isPrevNextCls) return true;
        if (isNumericText) return true;
        if (/page|trang|next|prev|sau|trước/.test(text)) return true;
        if (/page|trang|next|prev|sau|trước/.test(aria)) return true;

        return false;
    }

    function pickPaginationLink() {
        const containers = [
            'nav.woocommerce-pagination',
            '.woocommerce-pagination',
            '.pagination',
            '.page-numbers',
            'nav.pagination',
            '.nav-links',
            '.page-nav',
            '.paging-navigation',
            '.nav-pagination'
        ];
        let links = [];
        for (const sel of containers) {
            const c = document.querySelector(sel);
            if (c) {
                links = Array.from(c.querySelectorAll('a[href], button'));
                if (links.length) break;
            }
        }
        if (links.length === 0) {
            links = Array.from(document.querySelectorAll('a[href], button'));
        }

        const candidates = links.filter(isPaginationLinkCandidate);
        if (candidates.length === 0) return null;

        const scored = candidates.map((a, idx) => ({
            a,
            s: 100 - idx + ((a.getAttribute('rel') || '').toLowerCase() === 'next' ? 30 : 0),
        }));
        const topN = scored.slice(0, Math.min(6, scored.length));
        return utils.weightedPick(topN, (x) => x.s).a;
    }

    // --- Add to cart (simple) ---
    function isSimpleAddToCartButton(btn) {
        if (!btn) return false;
        if (!utils.isElementVisible(btn)) return false;

        const type = (btn.getAttribute('data-product_type') || btn.getAttribute('data-product-type') || '').toLowerCase();
        const cls = (btn.className || '').toLowerCase();
        if (type && type !== 'simple') return false;
        if (/product_type_variable|variable|grouped|external/.test(cls)) return false;
        if (btn.closest('.product-type-variable, .product-type-grouped, .product-type-external')) return false;

        const pid = btn.getAttribute('data-product_id') || btn.getAttribute('data-product-id') || btn.getAttribute('value');
        if (!pid) return false;

        return true;
    }

    function pickAddToCartButton() {
        const selectors = [
            '.add_to_cart_button',
            'a.product_type_simple',
            'button.product_type_simple',
            'button[name="add-to-cart"]',
            'form.cart button[type="submit"]',
        ];
        let candidates = [];
        for (const sel of selectors) {
            candidates = candidates.concat(Array.from(document.querySelectorAll(sel)));
        }
        const filtered = candidates.filter(isSimpleAddToCartButton);
        if (filtered.length === 0) return null;

        // Ưu tiên nút trong main content
        const inContent = filtered.filter(el => utils.isInMainContent(el));
        if (inContent.length > 0) return inContent[0];
        return filtered[0];
    }

    async function performAddToCart(btn) {
        const pid = btn.getAttribute('data-product_id') || btn.getAttribute('data-product-id') || btn.getAttribute('value');
        if (!pid) return false;

        // Chặn redirect bằng cách không theo href, dùng fetch add-to-cart
        const url = new URL(window.location.href);
        url.searchParams.set('add-to-cart', pid);

        try {
            const res = await fetch(url.toString(), { method: 'GET', credentials: 'same-origin' });
            return res.ok;
        } catch (e) {
            console.warn('[MySeoTask] add to cart failed', e);
            return false;
        }
    }

    function setupGuidedInternalLink() {
        arrow.clearGuidedHint();
        const suggested = pickSuggestedInternalLink();
        if (!suggested) return;
        suggested.classList.add('my-seo-task-link-highlight');
        arrow.attachArrow(suggested);
    }

    function setupGuidedPaginationLink() {
        arrow.clearGuidedHint();
        const suggested = pickPaginationLink();
        if (!suggested) return;
        suggested.classList.add('my-seo-task-link-highlight');
        arrow.attachArrow(suggested);
    }

    function setupGuidedAddToCartButton() {
        arrow.clearGuidedHint();
        const btn = pickAddToCartButton();
        if (!btn) return;
        btn.classList.add('my-seo-task-link-highlight');
        arrow.attachArrow(btn);
    }

    M.internalLink = {
        setupGuidedInternalLink,
        setupGuidedPaginationLink,
        setupGuidedAddToCartButton,
        isPaginationLinkCandidate,
        isSimpleAddToCartButton,
        performAddToCart,
    };
})();