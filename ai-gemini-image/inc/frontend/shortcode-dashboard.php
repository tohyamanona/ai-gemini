<?php
/**
 * AI Gemini Image Generator - Dashboard Shortcode
 * 
 * Shortcode cho trang quản lý tài khoản: lịch sử ảnh và số dư credit.
 */

if (!defined('ABSPATH')) exit;

/**
 * Đăng ký shortcode dashboard
 */
function ai_gemini_register_dashboard_shortcode() {
    add_shortcode('ai_gemini_dashboard', 'ai_gemini_dashboard_shortcode');
}
add_action('init', 'ai_gemini_register_dashboard_shortcode');

/**
 * Render shortcode dashboard
 * 
 * @param array $atts Thuộc tính shortcode
 * @return string HTML output
 */
function ai_gemini_dashboard_shortcode($atts) {
    $atts = shortcode_atts([
        'show_history'  => 'true',
        'history_limit' => 20,
    ], $atts, 'ai_gemini_dashboard');
    
    // Enqueue styles
    wp_enqueue_style(
        'ai-gemini-dashboard',
        AI_GEMINI_PLUGIN_URL . 'assets/css/generator.css',
        [],
        AI_GEMINI_VERSION
    );
    
    $user_id = get_current_user_id();
    $is_guest = !$user_id;

    // Thông tin credit & thống kê (hoạt động cho cả guest: dùng IP khi user_id = 0)
    $credits         = ai_gemini_get_credit($user_id ?: null);
    $total_spent     = ai_gemini_get_total_spent($user_id ?: null);
    $total_purchased = ai_gemini_get_total_purchased($user_id ?: null);
    
    // Lấy danh sách ảnh (theo user_id hoặc guest_ip)
    $images          = ai_gemini_get_user_images($user_id ?: null, (int) $atts['history_limit']);
    $unlocked_count  = 0;
    foreach ($images as $img) {
        if (!empty($img->is_unlocked)) {
            $unlocked_count++;
        }
    }

    // Thông tin trial còn lại (để show message gợi ý)
    $user_trial_limit  = (int) get_option('ai_gemini_user_trial_limit', 1);
    $guest_trial_limit = (int) get_option('ai_gemini_guest_trial_limit', 1);

    $trial_limit = $is_guest ? $guest_trial_limit : $user_trial_limit;
    $trial_count = ai_gemini_get_trial_count($user_id ?: null);
    $trial_left  = max(0, $trial_limit - $trial_count);
    
    ob_start();
    ?>
    <div class="ai-gemini-dashboard">

        <div class="dashboard-header">
            <h2><?php esc_html_e('Bảng điều khiển AI Gemini của bạn', 'ai-gemini-image'); ?></h2>

            <?php if ($is_guest) : ?>
                <div class="ai-gemini-dashboard-notice ai-gemini-dashboard-notice-guest">
                    <p>
                        <?php
                        if ($guest_trial_limit > 0) {
                            printf(
                                /* translators: 1: remaining guest trials */
                                esc_html__(
                                    'Bạn đang sử dụng với tư cách khách (guest). Bạn còn khoảng %d lượt thử miễn phí (tính theo IP).',
                                    'ai-gemini-image'
                                ),
                                intval($trial_left)
                            );
                        } else {
                            esc_html_e(
                                'Bạn đang sử dụng với tư cách khách (guest). Hiện tại khách không có lượt thử miễn phí.',
                                'ai-gemini-image'
                            );
                        }
                        ?>
                    </p>
                    <p>
                        <?php
                        if ($user_trial_limit > 0) {
                            printf(
                                /* translators: 1: user trial limit */
                                esc_html__(
                                    'Đăng ký / đăng nhập để nhận tối đa %d lượt thử miễn phí cho mỗi tài khoản và lưu lịch sử ảnh của bạn.',
                                    'ai-gemini-image'
                                ),
                                intval($user_trial_limit)
                            );
                        } else {
                            esc_html_e(
                                'Đăng ký / đăng nhập để lưu lịch sử ảnh và quản lý credit dễ dàng hơn.',
                                'ai-gemini-image'
                            );
                        }
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="ai-gemini-dashboard-notice ai-gemini-dashboard-notice-user">
                    <?php
                    $current_user = wp_get_current_user();
                    ?>
                    <p>
                        <?php
                        printf(
                            /* translators: 1: display name */
                            esc_html__('Xin chào, %s!', 'ai-gemini-image'),
                            esc_html($current_user->display_name ?: $current_user->user_login)
                        );
                        ?>
                    </p>
                    <?php if ($user_trial_limit > 0) : ?>
                        <p>
                            <?php
                            printf(
                                /* translators: 1: remaining trials, 2: total limit */
                                esc_html__(
                                    'Bạn còn %1$d / %2$d lượt thử miễn phí (nếu không đủ credit, hệ thống sẽ dùng trial).',
                                    'ai-gemini-image'
                                ),
                                intval($trial_left),
                                intval($user_trial_limit)
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_guest) : ?>
            <div class="ai-gemini-dashboard-auth">
                <?php
                // Nếu đã load file shortcode-account và có hàm render login/register → dùng lại
                if (function_exists('ai_gemini_render_login_register_forms')) {
                    ai_gemini_render_login_register_forms();
                } else {
                    // Fallback: hiển thị link tới trang đăng nhập mặc định của WP
                    ?>
                    <p>
                        <?php esc_html_e('Để có trải nghiệm tốt hơn, bạn nên đăng nhập hoặc đăng ký tài khoản.', 'ai-gemini-image'); ?>
                    </p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                            <?php esc_html_e('Đăng nhập', 'ai-gemini-image'); ?>
                        </a>
                        <?php if (get_option('users_can_register')) : ?>
                            <a class="button" href="<?php echo esc_url(wp_registration_url()); ?>">
                                <?php esc_html_e('Đăng ký', 'ai-gemini-image'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                    <?php
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon">💳</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo esc_html(number_format_i18n($credits)); ?></span>
                    <span class="stat-label"><?php esc_html_e('Credit hiện có', 'ai-gemini-image'); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🖼️</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo esc_html(count($images)); ?></span>
                    <span class="stat-label"><?php esc_html_e('Số ảnh đã tạo', 'ai-gemini-image'); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔓</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo esc_html($unlocked_count); ?></span>
                    <span class="stat-label"><?php esc_html_e('Ảnh đã mở khóa', 'ai-gemini-image'); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo esc_html(number_format_i18n($total_spent)); ?></span>
                    <span class="stat-label"><?php esc_html_e('Tổng credit đã dùng', 'ai-gemini-image'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="dashboard-actions">
            <a href="<?php echo esc_url(home_url('/generate')); ?>" class="btn-primary">
                <?php esc_html_e('+ Tạo ảnh mới', 'ai-gemini-image'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/buy-credit')); ?>" class="btn-secondary">
                <?php esc_html_e('Mua thêm credit', 'ai-gemini-image'); ?>
            </a>
        </div>
        
        <?php if ($atts['show_history'] === 'true') : ?>
        <div class="dashboard-history">
            <h3><?php esc_html_e('Lịch sử ảnh của bạn', 'ai-gemini-image'); ?></h3>
            
            <?php if (!empty($images)) : ?>
                <div class="image-gallery">
                    <?php foreach ($images as $image) : ?>
                        <div class="gallery-item <?php echo !empty($image->is_unlocked) ? 'unlocked' : 'locked'; ?>">
                            <div class="gallery-image">
                                <?php if (!empty($image->is_unlocked) && !empty($image->full_image_url)) : ?>
                                    <img src="<?php echo esc_url($image->full_image_url); ?>" alt="<?php esc_attr_e('Ảnh đã tạo', 'ai-gemini-image'); ?>">
                                <?php elseif (!empty($image->preview_image_url)) : ?>
                                    <img src="<?php echo esc_url($image->preview_image_url); ?>" alt="<?php esc_attr_e('Ảnh xem trước', 'ai-gemini-image'); ?>">
                                    <div class="locked-overlay">
                                        <span class="lock-icon">🔒</span>
                                    </div>
                                <?php else : ?>
                                    <div class="no-image">
                                        <span><?php esc_html_e('Ảnh đã hết hạn', 'ai-gemini-image'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="gallery-info">
                                <span class="gallery-style">
                                    <?php
                                    $style_label = !empty($image->style) ? $image->style : __('Tùy chỉnh', 'ai-gemini-image');
                                    echo esc_html($style_label);
                                    ?>
                                </span>
                                <span class="gallery-date">
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($image->created_at))); ?>
                                </span>
                                <?php if (!empty($image->is_unlocked)) : ?>
                                    <span class="unlocked-badge"><?php esc_html_e('Đã mở khóa', 'ai-gemini-image'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($image->is_unlocked) && !empty($image->full_image_url)) : ?>
                                <a href="<?php echo esc_url($image->full_image_url); ?>" class="btn-download" download>
                                    <?php esc_html_e('Tải ảnh', 'ai-gemini-image'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="no-history">
                    <p><?php esc_html_e('Bạn chưa tạo ảnh nào.', 'ai-gemini-image'); ?></p>
                    <a href="<?php echo esc_url(home_url('/generate')); ?>" class="btn-primary">
                        <?php esc_html_e('Tạo ảnh đầu tiên của bạn', 'ai-gemini-image'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="dashboard-transactions">
            <h3><?php esc_html_e('Giao dịch gần đây', 'ai-gemini-image'); ?></h3>
            
            <?php 
            $transactions = ai_gemini_get_credit_history($user_id ?: null, 10);
            if (!empty($transactions)) : 
            ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Thời gian', 'ai-gemini-image'); ?></th>
                            <th><?php esc_html_e('Loại', 'ai-gemini-image'); ?></th>
                            <th><?php esc_html_e('Số lượng', 'ai-gemini-image'); ?></th>
                            <th><?php esc_html_e('Mô tả', 'ai-gemini-image'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction) : ?>
                            <tr>
                                <td>
                                    <?php
                                    echo esc_html(
                                        date_i18n(
                                            get_option('date_format') . ' ' . get_option('time_format'),
                                            strtotime($transaction->created_at)
                                        )
                                    );
                                    ?>
                                </td>
                                <td>
                                    <span class="transaction-type type-<?php echo esc_attr($transaction->type); ?>">
                                        <?php
                                        echo esc_html(ucfirst(str_replace('_', ' ', $transaction->type)));
                                        ?>
                                    </span>
                                </td>
                                <td class="<?php echo $transaction->amount >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo $transaction->amount >= 0 ? '+' : ''; ?>
                                    <?php echo esc_html($transaction->amount); ?>
                                </td>
                                <td><?php echo esc_html($transaction->description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="no-transactions"><?php esc_html_e('Chưa có giao dịch nào.', 'ai-gemini-image'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}