(function () {
    if (!window.MySeoTask) {
        window.MySeoTask = {};
    }

    function getConfig() {
        const cfg = window.__MYSEOTASK_CONFIG__ || {};
        const ui = cfg.ui || {};
        return ui.start_button || {};
    }

    const cfg = getConfig();

    const SessionManager = window.MySeoTask.SessionManager || null;
    const Progress = window.MySeoTask.Progress || null;
    const TaskFlowManager = window.MySeoTask.TaskFlowManager || null;
    const TaskGenerator = window.MySeoTask.TaskGenerator || null;

    const MISSION_FLAG_KEY = 'MySeoTask_IsInMissionMode';
    const BUTTON_SHOWN_KEY = 'MySeoTask_StartButtonShown';
    const BUTTON_SHOWN_COUNT_KEY = 'MySeoTask_StartButtonShownCount';

    const SCROLL_THRESHOLD = typeof cfg.scroll_threshold === 'number' ? cfg.scroll_threshold : 0.5;
    const DELAY_MS = typeof cfg.delay_ms === 'number' ? cfg.delay_ms : 1000;
    const ONLY_IF_ELIGIBLE = cfg.only_if_eligible !== false; // default true
    const MAX_SHOW_PER_SESSION = Number.isInteger(cfg.max_show_per_session) ? cfg.max_show_per_session : 3; // nới lên 3

    const BTN_TEXT = cfg.text && typeof cfg.text === 'string' ? cfg.text : 'Bắt đầu nhiệm vụ';
    const BTN_DOM_ID = cfg.dom_id && typeof cfg.dom_id === 'string' ? cfg.dom_id : '';

    let hasInserted = false;
    let hasClicked = false;

    function isInMissionMode() {
        try {
            const raw = localStorage.getItem(MISSION_FLAG_KEY);
            return raw === '1';
        } catch (e) {
            return false;
        }
    }

    function setMissionMode(value) {
        try {
            localStorage.setItem(MISSION_FLAG_KEY, value ? '1' : '0');
        } catch (e) { }
        window.MySeoTask.isInMissionMode = !!value;
    }

    function shownCountThisSession() {
        try {
            return parseInt(sessionStorage.getItem(BUTTON_SHOWN_COUNT_KEY) || '0', 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    function incrementShownCount() {
        try {
            const n = shownCountThisSession() + 1;
            sessionStorage.setItem(BUTTON_SHOWN_COUNT_KEY, String(n));
        } catch (e) { }
    }

    function hasShownButtonThisSession() {
        if (MAX_SHOW_PER_SESSION <= 0) return true;
        return shownCountThisSession() >= MAX_SHOW_PER_SESSION;
    }

    function randomAlignForMobile() {
        const aligns = ['flex-start', 'center', 'flex-end'];
        const idx = Math.floor(Math.random() * aligns.length);
        return aligns[idx];
    }

    function randomAlignForDesktop(inner) {
        const variants = [
            { justify: 'flex-start', maxWidth: '480px' },
            { justify: 'center', maxWidth: '480px' },
            { justify: 'flex-end', maxWidth: '480px' },
            { justify: 'flex-start', maxWidth: '640px' },
            { justify: 'flex-end', maxWidth: '640px' },
        ];
        const idx = Math.floor(Math.random() * variants.length);
        const v = variants[idx];

        inner.style.justifyContent = v.justify;
        inner.style.maxWidth = v.maxWidth;
    }

    function canShowStartButtonForThisPage() {
        const pageType = window.MySeoTask.pageType || 'generic';

        // Nếu TaskGenerator chưa load → giữ behavior cũ (vẫn show)
        if (!TaskGenerator || typeof TaskGenerator.canStartFlow !== 'function') return true;

        try {
            const eligible = !!TaskGenerator.canStartFlow(pageType);
            if (ONLY_IF_ELIGIBLE) return eligible;
            return true;
        } catch (e) {
            console.warn('[MySeoTask] canStartFlow check failed, fallback show button', e);
            return true;
        }
    }

    function createStartButtonElement() {
        const isMobile = window.innerWidth <= 768;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'my-seo-task-start-btn';
        if (BTN_DOM_ID) btn.id = BTN_DOM_ID;

        const icon = document.createElement('div');
        icon.className = 'my-seo-task-start-btn-icon';
        icon.textContent = 'GO';

        const text = document.createElement('span');
        text.className = 'my-seo-task-start-btn-text';
        text.textContent = BTN_TEXT;

        btn.appendChild(icon);
        btn.appendChild(text);

        btn.addEventListener('click', function () {
            if (hasClicked) return;
            hasClicked = true;

            setMissionMode(true);

            const pageType = window.MySeoTask.pageType || 'generic';

            if (TaskFlowManager && TaskFlowManager.startNewFlow) {
                TaskFlowManager.startNewFlow(pageType);
            } else if (Progress && Progress.start) {
                Progress.start();
            }

            btn.classList.remove('is-visible');
            setTimeout(function () {
                const root = btn.closest('.my-seo-task-start-btn-root') || btn.parentNode;
                if (root && root.parentNode) {
                    root.parentNode.removeChild(root);
                }
            }, 250);
        });

        requestAnimationFrame(function () {
            btn.classList.add('is-visible');
        });

        return btn;
    }

    function renderButtonAtFooter() {
        if (!canShowStartButtonForThisPage()) {
            console.log('[MySeoTask] Page not eligible for tasks, skip start button');
            return;
        }

        const root = document.createElement('div');
        root.className = 'my-seo-task-start-btn-root';

        const wrapper = document.createElement('div');
        wrapper.className = 'my-seo-task-start-btn-wrapper';

        const inner = document.createElement('div');
        inner.className = 'my-seo-task-start-btn-inner';

        if (window.innerWidth <= 768) {
            inner.style.justifyContent = randomAlignForMobile();
        } else {
            randomAlignForDesktop(inner);
        }

        const btn = createStartButtonElement();

        inner.appendChild(btn);
        wrapper.appendChild(inner);
        root.appendChild(wrapper);
        document.body.appendChild(root);
    }

    function createStartButton() {
        if (hasInserted) return;
        hasInserted = true;
        renderButtonAtFooter();
    }

    function initStartButtonLogic() {
        if (isInMissionMode()) {
            console.log('[MySeoTask] Mission mode already on, skip start button');

            if (Progress && Progress.update) {
                const saved = SessionManager ? SessionManager.getStoredProgress() : 0;
                if (saved > 0) Progress.update(saved);
                else Progress.start();
            }

            if (TaskFlowManager && TaskFlowManager.resumeFlowIfAny) {
                TaskFlowManager.resumeFlowIfAny();
            }

            return;
        }

        if (hasShownButtonThisSession()) {
            console.log('[MySeoTask] Start button already shown enough this session, skip');
            return;
        }

        let hasScrolledEnough = false;
        let delayTimeout = null;

        function triggerCreateWithDelay() {
            if (delayTimeout) return;
            delayTimeout = setTimeout(function () {
                incrementShownCount();
                createStartButton();
            }, Math.max(0, DELAY_MS));
        }

        function onScroll() {
            if (hasScrolledEnough) return;

            const scrollY = window.scrollY || window.pageYOffset || 0;
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

            if (scrollY > viewportHeight * SCROLL_THRESHOLD) {
                hasScrolledEnough = true;
                triggerCreateWithDelay();
                window.removeEventListener('scroll', onScroll);
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
    }

    function onReady(fn) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(fn, 0);
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    onReady(function () {
        initStartButtonLogic();
    });

    window.MySeoTask.StartButton = {
        create: createStartButton,
        isInMissionMode: isInMissionMode,
        setMissionMode: setMissionMode,
    };
})();