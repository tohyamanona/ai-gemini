(function($) {
    'use strict';

    var AIGeminiGenerator = {
        config: window.AIGeminiConfig || {},
        currentImageData: null,
        currentImageId: null,
        currentImageSessionId: null,
        currentMission: null,
        pendingAction: null,

        storageKey: 'ai_gemini_current_image',

        init: function() {
            if ($('#selected-style-slug').length) {
                this.selectedStyle = $('#selected-style-slug').val();
            }
            this.bindEvents();
            this.initDropzone();
            this.restoreImageFromStorage();
        },
        
        bindEvents: function() {
            var self = this;

            $('#upload-area').on('click', function(e) {
                if (e.target.id !== 'image-input' && !$(e.target).closest('#remove-image').length) {
                    if (!$('#upload-preview').is(':visible')) $('#image-input').trigger('click');
                }
            });

            $('#image-input').on('change', function(e) {
                if (e.target.files.length) self.handleFileSelect(e.target.files[0]);
                $(this).val('');
            });

            $('#remove-image').on('click', function(e) { 
                e.preventDefault(); 
                e.stopPropagation(); 
                self.removeImage(true);
            });
            
            $('.style-option').on('click', function() {
                $('.style-option').removeClass('active');
                $(this).addClass('active');
                $('#selected-style-slug').val($(this).data('style'));
            });

            $('#btn-generate').on('click', function() { 
                self.pendingAction = 'generate'; 
                self.generatePreview(); 
            });
            
            $('#btn-unlock').on('click', function() { 
                self.pendingAction = 'unlock'; 
                self.unlockImage(); 
            });
            
            $('#btn-regenerate').on('click', function() { self.resetToForm(); });
            $('#btn-new').on('click', function() { self.resetAll(); });
            
            // Lỗi không phải credit
            $('#btn-retry-generic').on('click', function() { self.hideError(); });

            // Nút làm nhiệm vụ ở thanh trên
            $(document).on('click', '.btn-earn-free-bar', function(e) {
                e.preventDefault();
                self.pendingAction = null;
                self.startMission($(this));
            });

            // Nút làm nhiệm vụ trong box lỗi credit
            $(document).on('click', '.btn-earn-free-mission', function(e) {
                e.preventDefault();
                self.startMission($(this));
            });
            
            $(document).on('click', '#btn-verify-mission', function() {
                self.verifyMissionCode();
            });

            // Nút Nạp Credit trong box lỗi credit: redirect tới trang nạp
            $(document).on('click', '#btn-open-topup', function(e) {
                e.preventDefault();
                // Chuyển hướng thẳng tới trang nạp credit (đồng bộ với nút + Mua trên thanh trên)
                window.location.href = AIGeminiGenerator.getTopupUrl();
            });

            // Nút "Để sau" trong box lỗi credit: chỉ ẩn box
            $(document).on('click', '#btn-retry', function(e) {
                e.preventDefault();
                self.hideError();
            });
        },

        getTopupUrl: function() {
            // URL giống link +Mua trên thanh trên; sửa lại nếu anh dùng URL khác
            return window.location.origin + '/buy-credit';
        },

        // ============================================================
        // LƯU / KHÔI PHỤC ẢNH
        // ============================================================

        restoreImageFromStorage: function() {
            var saved = null;
            try {
                var raw = localStorage.getItem(this.storageKey);
                if (raw) saved = JSON.parse(raw);
            } catch (e) { return; }

            if (!saved || !saved.dataUrl) return;

            var maxAgeMs = 24 * 60 * 60 * 1000;
            if (saved.createdAt && (Date.now() - saved.createdAt > maxAgeMs)) {
                this.clearImageFromStorage();
                return;
            }

            this.currentImageData = saved.dataUrl;
            this.showPreview(saved.dataUrl);
            $('#btn-generate').prop('disabled', false);

            if (saved.imageSessionId) {
                this.currentImageSessionId = saved.imageSessionId;
            }
        },

        saveImageToStorage: function(dataUrl) {
            try {
                var payload = {
                    dataUrl: dataUrl,
                    createdAt: Date.now(),
                    imageSessionId: this.currentImageSessionId || null
                };
                localStorage.setItem(this.storageKey, JSON.stringify(payload));
            } catch (e) {}
        },

        clearImageFromStorage: function() {
            try { localStorage.removeItem(this.storageKey); } catch (e) {}
        },

        // ============================================================
        // UPLOAD / PREVIEW
        // ============================================================

        initDropzone: function() {
            var self = this; 
            var $d = $('#upload-area'); 
            if(!$d.length) return;

            ['dragenter','dragover','dragleave','drop'].forEach(function(e){ 
                $d[0].addEventListener(e, function(ev){ ev.preventDefault(); ev.stopPropagation(); }, false); 
            });

            $d.on('drop', function(e){ 
                if (e.originalEvent.dataTransfer.files.length) {
                    self.handleFileSelect(e.originalEvent.dataTransfer.files[0]); 
                }
            });
        },

        handleFileSelect: function(file) {
            var self = this;

            if(file.size > self.config.max_file_size) { 
                self.showError(self.config.strings.error_file_size); 
                return; 
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                var dataUrl = e.target.result;

                var img = new Image();
                img.onload = function() {
                    var maxDim = 1200;
                    var w = img.width, h = img.height;

                    var scale = 1;
                    if (w > h && w > maxDim)      scale = maxDim / w;
                    else if (h >= w && h > maxDim) scale = maxDim / h;

                    var newW = Math.round(w * scale);
                    var newH = Math.round(h * scale);

                    if (scale === 1) {
                        self.currentImageData = dataUrl;
                        self.currentImageSessionId = null;
                        self.showPreview(dataUrl);
                        $('#btn-generate').prop('disabled', false);
                        self.saveImageToStorage(dataUrl);
                        return;
                    }

                    var canvas = document.createElement('canvas');
                    canvas.width = newW;
                    canvas.height = newH;

                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, newW, newH);

                    var compressedDataUrl = canvas.toDataURL('image/jpeg', 0.7);

                    self.currentImageData = compressedDataUrl;
                    self.currentImageSessionId = null;

                    self.showPreview(compressedDataUrl);
                    $('#btn-generate').prop('disabled', false);
                    self.saveImageToStorage(compressedDataUrl);
                };

                img.onerror = function() {
                    self.showError(self.config.strings.error_upload || 'Không thể đọc ảnh.');
                };

                img.src = dataUrl;
            };

            reader.onerror = function() {
                self.showError(self.config.strings.error_upload || 'Không thể đọc ảnh.');
            };

            reader.readAsDataURL(file);
        },

        showPreview: function(src) { 
            $('#preview-image').attr('src', src); 
            $('.upload-placeholder').hide(); 
            $('#upload-preview').show(); 
        },

        removeImage: function(clearStorage) { 
            this.currentImageData      = null; 
            this.currentImageId        = null; 
            this.currentImageSessionId = null;

            $('#image-input').val(''); 
            $('#upload-preview').hide(); 
            $('.upload-placeholder').show(); 
            $('#btn-generate').prop('disabled', true); 

            if (clearStorage) this.clearImageFromStorage();
        },

        // ============================================================
        // GENERATE
        // ============================================================

        generatePreview: function() {
            var self = this;

            if (!this.currentImageData && !this.currentImageSessionId) {
                this.showError(this.config.strings.error_upload);
                return;
            }

            this.selectedStyle = $('#selected-style-slug').val();

            $('#btn-generate .btn-text').hide(); 
            $('#btn-generate .btn-loading').show(); 
            $('#btn-generate').prop('disabled', true);

            var payload = {
                style: this.selectedStyle,
                prompt: $('#custom-prompt').val()
            };

            if (this.currentImageSessionId) {
                payload.image_session_id = this.currentImageSessionId;
            } else {
                var img = this.currentImageData.includes(',')
                        ? this.currentImageData.split(',')[1]
                        : this.currentImageData;
                payload.image = img;
            }
            
            $.ajax({
                url: this.config.api_preview,
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function(res) { self.handlePreviewSuccess(res); },
                error: function(xhr) { self.handleAPIError(xhr); },
                complete: function() {
                    $('#btn-generate .btn-text').show(); 
                    $('#btn-generate .btn-loading').hide(); 
                    $('#btn-generate').prop('disabled', false);
                }
            });
        },
        
        handlePreviewSuccess: function(res) {
            if (res.success && res.preview_url) {
                this.currentImageId = res.image_id;

                var newSessionId = res.image_session_id || null;
                if (newSessionId) {
                    this.currentImageSessionId = newSessionId;
                }

                if (this.currentImageData) {
                    this.saveImageToStorage(this.currentImageData);
                }

                $('#result-image').attr('src', res.preview_url);
                $('.gemini-generator-form').hide();
                $('#gemini-result').show();

                if(res.credits_remaining !== undefined)
                    $('#gemini-credits-display').text(res.credits_remaining);

                // Nếu không đủ credit để unlock, vẫn cho click và show form lỗi credit
                if(!res.can_unlock) {
                    $('#btn-unlock')
                        .prop('disabled', false)
                        .data('force-credit-error', true)
                        .text('Mở khóa ảnh gốc');
                } else {
                    $('#btn-unlock')
                        .prop('disabled', false)
                        .data('force-credit-error', false)
                        .text('Mở Khóa Ảnh Gốc');
                }

            } else {
                this.showError(res.message || 'Lỗi');
            }
        },

        // ============================================================
        // UNLOCK
        // ============================================================

        unlockImage: function() {
            var self = this;
            if (!this.currentImageId) return;

            var $btn = $('#btn-unlock');

            // Nếu nút đang ở trạng thái "Không đủ credit" sau preview,
            // thì không gọi API unlock nữa, mà hiển thị luôn form lỗi credit
            if ($btn.data('force-credit-error')) {
                this.showError(
                    'Bạn không đủ tín dụng. Vui lòng nạp thêm để tiếp tục.',
                    true // isCreditError => dùng đúng form Bạn không đủ tín dụng + 3 nút
                );
                return;
            }

            var txt  = $btn.text();

            $btn.prop('disabled', true).text(this.config.strings.unlocking);

            $.ajax({
                url: this.config.api_unlock,
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                contentType: 'application/json',
                data: JSON.stringify({ image_id: this.currentImageId }),

                success: function(res) {
                    if(res.success){

                        if (res.download_url) {
                            $('#btn-download').attr('href', res.download_url);
                            $('#btn-download').attr('target', '_blank');
                        } else if (res.full_url) {
                            $('#btn-download').attr('href', res.full_url);
                            $('#btn-download').attr('target', '_blank');
                        }

                        var unlockedSrc = $('#result-image').attr('src');
                        $('#unlocked-image').attr('src', unlockedSrc);

                        if (res.download_url) {
                            fetch(res.download_url, { credentials: 'include' })
                                .then(res => { if (!res.ok) throw new Error(); return res.blob(); })
                                .then(blob => {
                                    var url = URL.createObjectURL(blob);
                                    $('#unlocked-image').attr('src', url);
                                })
                                .catch(err => console.error('[Gemini] Full image error:', err));
                        }

                        $('#gemini-result').hide();
                        $('#gemini-unlocked').show();

                        if(res.credits_remaining !== undefined)
                            $('#gemini-credits-display').text(res.credits_remaining);

                    } else {
                        self.showError(res.message);
                        $btn.prop('disabled', false).text(txt);
                    }
                },

                error: function(xhr) {
                    self.handleAPIError(xhr);
                    $btn.prop('disabled', false).text(txt); 
                }
            });
        },

        // ============================================================
        // ERROR HANDLING
        // ============================================================

        handleAPIError: function(xhr) {
            var msg = this.config.strings.error_generate;
            var isCreditError = false;

            if (xhr.responseJSON) {
                msg = xhr.responseJSON.message;
                if (xhr.status === 402 || xhr.responseJSON.code === 'insufficient_credits') {
                    isCreditError = true;
                }
            }
            
            this.showError(msg, isCreditError);
        },

        showError: function(message, isCreditError) {
            $('#error-message').text(message || this.config.strings.error_generate);
            $('#gemini-error').show();

            if (isCreditError) {
                $('#credit-suggestion').show();
                $('#generic-error-actions').hide();
            } else {
                $('#credit-suggestion').hide();
                $('#generic-error-actions').show();
            }
        },

        hideError: function() { $('#gemini-error').hide(); },

        resetToForm: function() {
            $('#gemini-result').hide();
            $('#gemini-unlocked').hide();
            $('#gemini-error').hide();
            $('.gemini-generator-form').show();
        },

        resetAll: function() {
            this.currentImageData      = null;
            this.currentImageId        = null;
            this.currentImageSessionId = null;
            this.removeImage(true);
            this.resetToForm();
        },

        // ============================================================
        // MISSION
        // ============================================================

        startMission: function($btnSource) {
            var self = this;
            this.hideError();

            var $btn = $btnSource || $('.btn-earn-free-bar');
            var originalText = $btn.text();

            $btn.prop('disabled', true).text('...');

            $.ajax({
                url: rest_url('ai/v1/mission/get'),
                method: 'GET',
                headers: { 'X-WP-Nonce': this.config.nonce },

                success: function(res) {
                    if (res.success) {
                        self.currentMission = res.mission;
                        self.showMissionModal(res.mission);
                    } else {
                        alert(res.message || 'Hiện tại không có nhiệm vụ nào.');
                    }
                },

                error: function() {
                    alert('Hiện tại không có nhiệm vụ nào.');
                },

                complete: function() { 
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        showMissionModal: function(mission) {
            $('#mission-modal').remove();
            var html = `
                <div id="mission-modal" style="display:flex; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
                    <div style="background:#fff; padding:25px; border-radius:12px; width:90%; max-width:450px; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
                        <span class="close-modal" style="position:absolute; top:10px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
                        <h3 style="margin-top:0; color:#0073aa;">${mission.title}</h3>
                        <div style="margin:15px 0; font-size:15px; line-height:1.6; max-height:300px; overflow-y:auto;">${mission.steps}</div>
                        <div style="background:#f0f0f1; padding:15px; border-radius:8px; text-align:center;">
                            <input type="text" id="mission-code" placeholder="Nhập mã 6 số" maxlength="6" 
                                   style="width:80%; font-size:20px; letter-spacing:3px; text-align:center; padding:8px; 
                                   border-radius:4px; border:1px solid #ccd0d4;">
                            <div style="margin-top:8px; color:#28a745; font-weight:bold;">+${mission.reward} Credit</div>
                        </div>
                        <button id="btn-verify-mission" 
                                style="width:100%; margin-top:15px; padding:12px; background:#0073aa; 
                                       color:#fff; border:none; border-radius:5px; font-size:16px; cursor:pointer;">
                            Xác Nhận
                        </button>
                    </div>
                </div>
            `;
            $('body').append(html);
            $('.close-modal').click(function(){ $('#mission-modal').hide(); });
        },

        verifyMissionCode: function() {
            var self = this;
            var code = $('#mission-code').val();
            var $btn = $('#btn-verify-mission');

            if(!code || code.length < 6) { 
                alert('Vui lòng nhập mã hợp lệ'); 
                return; 
            }
            
            $btn.prop('disabled', true).text('Đang kiểm tra...');

            $.ajax({
                url: rest_url('ai/v1/mission/verify'),
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                data: { code: code, mission_id: self.currentMission.id },

                success: function(res) {
                    if (res.success) {
                        alert(res.message);
                        $('#mission-modal').hide();
                        $('#gemini-credits-display').text(res.total_credits);

                        if (self.pendingAction === 'generate') {
                            self.generatePreview();
                        } else if (self.pendingAction === 'unlock') {
                            $('#btn-unlock').prop('disabled', false).text('Mở Khóa Ảnh Gốc');
                        }

                        self.pendingAction = null;
                    }
                },

                error: function(xhr) {
                    alert(xhr.responseJSON && xhr.responseJSON.message || 'Mã sai.');
                },

                complete: function() {
                    $btn.prop('disabled', false).text('Xác Nhận');
                }
            });
        }
    };

    function rest_url(path) { 
        return window.AIGeminiConfig.api_preview.replace('/preview', '') 
               + '/' + path.replace('ai/v1/', ''); 
    }

    $(document).ready(function() { 
        if ($('#ai-gemini-generator').length) AIGeminiGenerator.init();
    });

})(jQuery);