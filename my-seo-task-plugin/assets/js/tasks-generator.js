(function () {
    if (!window.MySeoTask) {
        window.MySeoTask = {};
    }

    const TaskRegistry = window.MySeoTask.TaskRegistry;

    function currentPageType() {
        return (window.MySeoTask && window.MySeoTask.pageType) ? window.MySeoTask.pageType : 'generic';
    }

    function getConfig() {
        return window.__MYSEOTASK_CONFIG__ || {};
    }

    function getDisabledTaskIds() {
        const cfg = getConfig();
        return Array.isArray(cfg.disabledTaskIds) ? cfg.disabledTaskIds : [];
    }

    function getDocMetrics() {
        const doc = document.documentElement;
        const body = document.body;

        const viewportH = window.innerHeight || doc.clientHeight || 0;

        const scrollHeight = Math.max(
            body ? body.scrollHeight : 0,
            doc.scrollHeight,
            body ? body.offsetHeight : 0,
            doc.offsetHeight,
            body ? body.clientHeight : 0,
            doc.clientHeight
        );

        const maxScrollable = Math.max(scrollHeight - viewportH, 0);
        const pages = viewportH > 0 ? (scrollHeight / viewportH) : 1;

        return { viewportH, scrollHeight, maxScrollable, pages };
    }

    function isInNavigationLike(el) {
        return !!el.closest(
            'nav, header, footer, .menu, .navbar, .site-header, .site-footer, .mobile-menu, .offcanvas, .drawer, [role="navigation"]'
        );
    }

    function isElementVisible(el) {
        if (!el) return false;
        const style = window.getComputedStyle(el);
        if (style && (style.display === 'none' || style.visibility === 'hidden')) return false;
        const ariaHidden = el.closest('[aria-hidden="true"]');
        if (ariaHidden) return false;

        const rect = el.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return false;

        return true;
    }

    function getBestMainContentRoot() {
        const selectors = [
            'article .entry-content',
            'article .post-content',
            'article',
            'main .entry-content',
            'main',
            '.entry-content',
            '.post-content',
            '.page-content',
            '.content',
            '.woocommerce-product-details__short-description',
            '.woocommerce-Tabs-panel',
            '.product'
        ];

        for (let i = 0; i < selectors.length; i++) {
            const el = document.querySelector(selectors[i]);
            if (!el) continue;

            const h = Math.max(el.scrollHeight || 0, el.offsetHeight || 0);
            if (h < 160) continue;

            return el;
        }

        return null;
    }

    function getVisibleTextLength(el) {
        if (!el) return 0;
        const text = (el.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();
        return text.length;
    }

    function hasAdequateMainContent() {
        const pageType = currentPageType();
        const root = getBestMainContentRoot();
        if (!root) return false;

        // Nới lỏng cho category/search
        const minHeightPx = (pageType === 'category' || pageType === 'search') ? 240 : 380;
        const minTextLen  = (pageType === 'category' || pageType === 'search') ? 80  : 260;

        const h = Math.max(root.scrollHeight || 0, root.offsetHeight || 0);
        if (h < minHeightPx) return false;

        const textLen = getVisibleTextLength(root);
        if (textLen < minTextLen) return false;

        return true;
    }

    function hasEnoughPageLengthForDiamonds() {
        const pageType = currentPageType();
        const { maxScrollable, pages } = getDocMetrics();
        const minScrollablePx = (pageType === 'category' || pageType === 'search') ? 600 : 900;
        const minPages        = (pageType === 'category' || pageType === 'search') ? 1.3 : 1.9;
        return (maxScrollable >= minScrollablePx) || (pages >= minPages);
    }

    function hasEligibleContentImage() {
        const root = getBestMainContentRoot();
        if (!root) return false;

        const imgs = Array.from(root.querySelectorAll('img'))
            .filter((img) => img && img.tagName === 'IMG')
            .filter((img) => !isInNavigationLike(img))
            .filter((img) => isElementVisible(img))
            .filter((img) => {
                const rect = img.getBoundingClientRect();
                const w = rect.width || img.naturalWidth || 0;
                const h = rect.height || img.naturalHeight || 0;
                return w >= 120 && h >= 120;
            })
            .filter((img) => {
                const src = (img.currentSrc || img.src || '').trim();
                return src.length > 0;
            });

        return imgs.length > 0;
    }

    // Phân trang
    function hasPaginationLinks() {
        const selectors = [
            'nav.woocommerce-pagination',
            '.woocommerce-pagination',
            '.pagination',
            '.page-numbers',
            'nav.pagination',
            '.nav-links',
            '.page-nav',
            '.paging-navigation'
        ];
        let container = null;
        for (const sel of selectors) {
            container = document.querySelector(sel);
            if (container) break;
        }

        let links = [];
        if (container) links = Array.from(container.querySelectorAll('a[href], button'));
        else links = Array.from(document.querySelectorAll('a[href], button'));

        const candidates = links.filter((el) => {
            if (!isElementVisible(el)) return false;
            if (el.tagName === 'BUTTON') {
                const txt = (el.textContent || '').trim().toLowerCase();
                const aria = (el.getAttribute('aria-label') || '').toLowerCase();
                return /page|trang|next|prev|sau|trước/.test(txt) || /page|trang|next|prev/.test(aria);
            }
            const href = (el.getAttribute('href') || '').trim();
            if (!href || href === '#') return false;
            if (el.classList.contains('current') || el.classList.contains('disabled')) return false;
            if (el.getAttribute('aria-current') === 'page') return false;
            return true;
        });

        return candidates.length > 0;
    }

    // Add to cart (simple)
    function hasSimpleAddToCartButtons() {
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
        const filtered = candidates.filter((el) => {
            if (!isElementVisible(el)) return false;
            const type = (el.getAttribute('data-product_type') || el.getAttribute('data-product-type') || '').toLowerCase();
            const cls = (el.className || '').toLowerCase();
            if (type && type !== 'simple') return false;
            if (/product_type_variable|variable|grouped|external/.test(cls)) return false;
            if (el.closest('.product-type-variable, .product-type-grouped, .product-type-external')) return false;
            const pid = el.getAttribute('data-product_id') || el.getAttribute('data-product-id') || el.getAttribute('value');
            return !!pid;
        });
        return filtered.length > 0;
    }

    function isImageTaskType(task) {
        return task && (task.type === 'click_content_image' || task.type === 'click_content_image_find_diamond');
    }

    function getEligibleTasks(pageType) {
        const all = TaskRegistry.getTasksForPageType(pageType);
        if (!all || all.length === 0) return [];

        // Lọc theo disabledTaskIds từ config
        const disabled = new Set(getDisabledTaskIds());

        const contentOk = hasAdequateMainContent();

        const diamondsOk = contentOk && hasEnoughPageLengthForDiamonds();
        const imageOk = contentOk && hasEligibleContentImage();
        const paginationOk = hasPaginationLinks();
        const addToCartOk = hasSimpleAddToCartButtons();

        return all.filter((task) => {
            if (!task) return false;

            // Disable theo config
            if (task.id && disabled.has(task.id)) return false;

            if (task.type === 'collect_diamond') return diamondsOk;
            if (isImageTaskType(task)) return imageOk;
            if (task.type === 'click_pagination') return paginationOk;
            if (task.type === 'click_add_to_cart') return addToCartOk;

            return true;
        });
    }

    // NEW: never allow 2 image-click tasks in the same flow
    function pickRandomTasks(pageType, count) {
        const eligible = getEligibleTasks(pageType);
        if (!eligible || eligible.length === 0) return [];

        const pool = eligible.slice();
        const result = [];
        let pickedImageTask = false;

        while (result.length < count && pool.length > 0) {
            const idx = Math.floor(Math.random() * pool.length);
            const picked = pool.splice(idx, 1)[0];

            if (isImageTaskType(picked)) {
                if (pickedImageTask) continue;
                pickedImageTask = true;
            }

            result.push(picked);
        }
        return result;
    }

    function canStartFlow(pageType) {
        const eligible = getEligibleTasks(pageType);
        return eligible && eligible.length > 0;
    }

    function canContinueFlowOnThisPage() {
        const contentOk = hasAdequateMainContent();
        if (!contentOk) return false;

        const { maxScrollable, pages } = getDocMetrics();
        const pageType = currentPageType();
        const minScrollablePx = (pageType === 'category' || pageType === 'search') ? 420 : 520;
        const minPages = (pageType === 'category' || pageType === 'search') ? 1.15 : 1.35;
        return (maxScrollable >= minScrollablePx) || (pages >= minPages);
    }

    window.MySeoTask.TaskGenerator = {
        pickRandomTasks,
        getEligibleTasks,
        canStartFlow,
        canContinueFlowOnThisPage,
    };
})();