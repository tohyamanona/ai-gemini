(function () {
    function getBodyClassString() {
        const body = document.body;
        if (!body || !body.className) return '';
        return String(body.className).toLowerCase();
    }

    function isCartPage() {
        const classes = getBodyClassString();
        if (classes.includes('woocommerce-cart')) return true;
        if (classes.includes('cart-page') || classes.includes('page-cart')) return true;
        if (document.querySelector('form.woocommerce-cart-form, table.shop_table.cart')) return true;
        const url = window.location.pathname.toLowerCase();
        if (url.includes('/cart') || url.includes('/gio-hang')) return true;
        return false;
    }

    function isCheckoutPage() {
        const classes = getBodyClassString();
        const url = window.location.pathname.toLowerCase();
        if (classes.includes('woocommerce-checkout')) return true;
        if (classes.includes('checkout-page') || classes.includes('page-checkout')) return true;
        if (document.querySelector('form.woocommerce-checkout')) return true;
        if (url.includes('/checkout') || url.includes('/thanh-toan')) return true;
        return false;
    }

    function isProductPage() {
        const classes = getBodyClassString();
        const url = window.location.pathname.toLowerCase();
        if (classes.includes('single-product')) return true;
        if (
            classes.includes('archive') ||
            classes.includes('category') ||
            classes.includes('product-category') ||
            classes.includes('tax-product_cat') ||
            classes.includes('search') ||
            classes.includes('search-results') ||
            classes.includes('woocommerce-cart') ||
            classes.includes('woocommerce-checkout')
        ) {
            return false;
        }
        const productElements = document.querySelectorAll('.product.type-product, .woocommerce div.product');
        if (productElements.length === 1) return true;
        if (url.includes('/san-pham/')) {
            const listProducts = document.querySelectorAll('.products .product');
            if (listProducts.length <= 1) return true;
        }
        return false;
    }

    function isCategoryPage() {
        const classes = getBodyClassString();
        const url = window.location.pathname.toLowerCase();

        // Nếu là single-product thì không phải category
        if (classes.includes('single-product')) return false;

        if (
            classes.includes('archive') ||
            classes.includes('category') ||
            classes.includes('product-category') ||
            classes.includes('tax-product_cat') ||
            classes.includes('blog') && classes.includes('archive') ||
            classes.includes('post-type-archive')
        ) {
            return true;
        }

        if (
            url.includes('/danh-muc') ||
            url.includes('/danh-muc-san-pham') ||
            url.includes('/category') ||
            url.includes('/chuyen-muc') ||
            url.includes('/chuyen-muc/') ||
            url.includes('/chuyen-muc-') ||
            (url.includes('/blog/') && !url.endsWith('/blog/'))
        ) {
            return true;
        }

        const loopItems = document.querySelectorAll(
            '.products .product, .post-list .post, .archive-posts .post, .blog-posts .post, .loop .post'
        );
        if (loopItems.length >= 4) return true;

        return false;
    }

    function isSearchPage() {
        const classes = getBodyClassString();
        const url = window.location.href.toLowerCase();
        if (classes.includes('search') || classes.includes('search-results')) return true;
        if (url.includes('?s=') || url.includes('/search/')) return true;
        const h1 = document.querySelector('h1, .page-title');
        if (h1) {
            const text = (h1.textContent || '').toLowerCase();
            if (text.includes('kết quả tìm kiếm') || text.includes('search results')) return true;
        }
        if (url.includes('ket-qua-tim-kiem')) return true;
        return false;
    }

    function isPostPage() {
        const classes = getBodyClassString();
        const url = window.location.pathname.toLowerCase();

        if (classes.includes('single-post') || classes.includes('single-format')) return true;

        if (
            classes.includes('single-product') ||
            classes.includes('archive') ||
            classes.includes('category') ||
            classes.includes('product-category') ||
            classes.includes('tax-product_cat') ||
            classes.includes('search') ||
            classes.includes('search-results')
        ) {
            return false;
        }

        if (document.querySelector('article.post, article.type-post')) return true;

        if (
            (url.includes('/tin-tuc/') || url.includes('/blog/')) &&
            !url.endsWith('/blog/') && !url.includes('/blog/page/')
        ) {
            return true;
        }

        return false;
    }

    function detectPageType() {
        try {
            // Thứ tự mới: cart -> checkout -> search -> product -> category -> post
            if (isCartPage()) return 'cart';
            if (isCheckoutPage()) return 'checkout';
            if (isSearchPage()) return 'search';
            if (isProductPage()) return 'product';
            if (isCategoryPage()) return 'category';
            if (isPostPage()) return 'post';
        } catch (e) {
            console.error('[MySeoTask] detectPageType error:', e);
        }
        return 'generic';
    }

    function onReady(fn) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(fn, 0);
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    onReady(function () {
        const pageType = detectPageType();
        console.log('[MySeoTask] Detected page type:', pageType);

        window.MySeoTask = window.MySeoTask || {};
        window.MySeoTask.pageType = pageType;
    });
})();