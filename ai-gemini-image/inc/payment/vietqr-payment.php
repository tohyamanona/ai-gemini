<?php
/**
 * AI Gemini Image Generator - Trang thanh toán VietQR
 * 
 * Xử lý hiển thị và xác nhận thanh toán VietQR.
 */

if (!defined('ABSPATH')) exit;

/**
 * Đăng ký endpoint thanh toán VietQR
 */
function ai_gemini_register_vietqr_endpoint() {
    add_rewrite_endpoint('ai-gemini-pay', EP_ROOT);
}
add_action('init', 'ai_gemini_register_vietqr_endpoint');

/**
 * Xử lý trang thanh toán VietQR
 */
function ai_gemini_handle_vietqr_page() {
    global $wp_query;
    
    if (!isset($wp_query->query_vars['ai-gemini-pay'])) {
        return;
    }
    
    $order_code = sanitize_text_field($wp_query->query_vars['ai-gemini-pay']);
    
    if (empty($order_code)) {
        wp_die(__('Yêu cầu thanh toán không hợp lệ', 'ai-gemini-image'));
    }
    
    // Lấy thông tin đơn hàng
    global $wpdb;
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_orders WHERE order_code = %s",
        $order_code
    ));
    
    if (!$order) {
        wp_die(__('Không tìm thấy đơn hàng', 'ai-gemini-image'));
    }
    
    if ($order->status === 'completed') {
        wp_redirect(home_url('?payment=success&order=' . $order_code));
        exit;
    }
    
    // Hiển thị trang thanh toán
    ai_gemini_display_vietqr_page($order);
    exit;
}
add_action('template_redirect', 'ai_gemini_handle_vietqr_page');

/**
 * Hiển thị giao diện thanh toán VietQR
 * 
 * @param object $order Order object
 */
function ai_gemini_display_vietqr_page($order) {
    $config = ai_gemini_get_vietqr_config();
    $qr_url = ai_gemini_generate_vietqr_url($order->order_code, $order->amount);
    
    // Lấy tên gói
    $packages = ai_gemini_get_credit_packages();
    $package_name = '';
    foreach ($packages as $package) {
        if ($package['credits'] == $order->credits) {
            $package_name = $package['name'];
            break;
        }
    }
    
    wp_enqueue_style('ai-gemini-vietqr', AI_GEMINI_PLUGIN_URL . 'assets/css/vietqr.css', [], AI_GEMINI_VERSION);
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html__('Thanh toán - AI Gemini', 'ai-gemini-image'); ?></title>
        <?php wp_head(); ?>
    </head>
    <body class="ai-gemini-payment-page">
        <div class="vietqr-container">
            <div class="vietqr-header">
                <h1><?php echo esc_html__('Hoàn tất thanh toán', 'ai-gemini-image'); ?></h1>
                <p><?php echo esc_html__('Quét mã QR bên dưới để thanh toán', 'ai-gemini-image'); ?></p>
            </div>
            
            <div class="vietqr-content">
                <div class="order-summary">
                    <h3><?php echo esc_html__('Thông tin đơn hàng', 'ai-gemini-image'); ?></h3>
                    <div class="order-details">
                        <div class="detail-row">
                            <span class="label"><?php echo esc_html__('Mã đơn', 'ai-gemini-image'); ?></span>
                            <span class="value"><code><?php echo esc_html($order->order_code); ?></code></span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><?php echo esc_html__('Gói nạp', 'ai-gemini-image'); ?></span>
                            <span class="value"><?php echo esc_html($package_name); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><?php echo esc_html__('Số credit', 'ai-gemini-image'); ?></span>
                            <span class="value"><?php echo esc_html(number_format_i18n($order->credits)); ?></span>
                        </div>
                        <div class="detail-row total">
                            <span class="label"><?php echo esc_html__('Tổng tiền', 'ai-gemini-image'); ?></span>
                            <span class="value"><?php echo esc_html(number_format_i18n($order->amount)); ?>đ</span>
                        </div>
                    </div>
                </div>
                
                <div class="qr-section">
                    <div class="qr-code">
                        <?php if ($qr_url) : ?>
                            <img src="<?php echo esc_url($qr_url); ?>" alt="Mã VietQR" id="vietqr-image">
                        <?php else : ?>
                            <p class="error"><?php echo esc_html__('Lỗi cấu hình thanh toán. Vui lòng liên hệ hỗ trợ.', 'ai-gemini-image'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bank-info">
                        <p><strong><?php echo esc_html__('Ngân hàng', 'ai-gemini-image'); ?>:</strong> <?php echo esc_html($config['bank_id']); ?></p>
                        <p><strong><?php echo esc_html__('Số tài khoản', 'ai-gemini-image'); ?>:</strong> <?php echo esc_html($config['account_no']); ?></p>
                        <p><strong><?php echo esc_html__('Chủ tài khoản', 'ai-gemini-image'); ?>:</strong> <?php echo esc_html($config['account_name']); ?></p>
                        <p><strong><?php echo esc_html__('Số tiền', 'ai-gemini-image'); ?>:</strong> <?php echo esc_html(number_format_i18n($order->amount)); ?>đ</p>
                        <p><strong><?php echo esc_html__('Nội dung', 'ai-gemini-image'); ?>:</strong> AIGC <?php echo esc_html($order->order_code); ?></p>
                    </div>
                </div>
                
                <div class="payment-status" id="payment-status">
                    <div class="status-waiting">
                        <span class="spinner"></span>
                        <span><?php echo esc_html__('Đang chờ thanh toán...', 'ai-gemini-image'); ?></span>
                    </div>
                </div>
                
                <div class="payment-instructions">
                    <h4><?php echo esc_html__('Hướng dẫn thanh toán', 'ai-gemini-image'); ?></h4>
                    <ol>
                        <li><?php echo esc_html__('Mở ứng dụng ngân hàng trên điện thoại', 'ai-gemini-image'); ?></li>
                        <li><?php echo esc_html__('Chọn chức năng Quét QR', 'ai-gemini-image'); ?></li>
                        <li><?php echo esc_html__('Quét mã QR hiển thị ở trên', 'ai-gemini-image'); ?></li>
                        <li><?php echo esc_html__('Kiểm tra số tiền và nội dung chuyển khoản', 'ai-gemini-image'); ?></li>
                        <li><?php echo esc_html__('Hoàn tất chuyển khoản', 'ai-gemini-image'); ?></li>
                        <li><?php echo esc_html__('Chờ hệ thống xác nhận (1–2 phút)', 'ai-gemini-image'); ?></li>
                    </ol>
                </div>
            </div>
            
            <div class="vietqr-footer">
                <a href="<?php echo esc_url(home_url()); ?>" class="btn-back"><?php echo esc_html__('← Quay lại trang chủ', 'ai-gemini-image'); ?></a>
            </div>
        </div>
        
        <script>
        (function() {
            var orderCode = '<?php echo esc_js($order->order_code); ?>';
            var checkInterval = <?php echo esc_js($config['check_interval'] * 1000); ?>;
            var maxWaitTime = <?php echo esc_js($config['max_wait_time'] * 1000); ?>;
            var startTime = Date.now();
            
            function checkPaymentStatus() {
                if (Date.now() - startTime > maxWaitTime) {
                    document.getElementById('payment-status').innerHTML = 
                        '<div class="status-timeout"><?php echo esc_js(__('Hết thời gian chờ. Vui lòng tải lại trang hoặc liên hệ hỗ trợ.', 'ai-gemini-image')); ?></div>';
                    return;
                }
                
                fetch('<?php echo esc_url(rest_url('ai/v1/credit/order/')); ?>' + orderCode)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.status === 'completed') {
                            document.getElementById('payment-status').innerHTML = 
                                '<div class="status-success">✓ <?php echo esc_js(__('Thanh toán thành công! Đang chuyển hướng...', 'ai-gemini-image')); ?></div>';
                            setTimeout(function() {
                                window.location.href = '<?php echo esc_url(home_url('?payment=success&order=')); ?>' + orderCode;
                            }, 2000);
                        } else {
                            setTimeout(checkPaymentStatus, checkInterval);
                        }
                    })
                    .catch(function() {
                        setTimeout(checkPaymentStatus, checkInterval);
                    });
            }
            
            setTimeout(checkPaymentStatus, checkInterval);
        })();
        </script>
        
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}

/**
 * Flush rewrite rules khi kích hoạt plugin
 */
function ai_gemini_flush_vietqr_rules() {
    ai_gemini_register_vietqr_endpoint();
    flush_rewrite_rules();
}
register_activation_hook(AI_GEMINI_PLUGIN_DIR . 'ai-gemini-image.php', 'ai_gemini_flush_vietqr_rules');
