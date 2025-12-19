<?php
/**
 * AI Gemini Image Generator - Admin Menu
 */

if (!defined('ABSPATH')) exit;

function ai_gemini_admin_menu() {
    // Menu chính
    add_menu_page(
        'AI Gemini',
        'AI Gemini',
        'manage_options',
        'ai-gemini-dashboard',
        'ai_gemini_dashboard_page',
        'dashicons-format-image',
        30
    );

    // Bảng tin
    add_submenu_page(
        'ai-gemini-dashboard',
        'Bảng Tin',
        'Bảng Tin',
        'manage_options',
        'ai-gemini-dashboard',
        'ai_gemini_dashboard_page'
    );

    // Cài đặt
    add_submenu_page(
        'ai-gemini-dashboard',
        'Cài Đặt',
        'Cài Đặt',
        'manage_options',
        'ai-gemini-settings',
        'ai_gemini_settings_page'
    );

    // Quản lý Prompts
    add_submenu_page(
        'ai-gemini-dashboard',
        'Quản lý Prompts',
        'Quản lý Prompts',
        'manage_options',
        'ai-gemini-prompts',
        'ai_gemini_prompt_manager_page'
    );
    
    // MISSION MANAGER MENU
    add_submenu_page(
        'ai-gemini-dashboard',
        'Quản lý Nhiệm Vụ',
        'Nhiệm Vụ (Traffic)',
        'manage_options',
        'ai-gemini-missions',
        'ai_gemini_mission_manager_page'
    );
    
    // Quản lý Credit (số dư người dùng)
    add_submenu_page(
        'ai-gemini-dashboard',
        'Quản lý Credit',
        'Quản lý Credit',
        'manage_options',
        'ai-gemini-credits',
        'ai_gemini_credit_manager_page'
    );

    // Gói tín dụng
    add_submenu_page(
        'ai-gemini-dashboard',
        'Gói Tín Dụng',
        'Gói Tín Dụng',
        'manage_options',
        'ai-gemini-credit-packages',
        'ai_gemini_credit_packages_page'
    );

    // Đơn hàng
    add_submenu_page(
        'ai-gemini-dashboard',
        'Đơn Hàng',
        'Đơn Hàng',
        'manage_options',
        'ai-gemini-orders',
        'ai_gemini_orders_page'
    );
}
add_action('admin_menu', 'ai_gemini_admin_menu');

// Dashboard
function ai_gemini_dashboard_page() {
    echo '<div class="wrap"><h1>Tổng Quan</h1><p>Chào mừng đến với AI Gemini.</p></div>';
}

/**
 * Trang Cài Đặt có 2 tab:
 * - general: API, Credit, Trial, Mission, Watermark
 * - bank: Kết nối Ngân hàng / Đối soát giao dịch (mặc định ngân hàng là ACB)
 */
function ai_gemini_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    if (!in_array($active_tab, ['general', 'bank'], true)) {
        $active_tab = 'general';
    }

    ?>
    <div class="wrap">
        <h1>Cài Đặt AI Gemini</h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( add_query_arg(['page' => 'ai-gemini-settings', 'tab' => 'general'], admin_url('admin.php')) ); ?>"
               class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                Cài Đặt Chung
            </a>
            <a href="<?php echo esc_url( add_query_arg(['page' => 'ai-gemini-settings', 'tab' => 'bank'], admin_url('admin.php')) ); ?>"
               class="nav-tab <?php echo $active_tab === 'bank' ? 'nav-tab-active' : ''; ?>">
                Kết nối Ngân hàng / Đối soát giao dịch
            </a>
        </h2>

        <?php
        if ($active_tab === 'bank') {
            ai_gemini_settings_page_bank();
        } else {
            ai_gemini_settings_page_general();
        }
        ?>
    </div>
    <?php
}

/**
 * Tab: Cài Đặt Chung
 * - API & Credit
 * - Trial
 * - Mission
 * - Watermark
 * (ĐÃ LOẠI BỎ HOÀN TOÀN FORM VietQR)
 */
function ai_gemini_settings_page_general() {
    // Lưu cài đặt chung
    if (isset($_POST['ai_gemini_save_settings_general']) && check_admin_referer('ai_gemini_settings_general_nonce')) {
        // API & credit
        update_option('ai_gemini_api_key', sanitize_text_field(wp_unslash($_POST['ai_gemini_api_key'] ?? '')));
        update_option('ai_gemini_preview_credit', absint($_POST['ai_gemini_preview_credit'] ?? 0));
        update_option('ai_gemini_unlock_credit', absint($_POST['ai_gemini_unlock_credit'] ?? 1));
        update_option('ai_gemini_free_trial_credits', absint($_POST['ai_gemini_free_trial_credits'] ?? 0));

        // Trial limits
        update_option('ai_gemini_user_trial_limit', absint($_POST['ai_gemini_user_trial_limit'] ?? 1));
        update_option('ai_gemini_guest_trial_limit', absint($_POST['ai_gemini_guest_trial_limit'] ?? 1));
        
        // Mission Settings
        update_option('ai_gemini_mission_secret', sanitize_text_field(wp_unslash($_POST['ai_gemini_mission_secret'] ?? '')));
        update_option('ai_gemini_mission_window', absint($_POST['ai_gemini_mission_window'] ?? 15));

        // Watermark Settings
        if (isset($_POST['ai_gemini_watermark_text'])) {
            update_option(
                'ai_gemini_watermark_text',
                sanitize_text_field( wp_unslash($_POST['ai_gemini_watermark_text']) )
            );
        }

        echo '<div class="notice notice-success"><p>Đã lưu cài đặt chung!</p></div>';
    }

    // Load current values
    $api_key             = get_option('ai_gemini_api_key', '');
    $preview_credit      = get_option('ai_gemini_preview_credit', 0);
    $unlock_credit       = get_option('ai_gemini_unlock_credit', 1);
    $free_trial_credits  = get_option('ai_gemini_free_trial_credits', 1);
    $mission_secret      = get_option('ai_gemini_mission_secret', '');
    $mission_window      = get_option('ai_gemini_mission_window', 15);
    $watermark_text      = get_option('ai_gemini_watermark_text', 'AI Gemini Preview');

    // Trial limits
    $user_trial_limit    = (int) get_option('ai_gemini_user_trial_limit', 1);
    $guest_trial_limit   = (int) get_option('ai_gemini_guest_trial_limit', 1);
    ?>
    <form method="post">
        <?php wp_nonce_field('ai_gemini_settings_general_nonce'); ?>

        <!-- API & Credit -->
        <h2>API & Credit</h2>
        <table class="form-table">
            <tr>
                <th>Gemini API Key</th>
                <td><input type="password" name="ai_gemini_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Phí xem trước</th>
                <td>
                    <input type="number" name="ai_gemini_preview_credit" value="<?php echo esc_attr($preview_credit); ?>" min="0" class="small-text">
                    <p class="description">Số credit trừ mỗi lần tạo preview (0 = miễn phí).</p>
                </td>
            </tr>
            <tr>
                <th>Phí mở khóa</th>
                <td>
                    <input type="number" name="ai_gemini_unlock_credit" value="<?php echo esc_attr($unlock_credit); ?>" min="1" class="small-text">
                    <p class="description">Số credit cần để mở khóa ảnh full.</p>
                </td>
            </tr>
            <tr>
                <th>Credit dùng thử (legacy)</th>
                <td>
                    <input type="number" name="ai_gemini_free_trial_credits" value="<?php echo esc_attr($free_trial_credits); ?>" min="0" class="small-text">
                    <p class="description">Dùng cho hệ thống trial cũ (free credit ban đầu). Có thể để 0 nếu không dùng.</p>
                </td>
            </tr>
            <tr>
                <th>Watermark preview text</th>
                <td>
                    <input type="text"
                           name="ai_gemini_watermark_text"
                           value="<?php echo esc_attr($watermark_text); ?>"
                           class="regular-text">
                    <p class="description">
                        Dòng chữ dùng cho watermark chéo trên ảnh preview (kiểu Shutterstock).
                    </p>
                </td>
            </tr>
        </table>

        <hr>

        <!-- Trial Settings -->
        <h2>Cấu Hình Trial</h2>
        <table class="form-table">
            <tr>
                <th>Trial cho user đăng nhập</th>
                <td>
                    <input type="number"
                           name="ai_gemini_user_trial_limit"
                           value="<?php echo esc_attr($user_trial_limit); ?>"
                           min="0"
                           class="small-text">
                    <p class="description">
                        Số lượt preview miễn phí cho mỗi user đã đăng nhập.
                    </p>
                </td>
            </tr>
            <tr>
                <th>Trial cho khách (guest, theo IP)</th>
                <td>
                    <input type="number"
                           name="ai_gemini_guest_trial_limit"
                           value="<?php echo esc_attr($guest_trial_limit); ?>"
                           min="0"
                           class="small-text">
                    <p class="description">
                        Số lượt preview miễn phí cho mỗi khách (theo IP).
                    </p>
                </td>
            </tr>
        </table>

        <hr>

        <!-- Cấu hình nhiệm vụ -->
        <h2>Cấu Hình Nhiệm Vụ (Mission 2FA)</h2>
        <table class="form-table">
            <tr>
                <th>Secret Key (2FA)</th>
                <td>
                    <input type="text" name="ai_gemini_mission_secret" id="sec_key" value="<?php echo esc_attr($mission_secret); ?>" class="regular-text">
                    <button type="button" class="button" onclick="document.getElementById('sec_key').value = Math.random().toString(36).slice(-12).toUpperCase();">Tạo mới</button>
                    <p class="description">Key chung cho tất cả nhiệm vụ. Cần dán vào file PHP trên web vệ tinh.</p>
                </td>
            </tr>
            <tr>
                <th>Thời gian hiệu lực</th>
                <td>
                    <input type="number" name="ai_gemini_mission_window" value="<?php echo esc_attr($mission_window); ?>" min="1" max="60" class="small-text"> phút
                    <p class="description">Cho phép mã cũ có hiệu lực trong khoảng này (tránh lệch giờ).</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit"
                   name="ai_gemini_save_settings_general"
                   class="button button-primary"
                   value="Lưu Cài Đặt Chung">
        </p>
    </form>
    <?php
}

/**
 * Tab: Kết nối Ngân hàng / Đối soát giao dịch
 * - Form riêng, xử lý riêng.
 * - Mặc định đồng bộ VietQR: bank_id = ACB, account_no = số TK này.
 */
function ai_gemini_settings_page_bank() {
    $test_history_result = null;
    $test_history_error  = null;

    // Lưu kết nối ngân hàng
    if (isset($_POST['ai_gemini_save_settings_bank']) && check_admin_referer('ai_gemini_settings_bank_nonce')) {
        $bank_username = sanitize_text_field(wp_unslash($_POST['ai_gemini_bank_username'] ?? ''));
        update_option('ai_gemini_bank_username', $bank_username);

        if (!empty($_POST['ai_gemini_bank_password'])) {
            $plain_pw = wp_unslash($_POST['ai_gemini_bank_password']);
            $enc_pw   = function_exists('ai_gemini_encrypt') ? ai_gemini_encrypt($plain_pw) : $plain_pw;
            update_option('ai_gemini_bank_password', $enc_pw);
        }

        $bank_account = sanitize_text_field(wp_unslash($_POST['ai_gemini_bank_account'] ?? ''));
        update_option('ai_gemini_bank_account', $bank_account);

        // Đồng bộ sang VietQR: mặc định ACB + số tài khoản này
        if ($bank_account !== '') {
            update_option('ai_gemini_vietqr_bank_id', 'ACB');
            update_option('ai_gemini_vietqr_account_no', $bank_account);
        }

        echo '<div class="notice notice-success"><p>Đã lưu cài đặt kết nối ngân hàng!</p></div>';
    }

    // Test đăng nhập & xem lịch sử
    if (isset($_POST['ai_gemini_test_bank_history']) && check_admin_referer('ai_gemini_settings_bank_nonce')) {
        $username = sanitize_text_field(wp_unslash($_POST['ai_gemini_bank_username'] ?? ''));
        $password = wp_unslash($_POST['ai_gemini_bank_password'] ?? '');
        $account  = sanitize_text_field(wp_unslash($_POST['ai_gemini_bank_account'] ?? ''));

        // Nếu không nhập mật khẩu mới, dùng mật khẩu đã lưu (mã hoá)
        if ($password === '') {
            $stored_enc = get_option('ai_gemini_bank_password', '');
            if ($stored_enc !== '' && function_exists('ai_gemini_decrypt')) {
                $password = ai_gemini_decrypt($stored_enc);
            }
        }

        if ($username === '' || $password === '' || $account === '') {
            $test_history_error = 'Vui lòng nhập đầy đủ Username / Password / Số tài khoản trước khi test.';
        } else {
            if (!function_exists('ai_gemini_test_bank_connection')) {
                $test_history_error = 'Hàm ai_gemini_test_bank_connection chưa được định nghĩa. Vui lòng kiểm tra file vietqr-checker.php.';
            } else {
                $result = ai_gemini_test_bank_connection($username, $password, $account);

                if (is_wp_error($result)) {
                    $test_history_error = $result->get_error_message();
                } else {
                    $test_history_result = $result;

                    // Đồng bộ số tài khoản sang VietQR khi test OK
                    update_option('ai_gemini_bank_account', $account);
                    update_option('ai_gemini_vietqr_bank_id', 'ACB');
                    update_option('ai_gemini_vietqr_account_no', $account);
                }
            }
        }
    }

    // Load values
    $bank_username = get_option('ai_gemini_bank_username', '');
    $bank_account  = get_option('ai_gemini_bank_account', '');
    ?>
    <form method="post">
        <?php wp_nonce_field('ai_gemini_settings_bank_nonce'); ?>

        <h2>Kết nối Ngân hàng / Đối soát giao dịch</h2>
        <p class="description">
            Nhập thông tin đăng nhập Internet Banking / API (tài khoản phụ) để plugin có thể kiểm tra và đối soát giao dịch.
            Thao tác ở tab này không ảnh hưởng tới các cài đặt chung khác.
            Ngân hàng mặc định là ACB; số tài khoản ở đây sẽ được dùng cho VietQR.
        </p>

        <table class="form-table">
            <tr>
                <th>Tên đăng nhập</th>
                <td>
                    <input type="text"
                           name="ai_gemini_bank_username"
                           value="<?php echo esc_attr($bank_username); ?>"
                           class="regular-text">
                    <p class="description">Username dùng để đăng nhập dịch vụ ngân hàng / đối soát.</p>
                </td>
            </tr>
            <tr>
                <th>Mật khẩu</th>
                <td>
                    <input type="password"
                           name="ai_gemini_bank_password"
                           value=""
                           autocomplete="new-password"
                           class="regular-text">
                    <p class="description">
                        Để trống nếu không muốn thay đổi mật khẩu đã lưu.
                        Mật khẩu sẽ không được hiển thị lại để đảm bảo bảo mật.
                    </p>
                </td>
            </tr>
            <tr>
                <th>Số tài khoản nhận tiền</th>
                <td>
                    <input type="text"
                           name="ai_gemini_bank_account"
                           value="<?php echo esc_attr($bank_account); ?>"
                           class="regular-text">
                    <p class="description">
                        Số tài khoản dùng để đối soát giao dịch (ví dụ: tài khoản ACB nhận VietQR).
                        Sau khi lưu/test, số này sẽ được đồng bộ sang cấu hình VietQR với Bank ID = ACB.
                    </p>
                </td>
            </tr>
        </table>

        <?php if (!empty($test_history_error)) : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($test_history_error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($test_history_result)) : ?>
            <div class="notice notice-success">
                <p>Đăng nhập thành công. Dưới đây là một số giao dịch gần nhất (đã giới hạn):</p>
            </div>
            <div style="max-height:300px; overflow:auto; background:#fff; border:1px solid #ddd; padding:10px;">
                <pre style="white-space:pre-wrap;word-break:break-all;"><?php
                    echo esc_html( print_r($test_history_result, true) );
                ?></pre>
            </div>
        <?php endif; ?>

        <p class="submit">
            <input type="submit"
                   name="ai_gemini_save_settings_bank"
                   class="button button-primary"
                   value="Lưu Cài Đặt Kết Nối Ngân Hàng">
            <input type="submit"
                   name="ai_gemini_test_bank_history"
                   class="button"
                   value="Đăng nhập thử &amp; xem lịch sử"
                   onclick="return confirm('Thao tác này sẽ cố gắng đăng nhập tài khoản ngân hàng/đối soát và lấy lịch sử giao dịch. Bạn chắc chắn chứ?');">
        </p>
    </form>
    <?php
}

// Orders page + admin CSS giữ nguyên
function ai_gemini_orders_page() { 
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';

    if (
        isset($_POST['ai_gemini_manual_complete_order'], $_POST['order_code'])
        && check_admin_referer('ai_gemini_complete_order_action', 'ai_gemini_complete_order_nonce')
    ) {
        $order_code     = sanitize_text_field(wp_unslash($_POST['order_code']));
        $transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field(wp_unslash($_POST['transaction_id'])) : '';

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_orders WHERE order_code = %s AND status = 'pending'",
            $order_code
        ));

        if ($order) {
            $success = ai_gemini_complete_order($order_code, $transaction_id);
            if ($success) {
                echo '<div class="notice notice-success"><p>Đã xác nhận đơn hàng ' . esc_html($order_code) . ' thành công.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Không thể hoàn tất đơn hàng ' . esc_html($order_code) . '. Vui lòng kiểm tra log.</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p>Đơn hàng không tồn tại hoặc đã được xử lý: ' . esc_html($order_code) . '.</p></div>';
        }
    }

    $paged  = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $limit  = 20;
    $offset = ($paged - 1) * $limit;

    $total_orders = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_orders");
    $orders = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_orders ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );

    $total_pages = $total_orders > 0 ? ceil($total_orders / $limit) : 1;
    ?>
    <div class="wrap ai-gemini-admin-wrap">
        <h1>Đơn Hàng</h1>

        <?php if (empty($orders)) : ?>
            <p>Chưa có đơn hàng nào.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Order Code</th>
                    <th>Người dùng</th>
                    <th>IP guest</th>
                    <th>Số credit</th>
                    <th>Số tiền (đ)</th>
                    <th>Trạng thái</th>
                    <th>Thanh toán</th>
                    <th>Ngày tạo</th>
                    <th>Hoàn tất</th>
                    <th>Hành động</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order) : ?>
                    <tr>
                        <td><?php echo esc_html($order->id); ?></td>
                        <td><code><?php echo esc_html($order->order_code); ?></code></td>
                        <td>
                            <?php
                            if ($order->user_id) {
                                $user = get_user_by('id', $order->user_id);
                                if ($user) {
                                    echo esc_html($user->user_login . ' (#' . $user->ID . ')');
                                } else {
                                    echo esc_html('ID ' . $order->user_id);
                                }
                            } else {
                                echo '<em>Guest</em>';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($order->guest_ip); ?></td>
                        <td><?php echo esc_html(number_format_i18n($order->credits)); ?></td>
                        <td><?php echo esc_html(number_format_i18n($order->amount)); ?></td>
                        <td><?php echo esc_html($order->status); ?></td>
                        <td><?php echo esc_html($order->payment_method); ?></td>
                        <td><?php echo esc_html($order->created_at); ?></td>
                        <td><?php echo esc_html($order->completed_at); ?></td>
                        <td>
                            <?php if ($order->status === 'pending') : ?>
                                <form method="post" style="display:inline-block;">
                                    <?php wp_nonce_field('ai_gemini_complete_order_action', 'ai_gemini_complete_order_nonce'); ?>
                                    <input type="hidden" name="order_code" value="<?php echo esc_attr($order->order_code); ?>">
                                    <input type="text" name="transaction_id" placeholder="Mã GD (tuỳ chọn)" style="width:120px;">
                                    <button type="submit" name="ai_gemini_manual_complete_order" class="button button-small button-primary" onclick="return confirm('Xác nhận hoàn tất đơn hàng này?');">
                                        Xác nhận
                                    </button>
                                </form>
                            <?php else : ?>
                                <span class="dashicons dashicons-yes" title="Đã hoàn tất"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links([
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                                'current'   => $paged,
                                'total'     => $total_pages,
                            ])
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

function ai_gemini_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'ai-gemini') === false) return;
    wp_enqueue_style('ai-gemini-admin', AI_GEMINI_PLUGIN_URL . 'assets/css/admin.css', [], AI_GEMINI_VERSION);
}
add_action('admin_enqueue_scripts', 'ai_gemini_admin_enqueue_scripts');