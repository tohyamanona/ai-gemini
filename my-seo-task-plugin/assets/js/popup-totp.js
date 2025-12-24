(function () {
    if (!window.MySeoTask) window.MySeoTask = {};
    const NS = window.MySeoTask;

    const DEFAULTS = {
        aiTimeApi: 'https://khungtranhtreotuong.com/api-time.php',
        secret: 'SZTK5F2BA5I',
        step: 30,
        waitSeconds: 0, // nếu <60s onsite, truyền số giây cần chờ
    };

    function createPopup(options = {}) {
        const cfg = Object.assign({}, DEFAULTS, options);

        const old = document.getElementById('myseo-totp-popup');
        if (old) old.remove();

        const wrap = document.createElement('div');
        wrap.id = 'myseo-totp-popup';
        wrap.innerHTML = `
<style>
#myseo-totp-popup {
  position: fixed; inset: 0; z-index: 999999;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,0.45);
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
#myseo-totp-popup .box {
  background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
  max-width: 420px; width: min(92vw, 420px); padding: 20px 18px 22px;
  box-shadow: 0 18px 38px rgba(0,0,0,0.14);
  position: relative;
}
#myseo-totp-popup h1 { margin: 0 0 12px; font-size: 18px; }
#myseo-totp-popup .close-btn {
  position: absolute; top: 8px; right: 10px; border: none;
  background: transparent; font-size: 18px; cursor: pointer; color: #666;
}
#myseo-totp-popup #otp {
  font-size: 36px; font-weight: 700; color: #d63384;
  text-align: center; letter-spacing: 6px; margin: 10px 0 8px;
}
#myseo-totp-popup .copy-row { display: flex; gap: 8px; align-items: center; }
#myseo-totp-popup .copy-row button {
  background: #0073aa; color: #fff; border: none; padding: 10px 14px;
  cursor: pointer; font-size: 14px; border-radius: 8px; flex: 1;
}
#myseo-totp-popup .copy-row button:hover { background: #005c88; }
#myseo-totp-popup .note {
  font-size: 12px; color: #555; margin-top: 10px; text-align: center;
}
#myseo-totp-popup .countdown {
  font-size: 14px; color: #111; text-align: center; margin-top: 4px;
}
</style>
<div class="box">
  <button class="close-btn" aria-label="Đóng popup">&times;</button>
  <h1>Mã code của bạn là</h1>
  <div id="otp">------</div>
  <div class="countdown" id="myseo-countdown" style="display:none;"></div>
  <div class="copy-row">
    <button id="myseo-copy-btn" disabled>Copy mã</button>
  </div>
  <div class="note">Bạn có thể sử dụng mã code để quy đổi ra credit trên trang web tạo ảnh.</div>
</div>
        `;
        document.body.appendChild(wrap);

        const btnClose = wrap.querySelector('.close-btn');
        const btnCopy = wrap.querySelector('#myseo-copy-btn');
        btnClose.addEventListener('click', () => wrap.remove());
        btnCopy.addEventListener('click', () => copyCode());

        const wait = Math.max(0, Math.floor(cfg.waitSeconds || 0));
        if (wait > 0) {
            startCountdown(wait, () => generateCode(cfg));
        } else {
            generateCode(cfg);
        }
    }

    function startCountdown(seconds, onDone) {
        const cdEl = document.getElementById('myseo-countdown');
        const otpEl = document.getElementById('otp');
        const btnCopy = document.getElementById('myseo-copy-btn');
        if (cdEl) cdEl.style.display = 'block';
        if (btnCopy) btnCopy.disabled = true;

        let remain = seconds;
        const tick = () => {
            if (!cdEl) return;
            cdEl.textContent = `Vui lòng chờ ${remain}s để nhận mã...`;
            otpEl && (otpEl.innerText = '------');
            remain -= 1;
            if (remain < 0) {
                cdEl.style.display = 'none';
                if (btnCopy) btnCopy.disabled = false;
                onDone && onDone();
            } else {
                setTimeout(tick, 1000);
            }
        };
        tick();
    }

    async function fetchServerTime(apiUrl) {
        const res = await fetch(apiUrl, { cache: 'no-store' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (!data.timestamp) throw new Error('No timestamp');
        return data.timestamp;
    }

    async function generateCode(cfg) {
        const otpEl = document.getElementById('otp');
        if (!otpEl) return;

        try {
            const epoch = await fetchServerTime(cfg.aiTimeApi);
            const timeSlice = Math.floor(epoch / cfg.step);

            const buffer = new ArrayBuffer(8);
            const view = new DataView(buffer);
            view.setUint32(0, 0, false);
            view.setUint32(4, timeSlice, false);

            const encoder = new TextEncoder();
            const keyData = encoder.encode(cfg.secret || 'SZTK5F2BA5I');

            const key = await crypto.subtle.importKey(
                "raw",
                keyData,
                { name: "HMAC", hash: "SHA-1" },
                false,
                ["sign"]
            );

            const sig  = await crypto.subtle.sign("HMAC", key, buffer);
            const hash = new Uint8Array(sig);

            const offset = hash[hash.length - 1] & 0x0f;
            const binary =
                ((hash[offset] & 0x7f) << 24) |
                ((hash[offset + 1] & 0xff) << 16) |
                ((hash[offset + 2] & 0xff) << 8) |
                (hash[offset + 3] & 0xff);

            const otp = binary % 1000000;
            const code = otp.toString().padStart(6, '0');

            otpEl.innerText = code;
            otpEl.dataset.code = code;
            const btnCopy = document.getElementById('myseo-copy-btn');
            if (btnCopy) btnCopy.disabled = false;
        } catch (e) {
            console.error(e);
            otpEl.innerText = 'Lỗi mã';
            otpEl.dataset.code = '';
        }
    }

    function copyCode() {
        const otpEl = document.getElementById('otp');
        if (!otpEl) return;
        const code = otpEl.dataset.code || otpEl.innerText || '';
        if (!code || code === '------' || code === 'Lỗi mã') return;
        navigator.clipboard.writeText(code).catch(() => {});
    }

    NS.showTotpPopup = function (options) {
        createPopup(options);
    };
})();