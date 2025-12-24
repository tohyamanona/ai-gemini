<?php
namespace MySeoTask\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TasksPage {

    const OPTION_KEY = 'myseotask_tasks_state';

    public static function tasks_catalog() {
        return [
            [ 'id' => 'gen_collect_diamond_1',             'type' => 'collect_diamond',         'label' => 'Nhặt kim cương',                  'page_type' => 'generic' ],
            [ 'id' => 'gen_click_internal',                'type' => 'click_internal_link',     'label' => 'Nhấp link nội bộ',                'page_type' => 'generic' ],
            [ 'id' => 'gen_click_content_image',           'type' => 'click_content_image',     'label' => 'Nhấp ảnh nội dung',               'page_type' => 'generic' ],
            [ 'id' => 'gen_click_content_image_find_diamond','type' => 'click_content_image_find_diamond','label' => 'Nhấp ảnh & tìm kim cương','page_type' => 'generic' ],

            [ 'id' => 'post_collect_diamond',              'type' => 'collect_diamond',         'label' => 'Nhặt kim cương',                  'page_type' => 'post' ],
            [ 'id' => 'post_click_related',                'type' => 'click_internal_link',     'label' => 'Nhấp bài liên quan',              'page_type' => 'post' ],
            [ 'id' => 'post_click_content_image',          'type' => 'click_content_image',     'label' => 'Nhấp ảnh nội dung',               'page_type' => 'post' ],
            [ 'id' => 'post_click_content_image_find_diamond','type' => 'click_content_image_find_diamond','label' => 'Nhấp ảnh & tìm kim cương','page_type' => 'post' ],

            [ 'id' => 'prod_collect_diamond',              'type' => 'collect_diamond',         'label' => 'Nhặt kim cương',                  'page_type' => 'product' ],
            [ 'id' => 'prod_click_internal',               'type' => 'click_internal_link',     'label' => 'Nhấp sp/bài liên quan',           'page_type' => 'product' ],
            [ 'id' => 'prod_click_content_image',          'type' => 'click_content_image',     'label' => 'Nhấp ảnh nội dung',               'page_type' => 'product' ],
            [ 'id' => 'prod_click_content_image_find_diamond','type' => 'click_content_image_find_diamond','label' => 'Nhấp ảnh & tìm kim cương','page_type' => 'product' ],
            [ 'id' => 'prod_click_add_to_cart',            'type' => 'click_add_to_cart',       'label' => 'Thêm vào giỏ',                    'page_type' => 'product' ],

            [ 'id' => 'cat_collect_diamond',               'type' => 'collect_diamond',         'label' => 'Nhặt kim cương',                  'page_type' => 'category' ],
            [ 'id' => 'cat_click_content_image',           'type' => 'click_content_image',     'label' => 'Nhấp ảnh nội dung',               'page_type' => 'category' ],
            [ 'id' => 'cat_click_content_image_find_diamond','type' => 'click_content_image_find_diamond','label' => 'Nhấp ảnh & tìm kim cương','page_type' => 'category' ],
            [ 'id' => 'cat_click_pagination',              'type' => 'click_pagination',        'label' => 'Nhấp phân trang',                 'page_type' => 'category' ],
            [ 'id' => 'cat_click_add_to_cart',             'type' => 'click_add_to_cart',       'label' => 'Thêm vào giỏ',                    'page_type' => 'category' ],

            [ 'id' => 'search_click_result',               'type' => 'click_internal_link',     'label' => 'Nhấp kết quả tìm kiếm',           'page_type' => 'search' ],
            [ 'id' => 'cart_scroll_50',                    'type' => 'scroll_to_percent',       'label' => 'Cuộn giỏ 50%',                    'page_type' => 'cart' ],
            [ 'id' => 'chk_scroll_50',                     'type' => 'scroll_to_percent',       'label' => 'Cuộn checkout 50%',               'page_type' => 'checkout' ],
        ];
    }

    public static function register_hooks() {
        add_action( 'admin_post_myseotask_save_tasks', [ __CLASS__, 'handle_save' ] );
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Không có quyền', 'my-seo-task' ) );
        }
        check_admin_referer( 'myseotask_tasks_nonce' );

        $enabled = isset( $_POST['task_enabled'] ) && is_array( $_POST['task_enabled'] ) ? $_POST['task_enabled'] : [];
        $states  = [];
        foreach ( self::tasks_catalog() as $task ) {
            $id = $task['id'];
            $states[ $id ] = ! empty( $enabled[ $id ] );
        }

        update_option( self::OPTION_KEY, $states );

        wp_safe_redirect( add_query_arg( [ 'page' => 'myseotask-tasks', 'updated' => 'true' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $states = get_option( self::OPTION_KEY, [] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MySeoTask - Nhiệm vụ', 'my-seo-task' ); ?></h1>
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Đã lưu danh sách nhiệm vụ.', 'my-seo-task' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'myseotask_tasks_nonce' ); ?>
                <input type="hidden" name="action" value="myseotask_save_tasks" />
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Bật', 'my-seo-task' ); ?></th>
                            <th><?php esc_html_e( 'Mã nhiệm vụ', 'my-seo-task' ); ?></th>
                            <th><?php esc_html_e( 'Loại', 'my-seo-task' ); ?></th>
                            <th><?php esc_html_e( 'Nhãn hiển thị', 'my-seo-task' ); ?></th>
                            <th><?php esc_html_e( 'Page type', 'my-seo-task' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( self::tasks_catalog() as $task ) :
                            $id = $task['id'];
                            $checked = ! empty( $states[ $id ] );
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="task_enabled[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $checked ); ?> />
                                </td>
                                <td><code><?php echo esc_html( $id ); ?></code></td>
                                <td><?php echo esc_html( $task['type'] ); ?></td>
                                <td><?php echo esc_html( $task['label'] ); ?></td>
                                <td><?php echo esc_html( $task['page_type'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( __( 'Lưu nhiệm vụ', 'my-seo-task' ) ); ?>
            </form>
        </div>
        <?php
    }
}