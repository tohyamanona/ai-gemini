<?php
/**
 * AI Gemini Image Generator - Mã Mua Tín Dụng
 * 
 * Mã ngắn cho trang mua tín dụng.
 */

if (!defined('ABSPATH')) exit;

/**
 * Đăng ký mã ngắn mua tín dụng
 */
function ai_gemini_register_credit_shortcode() {
    add_shortcode('ai_gemini_buy_credit', 'ai_gemini_credit_shortcode');
}
add_action('init', 'ai_gemini_register_credit_shortcode');

/**
 * Hiển thị mã ngắn mua tín dụng
 * 
 * @param array $atts Thuộc tính mã ngắn
 * @return string HTML đầu ra
 */
function ai_gemini_credit_shortcode($atts) {
    $atts = shortcode_atts([
        'columns' => 4,
    ], $atts, 'ai_gemini_buy_credit');
    
    // Nạp style
    wp_enqueue_style(
        'ai-gemini-credit',
        AI_GEMINI_PLUGIN_URL . 'assets/css/credit.css',
        [],
        AI_GEMINI_VERSION
    );

    // Đảm bảo jQuery & script frontend được load
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'ai-gemini-credit-js',
        AI_GEMINI_PLUGIN_URL . 'assets/js/credit.js',
        ['jquery'],
        AI_GEMINI_VERSION,
        true // load ở footer
    );

    // Truyền cấu hình sang JS
    wp_localize_script('ai-gemini-credit-js', 'AIGeminiCredit', [
        'rest_url_order' => rest_url('ai/v1/credit/order'),
        'pay_base_url'   => home_url('/ai-gemini-pay/'),
        'nonce'          => wp_create_nonce('wp_rest'),
        'i18n_processing'=> __('Đang xử lý...', 'ai-gemini-image'),
        'i18n_select'    => __('Chọn Gói', 'ai-gemini-image'),
        'i18n_error'     => __('Đã có lỗi xảy ra. Vui lòng thử lại.', 'ai-gemini-image'),
    ]);
    
    // Lấy thông tin người dùng
    $user_id = get_current_user_id();
    $credits = ai_gemini_get_credit($user_id ?: null);
    
    // Lấy gói tín dụng
    $packages = ai_gemini_get_credit_packages();
    
    // Kiểm tra cấu hình VietQR
    $vietqr_valid = ai_gemini_validate_vietqr_config();
    
    ob_start();
    ?>
    <div class="ai-gemini-credit-page">
        <div class="credit-header">
            <h2><?php esc_html_e('Mua Tín Dụng', 'ai-gemini-image'); ?></h2>
            <p><?php esc_html_e('Mua tín dụng để mở khóa hình ảnh AI chất lượng cao.', 'ai-gemini-image'); ?></p>
            <div class="current-balance">
                <?php esc_html_e('Số dư hiện tại:', 'ai-gemini-image'); ?>
                <strong><?php echo esc_html(number_format_i18n($credits)); ?></strong>
                <?php esc_html_e('tín dụng', 'ai-gemini-image'); ?>
            </div>
        </div>
        
        <?php if (!$vietqr_valid['valid']) : ?>
            <div class="credit-error">
                <p><?php esc_html_e('Hệ thống thanh toán chưa được cấu hình. Vui lòng liên hệ với quản trị viên.', 'ai-gemini-image'); ?></p>
            </div>
        <?php else : ?>
            <div class="credit-packages" style="--columns: <?php echo esc_attr($atts['columns']); ?>;">
                <?php foreach ($packages as $package) : ?>
                    <div class="package-card <?php echo !empty($package['popular']) ? 'popular' : ''; ?>" data-package-id="<?php echo esc_attr($package['id']); ?>">
                        <?php if (!empty($package['popular'])) : ?>
                            <div class="popular-badge"><?php esc_html_e('Phổ Biến Nhất', 'ai-gemini-image'); ?></div>
                        <?php endif; ?>
                        
                        <div class="package-name"><?php echo esc_html($package['name']); ?></div>
                        
                        <div class="package-credits">
                            <span class="credits-number"><?php echo esc_html(number_format_i18n($package['credits'])); ?></span>
                            <span class="credits-label"><?php esc_html_e('tín dụng', 'ai-gemini-image'); ?></span>
                        </div>
                        
                        <div class="package-price">
                            <?php echo esc_html($package['price_formatted']); ?>
                        </div>
                        
                        <div class="package-rate">
                            <?php 
                            $rate = $package['price'] / $package['credits'];
                            printf(esc_html__('%sđ mỗi tín dụng', 'ai-gemini-image'), number_format_i18n($rate));
                            ?>
                        </div>
                        
                        <button type="button" class="btn-select-package" data-package="<?php echo esc_attr($package['id']); ?>">
                            <?php esc_html_e('Chọn Gói', 'ai-gemini-image'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="payment-info">
                <h3><?php esc_html_e('Cách Thanh Toán', 'ai-gemini-image'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Chọn một gói tín dụng phía trên', 'ai-gemini-image'); ?></li>
                    <li><?php esc_html_e('Quét mã VietQR bằng ứng dụng ngân hàng của bạn', 'ai-gemini-image'); ?></li>
                    <li><?php esc_html_e('Hoàn tất chuyển khoản với số tiền và mô tả chính xác', 'ai-gemini-image'); ?></li>
                    <li><?php esc_html_e('Tín dụng sẽ được cộng tự động trong vòng 1-2 phút', 'ai-gemini-image'); ?></li>
                </ol>
            </div>
            
            <div class="payment-methods">
                <h4><?php esc_html_e('Các Ngân Hàng Hỗ Trợ', 'ai-gemini-image'); ?></h4>
                <p><?php esc_html_e('Hỗ trợ tất cả các ngân hàng lớn tại Việt Nam có hỗ trợ VietQR.', 'ai-gemini-image'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}
