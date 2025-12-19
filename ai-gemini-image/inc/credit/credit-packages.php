<?php
/**
 * AI Gemini Image Generator - Credit Packages Admin
 *
 * Giao diện admin để tạo/sửa/xóa các gói tín dụng.
 */

if (!defined('ABSPATH')) exit;

/**
 * Trang admin: Credit Packages
 */
function ai_gemini_credit_packages_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Xử lý submit form
    if (isset($_POST['ai_gemini_save_packages']) && check_admin_referer('ai_gemini_save_packages')) {
        $packages_raw = isset($_POST['packages']) ? wp_unslash($_POST['packages']) : [];
        $packages = [];

        if (is_array($packages_raw)) {
            foreach ($packages_raw as $index => $pkg) {
                $name    = $pkg['name']    ?? '';
                $credits = (int)($pkg['credits'] ?? 0);
                $price   = (int)($pkg['price']   ?? 0);

                // Bỏ qua gói không đủ thông tin
                if (empty($name) || $credits <= 0 || $price <= 0) {
                    continue;
                }

                $packages[] = [
                    'id'          => !empty($pkg['id']) ? sanitize_key($pkg['id']) : uniqid('pkg_'),
                    'name'        => sanitize_text_field($name),
                    'credits'     => $credits,
                    'price'       => $price,
                    'description' => sanitize_textarea_field($pkg['description'] ?? ''),
                    'sort_order'  => (int)($pkg['sort_order'] ?? 0),
                    'is_active'   => !empty($pkg['is_active']) ? 1 : 0,
                ];
            }
        }

        // Lưu vào option
        ai_gemini_save_credit_packages_to_option($packages);

        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Credit packages saved.', 'ai-gemini-image')
            . '</p></div>';
    }

    // Lấy danh sách gói từ option (thô)
    $packages = ai_gemini_get_credit_packages_from_option();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Credit Packages', 'ai-gemini-image'); ?></h1>
        <p><?php esc_html_e('Configure the credit packages that users can purchase via VietQR.', 'ai-gemini-image'); ?></p>

        <form method="post">
            <?php wp_nonce_field('ai_gemini_save_packages'); ?>

            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'ai-gemini-image'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Credits', 'ai-gemini-image'); ?></th>
                    <th style="width: 140px;"><?php esc_html_e('Price (₫)', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Description', 'ai-gemini-image'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Order', 'ai-gemini-image'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Active', 'ai-gemini-image'); ?></th>
                    <th style="width: 40px;"><?php esc_html_e('Delete', 'ai-gemini-image'); ?></th>
                </tr>
                </thead>
                <tbody id="ai-gemini-packages-rows">
                <?php if (!empty($packages)) : ?>
                    <?php foreach ($packages as $index => $pkg) : ?>
                        <tr>
                            <td>
                                <input type="hidden" name="packages[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($pkg['id']); ?>">
                                <input type="text" name="packages[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($pkg['name']); ?>" class="regular-text">
                            </td>
                            <td>
                                <input type="number" name="packages[<?php echo esc_attr($index); ?>][credits]" value="<?php echo esc_attr($pkg['credits']); ?>" min="1" class="small-text">
                            </td>
                            <td>
                                <input type="number" name="packages[<?php echo esc_attr($index); ?>][price]" value="<?php echo esc_attr($pkg['price']); ?>" min="1" class="small-text">
                            </td>
                            <td>
                                <textarea name="packages[<?php echo esc_attr($index); ?>][description]" rows="2" class="large-text"><?php echo esc_textarea($pkg['description'] ?? ''); ?></textarea>
                            </td>
                            <td>
                                <input type="number" name="packages[<?php echo esc_attr($index); ?>][sort_order]" value="<?php echo esc_attr($pkg['sort_order'] ?? 0); ?>" class="small-text">
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="packages[<?php echo esc_attr($index); ?>][is_active]" value="1" <?php checked(!empty($pkg['is_active'])); ?>>
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="button ai-gemini-remove-row">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Template row for JS to clone -->
                <tr class="ai-gemini-package-template" style="display:none;">
                    <td>
                        <input type="hidden" data-field="id" value="">
                        <input type="text" data-field="name" class="regular-text">
                    </td>
                    <td>
                        <input type="number" data-field="credits" min="1" class="small-text">
                    </td>
                    <td>
                        <input type="number" data-field="price" min="1" class="small-text">
                    </td>
                    <td>
                        <textarea data-field="description" rows="2" class="large-text"></textarea>
                    </td>
                    <td>
                        <input type="number" data-field="sort_order" class="small-text" value="0">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" data-field="is_active" value="1" checked>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="button ai-gemini-remove-row">&times;</button>
                    </td>
                </tr>

                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="ai-gemini-add-package">
                    <?php esc_html_e('Add Package', 'ai-gemini-image'); ?>
                </button>
            </p>

            <p>
                <button type="submit" name="ai_gemini_save_packages" class="button button-primary">
                    <?php esc_html_e('Save Packages', 'ai-gemini-image'); ?>
                </button>
            </p>
        </form>
    </div>

    <script>
    (function($){
        $('#ai-gemini-add-package').on('click', function(){
            var $tbody = $('#ai-gemini-packages-rows');
            var index = $tbody.find('tr').not('.ai-gemini-package-template').length; // số row hiện tại
            var $tmpl = $tbody.find('.ai-gemini-package-template').clone().removeClass('ai-gemini-package-template').show();

            $tmpl.find('[data-field]').each(function(){
                var field = $(this).data('field');
                var name = 'packages[' + index + '][' + field + ']';
                $(this).attr('name', name);
                if (field === 'id') {
                    $(this).val('pkg_' + Date.now() + '_' + index);
                }
            });

            $tbody.append($tmpl);
        });

        $('#ai-gemini-packages-rows').on('click', '.ai-gemini-remove-row', function(){
            $(this).closest('tr').remove();
        });
    })(jQuery);
    </script>
    <?php
}