<?php
/**
 * AI Gemini Image Generator - VietQR Payment Checker
 */

if (!defined('ABSPATH')) exit;

/**
 * ============================================================================
 *  SIMPLE ENCRYPT / DECRYPT HELPERS
 * ============================================================================
 */

function ai_gemini_get_crypto_key() {
    if (defined('AI_GEMINI_CRYPTO_KEY') && AI_GEMINI_CRYPTO_KEY) {
        return (string) AI_GEMINI_CRYPTO_KEY;
    }
    if (defined('AUTH_KEY') && AUTH_KEY) {
        return (string) AUTH_KEY;
    }
    return 'ai_gemini_default_crypto_key_change_me';
}

function ai_gemini_encrypt($plain) {
    $plain = (string) $plain;
    if ($plain === '') {
        return '';
    }

    $key = hash('sha256', ai_gemini_get_crypto_key(), true); // 32 bytes
    $iv  = random_bytes(12); // 96-bit IV cho AES-256-GCM

    $cipher = openssl_encrypt(
        $plain,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($cipher === false) {
        return '';
    }

    return base64_encode($iv . '::' . $cipher . '::' . $tag);
}

function ai_gemini_decrypt($encoded) {
    $encoded = (string) $encoded;
    if ($encoded === '') {
        return '';
    }

    $raw = base64_decode($encoded, true);
    if ($raw === false) {
        return '';
    }

    $parts = explode('::', $raw);
    if (count($parts) !== 3) {
        return '';
    }

    list($iv, $cipher, $tag) = $parts;
    if (strlen($iv) !== 12) {
        return '';
    }

    $key = hash('sha256', ai_gemini_get_crypto_key(), true);

    $plain = openssl_decrypt(
        $cipher,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plain === false) {
        return '';
    }

    return $plain;
}

/**
 * Test Node service (xem lịch sử trong admin)
 */
function ai_gemini_test_bank_connection($username, $password_plain, $account) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Bạn không có quyền thực hiện thao tác này.');
    }

    $account = trim($account);
    if ($account === '') {
        return new WP_Error('missing_fields', 'Thiếu Số tài khoản.');
    }

    $service_url = defined('AIGEMINI_NODE_SERVICE_URL')
        ? AIGEMINI_NODE_SERVICE_URL
        : 'http://127.0.0.1:3000/acb/history';

    $internal_secret = defined('AIGEMINI_NODE_INTERNAL_SECRET')
        ? AIGEMINI_NODE_INTERNAL_SECRET
        : 'CHANGE_THIS_SECRET';

    $response = wp_remote_post($service_url, [
        'timeout' => 30,
        'headers' => [
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
            'X-Internal-Secret' => $internal_secret,
        ],
        'body'    => wp_json_encode([
            'account' => $account,
        ]),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error(
            'service_failed',
            'Không thể kết nối tới service Node: ' . $response->get_error_message()
        );
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($code !== 200 || !is_array($data) || empty($data['success'])) {
        return new WP_Error(
            'service_error',
            'Service Node trả về lỗi. HTTP ' . $code . ' - ' . substr($body, 0, 200)
        );
    }

    return $data['raw'];
}

/**
 * Webhook (nếu sau này Node/bên thứ 3 bắn về trực tiếp).
 */
function ai_gemini_register_payment_webhook() {
    register_rest_route('ai/v1', '/payment/webhook', [
        'methods'             => 'POST',
        'callback'            => 'ai_gemini_handle_payment_webhook',
        'permission_callback' => 'ai_gemini_verify_webhook_signature',
    ]);
}
add_action('rest_api_init', 'ai_gemini_register_payment_webhook');

function ai_gemini_verify_webhook_signature($request) {
    $signature = $request->get_header('X-Webhook-Signature');
    
    if (!$signature) {
        ai_gemini_log('Webhook request rejected: missing signature', 'warning');
        return new WP_Error('missing_signature', 'Webhook signature missing', ['status' => 401]);
    }
    
    $config = ai_gemini_get_vietqr_config();
    $secret = $config['api_secret'];
    
    if (empty($secret)) {
        return new WP_Error('not_configured', 'Webhook secret not configured', ['status' => 500]);
    }
    
    $body = $request->get_body();
    $expected_signature = hash_hmac('sha256', $body, $secret);
    
    if (!hash_equals($expected_signature, $signature)) {
        return new WP_Error('invalid_signature', 'Invalid webhook signature', ['status' => 401]);
    }
    
    return true;
}

function ai_gemini_handle_payment_webhook($request) {
    $data = $request->get_json_params();
    ai_gemini_log('Payment webhook received: ' . wp_json_encode($data), 'info');
    
    $amount         = isset($data['amount']) ? (int) $data['amount'] : 0;
    $description    = isset($data['description']) ? sanitize_text_field($data['description']) : '';
    $transaction_id = isset($data['transaction_id']) ? sanitize_text_field($data['transaction_id']) : '';
    
    $order_code = ai_gemini_extract_order_code($description);
    
    if (!$order_code) {
        ai_gemini_log('Could not extract order code from: ' . $description, 'warning');
        return rest_ensure_response(['status' => 'ignored', 'message' => 'No matching order code found']);
    }
    
    global $wpdb;
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';
    
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_orders WHERE order_code = %s AND status = 'pending'",
        $order_code
    ));
    
    if (!$order) {
        ai_gemini_log('No pending order found for code: ' . $order_code, 'warning');
        return rest_ensure_response(['status' => 'ignored', 'message' => 'Order not found or already processed']);
    }
    
    if ($amount !== (int) $order->amount) {
        ai_gemini_log("Amount mismatch for order {$order_code}: expected {$order->amount}, received {$amount}", 'warning');
        return rest_ensure_response(['status' => 'error', 'message' => 'Amount mismatch']);
    }
    
    $success = ai_gemini_complete_order($order_code, $transaction_id);
    
    if ($success) {
        ai_gemini_log("Order {$order_code} completed via webhook", 'info');
        return rest_ensure_response(['status' => 'success', 'message' => 'Order completed']);
    } else {
        ai_gemini_log("Failed to complete order {$order_code}", 'error');
        return rest_ensure_response(['status' => 'error', 'message' => 'Failed to complete order']);
    }
}

/**
 * Tách order_code từ description (ví dụ: "AIGC AG67918C7F ...")
 */
function ai_gemini_extract_order_code($description) {
    if (preg_match('/AIGC\\s*([A-Z0-9]{8,12})/i', $description, $matches)) {
        return strtoupper($matches[1]);
    }
    
    if (preg_match('/AG[0-9A-Z]{8,10}/i', $description, $matches)) {
        return strtoupper($matches[0]);
    }
    
    return false;
}

function ai_gemini_manual_verify_payment($order_code, $transaction_id = '') {
    if (!current_user_can('manage_options')) {
        return false;
    }
    return ai_gemini_complete_order($order_code, $transaction_id);
}

/**
 * Đăng ký cron: mỗi phút
 */
function ai_gemini_schedule_payment_check() {
    if (!wp_next_scheduled('ai_gemini_check_pending_payments')) {
        wp_schedule_event(time(), 'every_minute', 'ai_gemini_check_pending_payments');
        ai_gemini_log('Scheduled ai_gemini_check_pending_payments every_minute', 'info');
    }
}
add_action('init', 'ai_gemini_schedule_payment_check');

function ai_gemini_cron_schedules($schedules) {
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute', 'ai-gemini-image'),
        ];
    }
    return $schedules;
}
add_filter('cron_schedules', 'ai_gemini_cron_schedules');

/**
 * Cron kiểm tra đơn pending qua Node + lịch sử ACB.
 */
function ai_gemini_check_pending_payments() {
    global $wpdb;
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';

    $now_ts = current_time('timestamp');
    ai_gemini_log('Cron ai_gemini_check_pending_payments started', 'info');

    // 1) Hủy đơn pending quá 30 phút
    $expired_orders = $wpdb->get_results(
        "SELECT * FROM $table_orders
         WHERE status = 'pending'
           AND created_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
    );
    foreach ($expired_orders as $order) {
        $wpdb->update(
            $table_orders,
            ['status' => 'canceled'],
            ['id' => $order->id],
            ['%s'],
            ['%d']
        );
        ai_gemini_log("Order {$order->order_code} auto-canceled after 30 minutes.", 'info');
    }

    // 2) Lấy tất cả đơn pending trong 30 phút gần nhất
    $pending_orders = $wpdb->get_results(
        "SELECT * FROM $table_orders
         WHERE status = 'pending'
           AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
    );
    if (empty($pending_orders)) {
        ai_gemini_log('No pending orders in last 30 minutes.', 'info');
        return;
    }

    ai_gemini_log('Pending orders: ' . count($pending_orders), 'info');

    // 3) Gọi Node service lấy lịch sử một lần
    $service_url = defined('AIGEMINI_NODE_SERVICE_URL')
        ? AIGEMINI_NODE_SERVICE_URL
        : 'http://127.0.0.1:3000/acb/history';

    $internal_secret = defined('AIGEMINI_NODE_INTERNAL_SECRET')
        ? AIGEMINI_NODE_INTERNAL_SECRET
        : 'CHANGE_THIS_SECRET';

    $account = get_option('ai_gemini_bank_account', '');
    if (empty($account)) {
        ai_gemini_log('Bank account is not configured, skip checking.', 'warning');
        return;
    }

    $response = wp_remote_post($service_url, [
        'timeout' => 20,
        'headers' => [
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
            'X-Internal-Secret' => $internal_secret,
        ],
        'body'    => wp_json_encode([
            'account' => $account,
        ]),
    ]);

    if (is_wp_error($response)) {
        ai_gemini_log('Node service error: ' . $response->get_error_message(), 'error');
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    ai_gemini_log('Node raw response in cron: ' . substr($body, 0, 500), 'info');

    if ($code !== 200 || !is_array($data) || empty($data['success'])) {
        ai_gemini_log(
            'Node service HTTP error: ' . $code . ' body=' . substr($body, 0, 200),
            'warning'
        );
        return;
    }

    // Lấy mảng transaction từ raw.data
    $transactions = [];
    if (isset($data['raw']['data']) && is_array($data['raw']['data'])) {
        $transactions = $data['raw']['data'];
    }

    ai_gemini_log('Transactions fetched: ' . count($transactions), 'info');

    if (empty($transactions)) {
        ai_gemini_log('No transactions found in history.', 'info');
        return;
    }

    // Log nhanh 3 giao dịch đầu để nhìn rõ dữ liệu
    $debug_tx = array_slice($transactions, 0, 3);
    ai_gemini_log('Sample TX: ' . wp_json_encode($debug_tx), 'info');

    // 4) Dò từng order trong danh sách giao dịch
    foreach ($pending_orders as $order) {
        $order_amount = (int) $order->amount;
        $order_code   = $order->order_code;

        $created_ts = strtotime($order->created_at);
        if (!$created_ts || ($now_ts - $created_ts) > 30 * 60) {
            continue;
        }

        ai_gemini_log(
            "Checking order {$order_code} (amount={$order_amount}, created_at={$order->created_at})",
            'info'
        );

        $matched_tx = null;

        foreach ($transactions as $tx) {
            $tx_amount      = isset($tx['amount']) ? (int) $tx['amount'] : 0;
            $tx_description = isset($tx['description']) ? (string) $tx['description'] : '';
            $tx_id          = isset($tx['transactionNumber']) ? (string) $tx['transactionNumber'] : '';
            $tx_account     = isset($tx['account']) ? (string) $tx['account'] : '';

            ai_gemini_log(
                "TX candidate for {$order_code}: amount={$tx_amount}, account={$tx_account}, desc=" . $tx_description,
                'info'
            );

            // 1) Check tài khoản
            if ($tx_account !== '' && $tx_account !== (string) $account) {
                continue;
            }

            // 2) Check số tiền
            if ($tx_amount !== $order_amount) {
                continue;
            }

            // 3) Check nội dung chứa order_code (vd "AIGC AG80D9E2D4 GD ...")
            if (stripos($tx_description, $order_code) === false) {
                continue;
            }

            ai_gemini_log(
                "Matched TX for order {$order_code}: TX_ID={$tx_id}, DESC={$tx_description}",
                'info'
            );

            $matched_tx = [
                'id'          => $tx_id ?: 'N/A',
                'description' => $tx_description,
            ];
            break;
        }

        if ($matched_tx) {
            $tx_id = sanitize_text_field($matched_tx['id']);

            $success = ai_gemini_complete_order($order_code, $tx_id);
            if ($success) {
                ai_gemini_log(
                    "Order {$order_code} completed via polling. TX_ID={$tx_id}",
                    'info'
                );
            } else {
                ai_gemini_log("Failed to complete order {$order_code} via polling.", 'error');
            }
        } else {
            ai_gemini_log("No matching TX found for order {$order_code}", 'info');
        }
    }
}
add_action('ai_gemini_check_pending_payments', 'ai_gemini_check_pending_payments');

/**
 * Không dùng nữa (đã thay bằng Node)
 */
function ai_gemini_verify_payment_with_bank($order) {
    return false;
}

/**
 * Clear schedule khi deactivate plugin.
 */
function ai_gemini_clear_payment_schedule() {
    wp_clear_scheduled_hook('ai_gemini_check_pending_payments');
}
register_deactivation_hook(AI_GEMINI_PLUGIN_DIR . 'ai-gemini-image.php', 'ai_gemini_clear_payment_schedule');