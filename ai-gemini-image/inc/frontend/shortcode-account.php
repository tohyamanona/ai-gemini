<?php
/**
 * AI Gemini Image Generator - Account Shortcode
 *
 * Hiển thị giao diện đăng nhập / đăng ký cho khách hàng.
 * Shortcode: [ai_gemini_account]
 */

if (!defined('ABSPATH')) exit;

/**
 * Shortcode handler: [ai_gemini_account]
 */
function ai_gemini_account_shortcode($atts = [], $content = null) {
    // Bọc toàn bộ xử lý trong try/catch để tránh 500 trắng và log lỗi rõ ràng
    try {
        wp_enqueue_style(
            'ai-gemini-frontend',
            AI_GEMINI_PLUGIN_URL . 'assets/css/generator.css',
            [],
            AI_GEMINI_VERSION
        );

        ob_start();

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            ?>
            <div class="ai-gemini-account-wrap">
                <h2><?php printf(esc_html__('Xin chào, %s', 'ai-gemini-image'), esc_html($current_user->display_name ?: $current_user->user_login)); ?></h2>
                <p>
                    <?php esc_html_e('Bạn đã đăng nhập. Bạn có thể bắt đầu tạo ảnh hoặc xem lịch sử của mình.', 'ai-gemini-image'); ?>
                </p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(home_url('/')); ?>">
                        <?php esc_html_e('Về trang chủ', 'ai-gemini-image'); ?>
                    </a>
                </p>
                <p>
                    <a class="button" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">
                        <?php esc_html_e('Đăng xuất', 'ai-gemini-image'); ?>
                    </a>
                </p>
            </div>
            <?php
        } else {
            ai_gemini_render_login_register_forms();
        }

        return ob_get_clean();
    } catch (Throwable $e) {
        if (function_exists('ai_gemini_log')) {
            ai_gemini_log(
                'Account page error: ' . $e->getMessage() .
                ' in ' . $e->getFile() . ':' . $e->getLine(),
                'error'
            );
        }

        // Trả thông báo thân thiện thay vì 500 trắng
        return '<div class="ai-gemini-account-wrap"><p>' .
            esc_html__('Có lỗi xảy ra khi tải trang tài khoản. Vui lòng thử lại sau hoặc liên hệ quản trị viên.', 'ai-gemini-image') .
            '</p></div>';
    }
}
add_shortcode('ai_gemini_account', 'ai_gemini_account_shortcode');

/**
 * Render form đăng nhập + đăng ký cho khách chưa đăng nhập
 * Dạng 2 tab: Đăng nhập / Đăng ký
 */
function ai_gemini_render_login_register_forms() {
    // Xử lý đăng ký
    if (isset($_POST['ai_gemini_register']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ai_gemini_register_nonce')) {
        ai_gemini_handle_register_request();
    }

    // Xử lý đăng nhập
    if (isset($_POST['ai_gemini_login']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ai_gemini_login_nonce')) {
        ai_gemini_handle_login_request();
    }

    // Hiển thị thông báo (nếu có)
    $errors   = isset($GLOBALS['ai_gemini_auth_errors']) ? $GLOBALS['ai_gemini_auth_errors'] : [];
    $messages = isset($GLOBALS['ai_gemini_auth_messages']) ? $GLOBALS['ai_gemini_auth_messages'] : [];

    if (!empty($errors)) {
        echo '<div class="ai-gemini-auth-notice ai-gemini-auth-notice-error">';
        foreach ($errors as $err) {
            echo '<p>' . esc_html($err) . '</p>';
        }
        echo '</div>';
    }

    if (!empty($messages)) {
        echo '<div class="ai-gemini-auth-notice ai-gemini-auth-notice-success">';
        foreach ($messages as $msg) {
            echo '<p>' . esc_html($msg) . '</p>';
        }
        echo '</div>';
    }

    // Mặc định tab đang mở: login (đổi sang register nếu có submit đăng ký)
    $active_tab = isset($_POST['ai_gemini_register']) ? 'register' : 'login';
    ?>
    <div class="ai-gemini-account-tabs-wrap">
        <div class="ai-gemini-account-tabs">
            <button type="button"
                    class="ai-gemini-tab-btn <?php echo $active_tab === 'login' ? 'is-active' : ''; ?>"
                    data-tab="login">
                <?php esc_html_e('Đăng nhập', 'ai-gemini-image'); ?>
            </button>
            <button type="button"
                    class="ai-gemini-tab-btn <?php echo $active_tab === 'register' ? 'is-active' : ''; ?>"
                    data-tab="register">
                <?php esc_html_e('Đăng ký', 'ai-gemini-image'); ?>
            </button>
        </div>

        <div class="ai-gemini-tab-panels">

            <!-- TAB: Đăng nhập -->
            <div class="ai-gemini-tab-panel <?php echo $active_tab === 'login' ? 'is-active' : ''; ?>"
                 data-tab-panel="login">
                <h2><?php esc_html_e('Đăng nhập tài khoản', 'ai-gemini-image'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Đăng nhập bằng số điện thoại hoặc email để nhận nhiều lượt thử miễn phí hơn, lưu lịch sử ảnh và quản lý credit.', 'ai-gemini-image'); ?>
                </p>

                <form method="post" class="ai-gemini-form ai-gemini-login-form">
                    <?php wp_nonce_field('ai_gemini_login_nonce'); ?>
                    <p class="ai-gemini-form-row">
                        <label for="ai_gemini_login_username"><?php esc_html_e('Số điện thoại hoặc Email', 'ai-gemini-image'); ?></label>
                        <input type="text"
                               name="ai_gemini_login_username"
                               id="ai_gemini_login_username"
                               class="regular-text"
                               required>
                    </p>
                    <p class="ai-gemini-form-row">
                        <label for="ai_gemini_login_password"><?php esc_html_e('Mật khẩu', 'ai-gemini-image'); ?></label>
                        <input type="password"
                               name="ai_gemini_login_password"
                               id="ai_gemini_login_password"
                               class="regular-text"
                               required>
                    </p>
                    <p class="ai-gemini-form-row ai-gemini-form-row-inline">
                        <label>
                            <input type="checkbox" name="ai_gemini_login_remember" value="1">
                            <?php esc_html_e('Ghi nhớ đăng nhập', 'ai-gemini-image'); ?>
                        </label>
                        <a class="ai-gemini-link-right" href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                            <?php esc_html_e('Quên mật khẩu?', 'ai-gemini-image'); ?>
                        </a>
                    </p>
                    <p class="ai-gemini-form-row">
                        <button type="submit" name="ai_gemini_login" class="button button-primary button-large">
                            <?php esc_html_e('Đăng nhập', 'ai-gemini-image'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- TAB: Đăng ký -->
            <div class="ai-gemini-tab-panel <?php echo $active_tab === 'register' ? 'is-active' : ''; ?>"
                 data-tab-panel="register">
                <h2><?php esc_html_e('Đăng ký tài khoản mới', 'ai-gemini-image'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Sử dụng số điện thoại để đăng ký nhanh. Bạn cũng nên nhập email để có thể khôi phục mật khẩu khi cần.', 'ai-gemini-image'); ?>
                </p>

                <form method="post" class="ai-gemini-form ai-gemini-register-form">
                    <?php wp_nonce_field('ai_gemini_register_nonce'); ?>
                    <p class="ai-gemini-form-row">
                        <label for="ai_gemini_reg_phone"><?php esc_html_e('Số điện thoại', 'ai-gemini-image'); ?></label>
                        <input type="text"
                               name="ai_gemini_reg_phone"
                               id="ai_gemini_reg_phone"
                               class="regular-text"
                               required>
                    </p>
                    <p class="ai-gemini-form-row">
                        <label for="ai_gemini_reg_email"><?php esc_html_e('Email', 'ai-gemini-image'); ?></label>
                        <input type="email"
                               name="ai_gemini_reg_email"
                               id="ai_gemini_reg_email"
                               class="regular-text"
                               required>
                    </p>
                    <p class="ai-gemini-form-row">
                        <label for="ai_gemini_reg_password"><?php esc_html_e('Mật khẩu', 'ai-gemini-image'); ?></label>
                        <input type="password"
                               name="ai_gemini_reg_password"
                               id="ai_gemini_reg_password"
                               class="regular-text"
                               required>
                    </p>
                    <p class="ai-gemini-form-row">
                        <label for="ai_gemini_reg_password2"><?php esc_html_e('Nhập lại mật khẩu', 'ai-gemini-image'); ?></label>
                        <input type="password"
                               name="ai_gemini_reg_password2"
                               id="ai_gemini_reg_password2"
                               class="regular-text"
                               required>
                    </p>
                    <p class="ai-gemini-form-row">
                        <button type="submit" name="ai_gemini_register" class="button button-secondary button-large">
                            <?php esc_html_e('Đăng ký', 'ai-gemini-image'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const container = document.querySelector('.ai-gemini-account-tabs-wrap');
            if (!container) return;

            const tabButtons = container.querySelectorAll('.ai-gemini-tab-btn');
            const tabPanels  = container.querySelectorAll('.ai-gemini-tab-panel');

            tabButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-tab');

                    tabButtons.forEach(function(b) {
                        b.classList.toggle('is-active', b === btn);
                    });

                    tabPanels.forEach(function(panel) {
                        panel.classList.toggle('is-active', panel.getAttribute('data-tab-panel') === target);
                    });
                });
            });
        })();
    </script>
    <?php
}

/**
 * Xử lý đăng ký (dùng SĐT làm username)
 */
function ai_gemini_handle_register_request() {
    $errors   = [];
    $messages = [];

    $phone     = isset($_POST['ai_gemini_reg_phone']) ? sanitize_text_field(wp_unslash($_POST['ai_gemini_reg_phone'])) : '';
    $email     = isset($_POST['ai_gemini_reg_email']) ? sanitize_email(wp_unslash($_POST['ai_gemini_reg_email'])) : '';
    $password  = isset($_POST['ai_gemini_reg_password']) ? (string) $_POST['ai_gemini_reg_password'] : '';
    $password2 = isset($_POST['ai_gemini_reg_password2']) ? (string) $_POST['ai_gemini_reg_password2'] : '';

    // Chuẩn hoá số điện thoại thành username (loại bỏ khoảng trắng, ký tự lạ)
    $username_raw = preg_replace('/\D+/', '', $phone);
    $username     = sanitize_user($username_raw, true);

    // Validate
    if (empty($phone) || empty($username) || empty($email) || empty($password) || empty($password2)) {
        $errors[] = __('Vui lòng nhập đầy đủ thông tin.', 'ai-gemini-image');
    }

    if (!is_email($email)) {
        $errors[] = __('Email không hợp lệ.', 'ai-gemini-image');
    }

    if ($password !== $password2) {
        $errors[] = __('Mật khẩu nhập lại không khớp.', 'ai-gemini-image');
    }

    if (username_exists($username)) {
        $errors[] = __('Số điện thoại này đã được sử dụng để đăng ký.', 'ai-gemini-image');
    }

    if (email_exists($email)) {
        $errors[] = __('Email này đã được sử dụng.', 'ai-gemini-image');
    }

    if (!empty($errors)) {
        $GLOBALS['ai_gemini_auth_errors'] = $errors;
        return;
    }

    // Tạo user
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        $errors[] = $user_id->get_error_message();
        $GLOBALS['ai_gemini_auth_errors'] = $errors;
        return;
    }

    // Lưu số điện thoại trong user meta
    update_user_meta($user_id, 'ai_gemini_phone', $phone);

    // Set role (nếu cần)
    wp_update_user([
        'ID'   => $user_id,
        'role' => 'subscriber',
    ]);

    $messages[] = __('Đăng ký thành công. Bạn có thể đăng nhập ngay bây giờ bằng số điện thoại hoặc email.', 'ai-gemini-image');
    $GLOBALS['ai_gemini_auth_messages'] = $messages;
}

/**
 * Xử lý đăng nhập (số điện thoại hoặc email)
 */
function ai_gemini_handle_login_request() {
    $errors   = [];
    $messages = [];

    $login_raw = isset($_POST['ai_gemini_login_username']) ? sanitize_text_field(wp_unslash($_POST['ai_gemini_login_username'])) : '';
    $password  = isset($_POST['ai_gemini_login_password']) ? (string) $_POST['ai_gemini_login_password'] : '';
    $remember  = !empty($_POST['ai_gemini_login_remember']);

    if (empty($login_raw) || empty($password)) {
        $errors[] = __('Vui lòng nhập số điện thoại/email và mật khẩu.', 'ai-gemini-image');
        $GLOBALS['ai_gemini_auth_errors'] = $errors;
        return;
    }

    $user = null;

    if (is_email($login_raw)) {
        // Đăng nhập bằng email
        $user_obj = get_user_by('email', $login_raw);
        if ($user_obj) {
            $user_login = $user_obj->user_login;
        } else {
            $errors[] = __('Không tìm thấy tài khoản với email này.', 'ai-gemini-image');
            $GLOBALS['ai_gemini_auth_errors'] = $errors;
            return;
        }
    } else {
        // Đăng nhập bằng số điện thoại (username)
        // Chuẩn hoá giống lúc đăng ký
        $username_raw = preg_replace('/\D+/', '', $login_raw);
        $user_login   = $username_raw;
    }

    $creds = [
        'user_login'    => $user_login,
        'user_password' => $password,
        'remember'      => $remember,
    ];

    $user = wp_signon($creds, is_ssl());

    if (is_wp_error($user)) {
        $errors[] = $user->get_error_message();
        $GLOBALS['ai_gemini_auth_errors'] = $errors;
        return;
    }

    // Đăng nhập thành công, reload trang hiện tại
    wp_safe_redirect(get_permalink());
    exit;
}