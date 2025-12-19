<?php
/**
 * AI Gemini Image Generator - Credit Functions
 *
 * Hàm xử lý hệ thống credit, bao gồm cộng/trừ credit, log giao dịch
 * và hoàn tất đơn hàng VietQR.
 */

if (!defined('ABSPATH')) exit;

/**
 * Initialize default credit settings on plugin activation
 */
function ai_gemini_init_credit_settings() {
    add_option('ai_gemini_preview_credit', 0);
    add_option('ai_gemini_unlock_credit', 1);
    add_option('ai_gemini_free_trial_credits', 5);
}

/**
 * Grant free trial credits to user or guest
 * 
 * @param int|null $user_id User ID or null for guest
 * @return bool Success status
 */
function ai_gemini_grant_free_trial($user_id = null) {
    $free_credits = (int) get_option('ai_gemini_free_trial_credits', 5);
    
    if ($free_credits <= 0) {
        return false;
    }
    
    // Mark trial as used
    ai_gemini_mark_trial_used($user_id);
    
    // Add credits
    ai_gemini_update_credit($free_credits, $user_id);
    
    // Log transaction (hàm này đã được định nghĩa ở file khác)
    if (function_exists('ai_gemini_log_transaction')) {
        ai_gemini_log_transaction([
            'user_id'      => $user_id,
            'guest_ip'     => $user_id ? null : ai_gemini_get_client_ip(),
            'type'         => 'purchase',
            'amount'       => $free_credits,
            'description'  => __('Free trial credits', 'ai-gemini-image'),
            'reference_id' => null,
        ]);
    }
    
    return true;
}

/**
 * Check if user can afford an action
 * 
 * @param int $cost Credit cost
 * @param int|null $user_id User ID or null for guest
 * @return bool True if can afford
 */
function ai_gemini_can_afford($cost, $user_id = null) {
    $credits = ai_gemini_get_credit($user_id);
    return $credits >= $cost;
}

/**
 * Deduct credits for an action
 * 
 * @param int $cost Credit cost
 * @param string $reason Reason for deduction
 * @param int|null $user_id User ID or null for guest
 * @param int|null $reference_id Optional reference ID
 * @return bool Success status
 */
function ai_gemini_deduct_credits($cost, $reason, $user_id = null, $reference_id = null) {
    if (!ai_gemini_can_afford($cost, $user_id)) {
        return false;
    }
    
    ai_gemini_update_credit(-$cost, $user_id);
    
    // Log transaction
    if (function_exists('ai_gemini_log_transaction')) {
        ai_gemini_log_transaction([
            'user_id'      => $user_id,
            'guest_ip'     => $user_id ? null : ai_gemini_get_client_ip(),
            'type'         => 'deduction',
            'amount'       => -$cost,
            'description'  => $reason,
            'reference_id' => $reference_id,
        ]);
    }
    
    return true;
}

/**
 * Refund credits
 * 
 * @param int $amount Amount to refund
 * @param string $reason Reason for refund
 * @param int|null $user_id User ID or null for guest
 * @param int|null $reference_id Optional reference ID
 * @return bool Success status
 */
function ai_gemini_refund_credits($amount, $reason, $user_id = null, $reference_id = null) {
    ai_gemini_update_credit($amount, $user_id);
    
    // Log transaction
    if (function_exists('ai_gemini_log_transaction')) {
        ai_gemini_log_transaction([
            'user_id'      => $user_id,
            'guest_ip'     => $user_id ? null : ai_gemini_get_client_ip(),
            'type'         => 'refund',
            'amount'       => $amount,
            'description'  => $reason,
            'reference_id' => $reference_id,
        ]);
    }
    
    return true;
}

/**
 * Get user credit history
 * 
 * @param int|null $user_id User ID or null for guest
 * @param int $limit Maximum records to return
 * @param int $offset Offset for pagination
 * @return array Credit transactions
 */
function ai_gemini_get_credit_history($user_id = null, $limit = 20, $offset = 0) {
    global $wpdb;
    
    $table_transactions = $wpdb->prefix . 'ai_gemini_transactions';
    
    if ($user_id) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_transactions WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    } else {
        $ip = ai_gemini_get_client_ip();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_transactions WHERE guest_ip = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $ip,
            $limit,
            $offset
        ));
    }
}

/**
 * Get total credits spent by user
 * 
 * @param int|null $user_id User ID or null for guest
 * @return int Total credits spent
 */
function ai_gemini_get_total_spent($user_id = null) {
    global $wpdb;
    
    $table_transactions = $wpdb->prefix . 'ai_gemini_transactions';
    
    if ($user_id) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(-amount) FROM $table_transactions WHERE user_id = %d AND amount < 0",
            $user_id
        ));
    } else {
        $ip = ai_gemini_get_client_ip();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(-amount) FROM $table_transactions WHERE guest_ip = %s AND amount < 0",
            $ip
        ));
    }
}

/**
 * Get total credits purchased by user or guest
 *
 * @param int|null $user_id User ID or null for guest
 * @return int Total credits purchased
 */
function ai_gemini_get_total_purchased($user_id = null) {
    global $wpdb;

    $table_transactions = $wpdb->prefix . 'ai_gemini_transactions';

    if ($user_id) {
        // Tổng số credits mua cho user (amount > 0, type purchase/credit_purchase)
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_transactions
             WHERE user_id = %d
               AND amount > 0
               AND (type = 'purchase' OR type = 'credit_purchase')",
            $user_id
        ));
    } else {
        // Guest theo IP
        $ip = ai_gemini_get_client_ip();
        if (empty($ip)) {
            return 0;
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_transactions
             WHERE guest_ip = %s
               AND amount > 0
               AND (type = 'purchase' OR type = 'credit_purchase')",
            $ip
        ));
    }

    return (int) $total;
}

/**
 * COMPLETE ORDER (FIXED VERSION)
 *
 * - Đọc order từ bảng ai_gemini_orders theo order_code
 * - Cập nhật status = completed, transaction_id, completed_at
 * - Cộng credits cho:
 *     + user_id nếu có
 *     + hoặc guest theo guest_ip lưu trong order (KHÔNG dùng IP hiện tại của request)
 * - Log transaction vào ai_gemini_transactions (nếu hàm tồn tại)
 *
 * @param string $order_code
 * @param string $transaction_id
 * @return bool
 */
function ai_gemini_complete_order($order_code, $transaction_id = '') {
    global $wpdb;
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';

    // Lấy đơn theo order_code
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_orders} WHERE order_code = %s",
        $order_code
    ));

    if (!$order) {
        ai_gemini_log("complete_order: Order {$order_code} not found", 'warning');
        return false;
    }

    // Nếu đã completed thì không làm lại
    if ($order->status === 'completed') {
        ai_gemini_log("complete_order: Order {$order_code} already completed", 'info');
        return true;
    }

    // Cập nhật trạng thái đơn
    $updated = $wpdb->update(
        $table_orders,
        [
            'status'        => 'completed',
            'transaction_id'=> $transaction_id,
            'completed_at'  => current_time('mysql'),
        ],
        ['id' => $order->id],
        ['%s', '%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        ai_gemini_log("complete_order: Failed to update order {$order_code}", 'error');
        return false;
    }

    $credits_to_add = (int) $order->credits;
    if ($credits_to_add <= 0) {
        ai_gemini_log("complete_order: Order {$order_code} has no credits to add", 'warning');
        return true;
    }

    $user_id  = $order->user_id ? (int) $order->user_id : null;
    $guest_ip = !empty($order->guest_ip) ? $order->guest_ip : null;

    // 1) Cộng credit cho USER nếu có
    if ($user_id) {
        ai_gemini_update_credit($credits_to_add, $user_id);
        $new_balance = ai_gemini_get_credit($user_id);

        if (function_exists('ai_gemini_log_transaction')) {
            ai_gemini_log_transaction([
                'user_id'      => $user_id,
                'guest_ip'     => null,
                'type'         => 'purchase',
                'amount'       => $credits_to_add,
                'balance_after'=> $new_balance,
                'description'  => sprintf('Credits purchased via VietQR, order %s', $order_code),
                'reference_id' => $order->id,
            ]);
        }

        ai_gemini_log("Order {$order_code} completed, added {$credits_to_add} credits to user {$user_id}", 'info');
        return true;
    }

    // 2) Nếu không có user_id nhưng có guest_ip -> cộng cho GUEST THEO IP CỦA ORDER
    if ($guest_ip) {
        $table_guest = $wpdb->prefix . 'ai_gemini_guest_credits';
        $now = current_time('mysql');

        // Đảm bảo bảng tồn tại (site mới cài)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_guest}'") !== $table_guest) {
            ai_gemini_log("complete_order: guest credit table {$table_guest} does not exist", 'error');
            return false;
        }

        $guest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_guest} WHERE ip = %s",
            $guest_ip
        ));

        if ($guest) {
            $new_balance = max(0, (int)$guest->credits + $credits_to_add);
            $wpdb->update(
                $table_guest,
                [
                    'credits'    => $new_balance,
                    'updated_at' => $now,
                ],
                ['ip' => $guest_ip],
                ['%d', '%s'],
                ['%s']
            );
        } else {
            $new_balance = $credits_to_add;
            $wpdb->insert(
                $table_guest,
                [
                    'ip'          => $guest_ip,
                    'credits'     => $new_balance,
                    'used_trial'  => 0,
                    'trial_count' => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                ['%s', '%d', '%d', '%d', '%s', '%s']
            );
        }

        if (function_exists('ai_gemini_log_transaction')) {
            ai_gemini_log_transaction([
                'user_id'      => null,
                'guest_ip'     => $guest_ip,
                'type'         => 'purchase',
                'amount'       => $credits_to_add,
                'balance_after'=> $new_balance,
                'description'  => sprintf('Guest credits purchased via VietQR, order %s', $order_code),
                'reference_id' => $order->id,
            ]);
        }

        ai_gemini_log("Order {$order_code} completed, added {$credits_to_add} credits to guest IP {$guest_ip}", 'info');
        return true;
    }

    // 3) Không có user_id cũng không có guest_ip
    ai_gemini_log("complete_order: Order {$order_code} has neither user_id nor guest_ip", 'warning');
    return true;
}