(function () {
    if (!window.MySeoTask) {
        window.MySeoTask = {};
    }

    const SessionManager = window.MySeoTask.SessionManager;

    let currentProgress = 0;
    let isInitialized = false;

    let containerEl = null;
    let barInnerEl = null;
    let labelPercentEl = null;

    function handleRefreshTasks() {
        const TFM = window.MySeoTask.TaskFlowManager;
        // Xóa lưu tiến độ cũ
        if (SessionManager && typeof SessionManager.setStoredProgress === 'function') {
            SessionManager.setStoredProgress(0);
        }
        try { localStorage.removeItem('MySeoTask_CurrentFlow'); } catch (e) {}
        try { sessionStorage.removeItem('MySeoTask_TOTP_Popup_Config'); } catch (e) {}

        if (TFM && typeof TFM.clearFlow === 'function') {
            TFM.clearFlow();
        }
        if (TFM && typeof TFM.startNewFlow === 'function') {
            const pageType = window.MySeoTask.pageType || 'generic';
            TFM.startNewFlow(pageType);
        } else if (window.location && typeof window.location.reload === 'function') {
            // fallback: reload trang để khởi động lại nhiệm vụ
            window.location.reload();
        } else {
            setProgressInternal(0);
        }
    }

    function createProgressBar() {
        if (isInitialized) return;
        isInitialized = true;

        currentProgress = SessionManager ? SessionManager.getStoredProgress() : 0;

        // Tạo container
        containerEl = document.createElement('div');
        containerEl.className = 'my-seo-task-progress-container';

        // ==== HEADER ====
        const header = document.createElement('div');
        header.className = 'my-seo-task-progress-header';

        const titleWrap = document.createElement('div');
        titleWrap.className = 'my-seo-task-progress-title-wrap';

        const icon = document.createElement('div');
        icon.className = 'my-seo-task-progress-icon';
        icon.innerHTML = '<span>XP</span>';

        const titleTextWrap = document.createElement('div');

        const title = document.createElement('div');
        title.className = 'my-seo-task-progress-title';
        title.textContent = 'Nhiệm vụ SEO';

        const subtitle = document.createElement('div');
        subtitle.className = 'my-seo-task-progress-subtitle';
        subtitle.textContent = 'Hoàn thành hành động để nhận thưởng';

        titleTextWrap.appendChild(title);
        titleTextWrap.appendChild(subtitle);

        titleWrap.appendChild(icon);
        titleWrap.appendChild(titleTextWrap);

        labelPercentEl = document.createElement('span');
        labelPercentEl.className = 'my-seo-task-progress-percent-label';
        labelPercentEl.textContent = currentProgress + '%';

        header.appendChild(titleWrap);
        header.appendChild(labelPercentEl);

        // ==== BAR ====
        const barWrapper = document.createElement('div');
        barWrapper.className = 'my-seo-task-progress-bar-wrapper';

        barInnerEl = document.createElement('div');
        barInnerEl.className = 'my-seo-task-progress-bar-inner';
        barInnerEl.style.width = currentProgress + '%';

        barWrapper.appendChild(barInnerEl);

        // ==== FOOTER ====
        const footer = document.createElement('div');
        footer.className = 'my-seo-task-progress-footer';

        const footerLeft = document.createElement('div');
        footerLeft.className = 'my-seo-task-footer-left';
        footerLeft.textContent = ''; // bỏ Session / Tự động lưu để tránh rối mắt

        const footerRight = document.createElement('div');
        footerRight.className = 'my-seo-task-footer-right';

        const refreshBtn = document.createElement('button');
        refreshBtn.type = 'button';
        refreshBtn.className = 'my-seo-task-refresh-btn';
        refreshBtn.textContent = 'Làm mới nhiệm vụ';
        refreshBtn.addEventListener('click', handleRefreshTasks);

        footerRight.appendChild(refreshBtn);

        footer.appendChild(footerLeft);
        footer.appendChild(footerRight);

        // Lắp vào container
        containerEl.appendChild(header);
        containerEl.appendChild(barWrapper);
        containerEl.appendChild(footer);

        document.body.appendChild(containerEl);

        // Kích hoạt hiệu ứng "fade-in"
        requestAnimationFrame(function () {
            containerEl.classList.add('is-visible');
        });

        console.log('[MySeoTask] Progress bar created, initial progress:', currentProgress);
    }

    function setProgressInternal(percent) {
        const p = Math.max(0, Math.min(100, Math.round(percent)));
        currentProgress = p;

        if (barInnerEl) {
            barInnerEl.style.width = p + '%';
        }
        if (labelPercentEl) {
            labelPercentEl.textContent = p + '%';
        }

        if (SessionManager) {
            SessionManager.setStoredProgress(p);
        }
    }

    const ProgressAPI = {
        start: function () {
            if (!isInitialized) {
                createProgressBar();
            }
            setProgressInternal(0);
        },
        update: function (percent) {
            if (!isInitialized) {
                createProgressBar();
            }
            setProgressInternal(percent);
        },
        finish: function () {
            if (!isInitialized) {
                createProgressBar();
            }
            setProgressInternal(100);
        },
        getProgress: function () {
            return currentProgress;
        }
    };

    window.MySeoTask.Progress = ProgressAPI;
})();