(function () {
    const NS = window.MySeoTask = window.MySeoTask || {};
    const M = NS.ValidatorModules = NS.ValidatorModules || {};

    const state = {
        activeTask: null,
        completeCallback: null,
        activeContext: null,

        totalDiamonds: 0,
        collectedDiamonds: 0,
        diamonds: [],

        guidedEl: null,
        arrowEl: null,
        arrowRaf: 0,

        viewerOverlayEl: null,
        viewerImgEl: null,
        viewerScale: 1,
        viewerTranslate: { x: 0, y: 0 },

        viewerTargetLayerEl: null,
        viewerDiamondEl: null,
        viewerDiamondFound: false,
        viewerDiamondPos: { xNorm: 0.58, yNorm: 0.42 },
        viewerHintTimer: 0,

        viewerZoomSteps: 0,
        viewerRequiredZoomSteps: 0,
        viewerLastNotifiedStep: 0,

        viewerSearchSquareEl: null,
        viewerSearchSquareLabelEl: null,

        pointers: new Map(),
        gesture: {
            pinching: false,
            startDist: 0,
            startScale: 1,
            startCenter: { x: 0, y: 0 },
            panning: false,
            panPointerId: null,
            panStart: { x: 0, y: 0 },
            panOrigin: { x: 0, y: 0 },
        },

        lastScrollY: 0,
        lastScrollTime: 0,
        hasShownFastScrollWarning: false,
    };

    function resetFastScroll() {
        state.hasShownFastScrollWarning = false;
        state.lastScrollY = window.pageYOffset || 0;
        state.lastScrollTime = performance.now ? performance.now() : Date.now();
    }

    function clearDiamondsState() {
        state.totalDiamonds = 0;
        state.collectedDiamonds = 0;
        state.diamonds = [];
    }

    function clearGuidedState() {
        state.guidedEl = null;
        state.arrowEl = null;
        state.arrowRaf = 0;
    }

    function clearViewerState() {
        state.viewerOverlayEl = null;
        state.viewerImgEl = null;
        state.viewerScale = 1;
        state.viewerTranslate = { x: 0, y: 0 };
        state.viewerTargetLayerEl = null;
        state.viewerDiamondEl = null;
        state.viewerDiamondFound = false;
        state.viewerDiamondPos = { xNorm: 0.58, yNorm: 0.42 };
        state.viewerHintTimer = 0;
        state.viewerZoomSteps = 0;
        state.viewerRequiredZoomSteps = 0;
        state.viewerLastNotifiedStep = 0;
        state.viewerSearchSquareEl = null;
        state.viewerSearchSquareLabelEl = null;
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
    }

    M.state = {
        get: () => state,
        resetFastScroll,
        clearDiamondsState,
        clearGuidedState,
        clearViewerState,
    };
})();