<?php
/**
 * AI Gemini Image Generator - Credit Packages (Option-based)
 *
 * Lưu & đọc gói tín dụng từ option, sau đó override hàm
 * ai_gemini_get_credit_packages() thông qua filter.
 */

if (!defined('ABSPATH')) exit;

/**
 * Lấy gói tín dụng từ option (thô, không qua filter API)
 *
 * @return array
 */
function ai_gemini_get_credit_packages_from_option() {
    $packages = get_option('ai_gemini_credit_packages', []);

    if (!is_array($packages)) {
        $packages = [];
    }

    // Sắp xếp
    usort($packages, function($a, $b) {
        $ao = isset($a['sort_order']) ? (int) $a['sort_order'] : 0;
        $bo = isset($b['sort_order']) ? (int) $b['sort_order'] : 0;

        if ($ao === $bo) {
            return ((int)($a['price'] ?? 0)) <=> ((int)($b['price'] ?? 0));
        }

        return $ao <=> $bo;
    });

    return $packages;
}

/**
 * Lưu gói tín dụng vào option
 *
 * @param array $packages
 * @return bool
 */
function ai_gemini_save_credit_packages_to_option($packages) {
    if (!is_array($packages)) {
        return false;
    }

    $clean = [];

    foreach ($packages as $pkg) {
        $clean[] = [
            'id'          => sanitize_key($pkg['id'] ?? uniqid('pkg_')),
            'name'        => sanitize_text_field($pkg['name'] ?? ''),
            'credits'     => max(0, (int)($pkg['credits'] ?? 0)),
            'price'       => max(0, (int)($pkg['price'] ?? 0)),
            'description' => sanitize_textarea_field($pkg['description'] ?? ''),
            'sort_order'  => (int)($pkg['sort_order'] ?? 0),
            'is_active'   => !empty($pkg['is_active']) ? 1 : 0,
        ];
    }

    return update_option('ai_gemini_credit_packages', $clean);
}

/**
 * Lấy 1 gói theo ID từ option
 *
 * @param string $id
 * @return array|null
 */
function ai_gemini_get_credit_package_from_option($id) {
    foreach (ai_gemini_get_credit_packages_from_option() as $pkg) {
        if (!empty($pkg['id']) && $pkg['id'] === $id) {
            return $pkg;
        }
    }
    return null;
}

/**
 * Filter override danh sách package mặc định của API bằng dữ liệu từ option
 */
add_filter('ai_gemini_credit_packages', function($default_packages) {
    $option_packages = ai_gemini_get_credit_packages_from_option();

    // Nếu chưa cấu hình gì trong option thì dùng default
    if (empty($option_packages)) {
        return $default_packages;
    }

    // Chuẩn hoá: thêm price_formatted / popular nếu cần
    $result = [];
    foreach ($option_packages as $pkg) {
        $price = (int) ($pkg['price'] ?? 0);

        $result[] = [
            'id'              => $pkg['id'] ?? '',
            'name'            => $pkg['name'] ?? '',
            'credits'         => (int) ($pkg['credits'] ?? 0),
            'price'           => $price,
            'price_formatted' => number_format_i18n($price) . 'đ',
            'popular'         => !empty($pkg['popular']) || !empty($pkg['is_active']) ? (bool) $pkg['is_active'] : false,
            'description'     => $pkg['description'] ?? '',
            'sort_order'      => (int) ($pkg['sort_order'] ?? 0),
            'is_active'       => !empty($pkg['is_active']) ? 1 : 0,
        ];
    }

    return $result;
});