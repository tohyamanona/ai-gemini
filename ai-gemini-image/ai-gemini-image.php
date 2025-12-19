<?php
/**
 * Plugin Name: AI Gemini Image Generator
 * Description: Plugin tạo hình ảnh bằng Google Gemini 2.5 Flash Image với hệ thống credit và nhiệm vụ
 * Version: 1.0.1
 * Author: Your Name
 * Text Domain: ai-gemini-image
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('AI_GEMINI_VERSION', '1.0.1');
define('AI_GEMINI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_GEMINI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * URL time server chuẩn dùng cho TOTP
 * Bạn đã triển khai endpoint này trên VPS:
 *   https://khungtranhtreotuong.com/api-time.php
 * Trả về JSON: {"timestamp":1764645274,"datetime":"2025-12-02T03:14:34+00:00"}
 */
if (!defined('AI_GEMINI_TIME_SERVER_URL')) {
    define('AI_GEMINI_TIME_SERVER_URL', 'https://khungtranhtreotuong.com/api-time.php');
}

// Autoload files in organized order
$ai_gemini_includes = [
    // Database
    'inc/db/install.php',
    'inc/db/cleanup.php',
    
    // Admin
    'inc/admin/menu.php',
    'inc/admin/credit-manager.php',
    'inc/admin/prompt-manager.php',
    'inc/admin/mission-manager.php', // <--- FILE MỚI: Quản lý nhiệm vụ
    
    // API
    'inc/api/class-gemini-api.php',
    'inc/api/preview.php',
    'inc/api/unlock.php',
    'inc/api/credit.php',
    'inc/api/mission.php',
    'inc/api/download.php',
    
    // Credit System
    'inc/credit/functions.php',
    'inc/credit/tables.php',
    'inc/credit/ajax.php',
    'inc/credit/packages.php',
    'inc/credit/credit-packages.php',
    
    // Payment
    'inc/payment/vietqr-config.php',
    'inc/payment/vietqr-payment.php',
    'inc/payment/vietqr-checker.php',
    
    // Frontend
    'inc/frontend/shortcode-generator.php',
    'inc/frontend/shortcode-dashboard.php',
    'inc/frontend/shortcode-credit.php',
    'inc/frontend/shortcode-account.php',
    
    // Utilities
    'inc/watermark.php',
    'inc/helpers.php',
];

foreach ($ai_gemini_includes as $file) {
    $filepath = AI_GEMINI_PLUGIN_DIR . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    }
}

// Activation hook
register_activation_hook(__FILE__, 'ai_gemini_install_tables');

// Deactivation hook
register_deactivation_hook(__FILE__, 'ai_gemini_cleanup_on_deactivate');

/**
 * -------------------------------------------------------------------------
 *  DISABLE CACHE FOR PAYMENT & CREDIT STATUS ROUTES
 * -------------------------------------------------------------------------
 * - Không cache REST: /wp-json/ai/v1/credit/order/*
 * - Không cache template: /ai-gemini-pay/*
 *   (phần /ai-gemini-pay chủ yếu cấu hình trong LiteSpeed Cache plugin,
 *    ở đây ta chỉ chắc chắn REST không bị cache thêm nữa)
 */

/**
 * Thêm header no-cache cho REST route kiểm tra đơn hàng credit
 */
add_filter('rest_post_dispatch', function($result, $server, $request) {
    $route = $request->get_route();

    // Chỉ áp dụng cho route: /ai/v1/credit/order/{code}
    if (strpos($route, '/ai/v1/credit/order/') === 0) {
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    return $result;
}, 10, 3);