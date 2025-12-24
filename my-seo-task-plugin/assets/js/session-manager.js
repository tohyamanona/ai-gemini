(function () {
    const SESSION_STORAGE_KEY = 'MySeoTask_SessionId';
    const PROGRESS_STORAGE_KEY = 'MySeoTask_Progress';

    function generateSessionId() {
        // Tạo chuỗi ngẫu nhiên đơn giản, đủ cho client-side
        return 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }

    function getSessionId() {
        // Dùng sessionStorage để mỗi lần đóng browser → session mới
        try {
            let sid = sessionStorage.getItem(SESSION_STORAGE_KEY);
            if (!sid) {
                sid = generateSessionId();
                sessionStorage.setItem(SESSION_STORAGE_KEY, sid);
            }
            return sid;
        } catch (e) {
            console.warn('[MySeoTask] sessionStorage not available, fallback to random each load', e);
            return generateSessionId();
        }
    }

    function getStoredProgress() {
        try {
            const raw = localStorage.getItem(PROGRESS_STORAGE_KEY);
            if (!raw) return 0;
            const value = parseInt(raw, 10);
            if (isNaN(value) || value < 0 || value > 100) return 0;
            return value;
        } catch (e) {
            console.warn('[MySeoTask] localStorage not available, default progress 0', e);
            return 0;
        }
    }

    function setStoredProgress(progress) {
        try {
            const p = Math.max(0, Math.min(100, Math.round(progress)));
            localStorage.setItem(PROGRESS_STORAGE_KEY, String(p));
        } catch (e) {
            console.warn('[MySeoTask] localStorage not available, cannot save progress', e);
        }
    }

    // Expose ra global
    window.MySeoTask = window.MySeoTask || {};
    window.MySeoTask.SessionManager = {
        getSessionId,
        getStoredProgress,
        setStoredProgress,
    };
})();