<?php
namespace MySeoTask\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RulesPage {

    const OPTION_KEY = 'myseotask_rules';

    public static function register_hooks() {
        add_action( 'admin_post_myseotask_save_rules', [ __CLASS__, 'handle_save' ] );
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Không có quyền', 'my-seo-task' ) );
        }
        check_admin_referer( 'myseotask_rules_nonce' );

        $rules = [
            'include_urls' => sanitize_textarea_field( $_POST['include_urls'] ?? '' ),
            'exclude_urls' => sanitize_textarea_field( $_POST['exclude_urls'] ?? '' ),
            'page_types'   => array_map( 'sanitize_text_field', $_POST['page_types'] ?? [] ),
            'allow_logged_in' => ! empty( $_POST['allow_logged_in'] ),
            'allow_guests'    => ! empty( $_POST['allow_guests'] ),
            'devices' => array_map( 'sanitize_text_field', $_POST['devices'] ?? [] ),
        ];

        update_option( self::OPTION_KEY, $rules );

        wp_safe_redirect( add_query_arg( [ 'page' => 'myseotask-rules', 'updated' => 'true' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $opt = get_option( self::OPTION_KEY, [] );
        $include = $opt['include_urls'] ?? '';
        $exclude = $opt['exclude_urls'] ?? '';
        $pageTypes = $opt['page_types'] ?? [];
        $allowLogged = ! empty( $opt['allow_logged_in'] );
        $allowGuest  = ! empty( $opt['allow_guests'] );
        $devices = $opt['devices'] ?? [];

        $pageTypeList = [ 'product', 'category', 'post', 'search', 'cart', 'checkout', 'generic' ];
        $deviceList   = [ 'desktop', 'mobile' ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MySeoTask - Luật & Phạm vi', 'my-seo-task' ); ?></h1>
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Đã lưu luật.', 'my-seo-task' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'myseotask_rules_nonce' ); ?>
                <input type="hidden" name="action" value="myseotask_save_rules" />

                <h2><?php esc_html_e( 'URL áp dụng', 'my-seo-task' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Include URL patterns', 'my-seo-task' ); ?></th>
                        <td><textarea name="include_urls" rows="4" class="large-text" placeholder="/san-pham/*&#10;/danh-muc/*"><?php echo esc_textarea( $include ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Exclude URL patterns', 'my-seo-task' ); ?></th>
                        <td><textarea name="exclude_urls" rows="4" class="large-text" placeholder="/checkout/*&#10;/cart/*"><?php echo esc_textarea( $exclude ); ?></textarea></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Page type cho phép', 'my-seo-task' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Chọn page type', 'my-seo-task' ); ?></th>
                        <td>
                            <?php foreach ( $pageTypeList as $pt ) : ?>
                                <label style="margin-right:12px;">
                                    <input type="checkbox" name="page_types[]" value="<?php echo esc_attr( $pt ); ?>" <?php checked( in_array( $pt, $pageTypes, true ) ); ?> />
                                    <?php echo esc_html( $pt ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Đối tượng & Thiết bị', 'my-seo-task' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Người dùng', 'my-seo-task' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="allow_logged_in" value="1" <?php checked( $allowLogged ); ?> /> <?php esc_html_e( 'Cho phép người đã đăng nhập', 'my-seo-task' ); ?></label><br/>
                            <label><input type="checkbox" name="allow_guests" value="1" <?php checked( $allowGuest ); ?> /> <?php esc_html_e( 'Cho phép khách (chưa đăng nhập)', 'my-seo-task' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Thiết bị', 'my-seo-task' ); ?></th>
                        <td>
                            <?php foreach ( $deviceList as $d ) : ?>
                                <label style="margin-right:12px;">
                                    <input type="checkbox" name="devices[]" value="<?php echo esc_attr( $d ); ?>" <?php checked( in_array( $d, $devices, true ) ); ?> />
                                    <?php echo esc_html( $d ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Lưu luật', 'my-seo-task' ) ); ?>
            </form>
        </div>
        <?php
    }
}