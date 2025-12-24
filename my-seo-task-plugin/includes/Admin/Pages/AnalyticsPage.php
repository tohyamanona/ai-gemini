<?php
namespace MySeoTask\Admin\Pages;

use MySeoTask\Telemetry\Aggregator;
use MySeoTask\Admin\Pages\TasksPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AnalyticsPage {

    public static function register_hooks() {
        add_action( 'admin_post_myseotask_export_csv', [ __CLASS__, 'handle_export_csv' ] );
    }

    public static function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Không có quyền', 'my-seo-task' ) );
        }
        check_admin_referer( 'myseotask_analytics_export' );

        $today = current_time( 'Y-m-d' );
        $start = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-d', strtotime( '-7 days', strtotime( $today ) ) );
        $end   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : $today;
        $page_type = isset( $_GET['page_type'] ) ? sanitize_text_field( $_GET['page_type'] ) : '';
        $task_raw  = isset( $_GET['task_id'] ) ? sanitize_text_field( $_GET['task_id'] ) : '';

        $task_ids = [];
        if ( $task_raw !== '' && strpos( $task_raw, ',' ) !== false ) {
            $task_ids = array_filter( array_map( 'trim', explode( ',', $task_raw ) ) );
        } elseif ( $task_raw !== '' ) {
            $task_ids = [ $task_raw ];
        }

        global $wpdb;
        $table = \MySeoTask\Data\Migrations::table_name();

        $where = [];
        $params = [];

        if ( $start ) {
            $where[] = 'ts >= %s';
            $params[] = $start . ' 00:00:00';
        }
        if ( $end ) {
            $where[] = 'ts <= %s';
            $params[] = $end . ' 23:59:59';
        }
        if ( $page_type ) {
            $where[] = 'page_type = %s';
            $params[] = $page_type;
        }
        if ( $task_ids ) {
            $ph = implode( ',', array_fill( 0, count( $task_ids ), '%s' ) );
            $where[] = "task_id IN ($ph)";
            $params = array_merge( $params, $task_ids );
        }

        $whereSql = $where ? ('WHERE ' . implode( ' AND ', $where )) : '';
        $sql = "SELECT ts, event_type, page_type, task_id, duration_ms, session_id, reason_fail FROM $table $whereSql ORDER BY ts DESC LIMIT 20000";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="myseotask-events.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'ts', 'event_type', 'page_type', 'task_id', 'duration_ms', 'session_id', 'reason_fail' ] );
        if ( $rows ) {
            foreach ( $rows as $r ) {
                fputcsv( $out, $r );
            }
        }
        fclose( $out );
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        $start = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-d', strtotime( '-7 days', strtotime( $today ) ) );
        $end   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : $today;
        $page_type = isset( $_GET['page_type'] ) ? sanitize_text_field( $_GET['page_type'] ) : '';
        $task_raw  = isset( $_GET['task_id'] ) ? sanitize_text_field( $_GET['task_id'] ) : '';

        $task_ids = [];
        if ( $task_raw !== '' && strpos( $task_raw, ',' ) !== false ) {
            $task_ids = array_filter( array_map( 'trim', explode( ',', $task_raw ) ) );
        } elseif ( $task_raw !== '' ) {
            $task_ids = [ $task_raw ];
        }

        $summary = Aggregator::summary([
            'start_date' => $start,
            'end_date'   => $end,
            'page_type'  => $page_type ?: null,
            'task_ids'   => $task_ids ?: null,
        ]);
        $events = Aggregator::event_breakdown([
            'start_date' => $start,
            'end_date'   => $end,
            'page_type'  => $page_type ?: null,
            'task_ids'   => $task_ids ?: null,
        ]);

        $pageTypes = [
            ''          => '(Tất cả)',
            'generic'   => 'generic',
            'post'      => 'post',
            'product'   => 'product',
            'category'  => 'category',
            'search'    => 'search',
            'cart'      => 'cart',
            'checkout'  => 'checkout',
        ];

        $allTasks = TasksPage::tasks_catalog();
        $grouped = [];
        foreach ( $allTasks as $t ) {
            $label = $t['label'];
            if ( ! isset( $grouped[ $label ] ) ) {
                $grouped[ $label ] = [];
            }
            $grouped[ $label ][] = $t['id'];
        }

        $taskOptions = [ '' => '(Tất cả nhiệm vụ)' ];
        foreach ( $grouped as $label => $ids ) {
            $value = implode( ',', $ids );
            $pretty = (count($ids) > 1) ? ($label . ' (gộp biến thể)') : $label;
            $taskOptions[ $value ] = $pretty;
        }
        asort( $taskOptions );

        $summaryRows = [
            [ 'Hiển thị (task_show)', $summary['impressions'] ],
            [ 'Bắt đầu (task_start)', $summary['attempts'] ],
            [ 'Hoàn thành (task_complete)', $summary['success'] ],
            [ 'Tỷ lệ hoàn thành (%)', $summary['completion_rate'] . '%' ],
            [ 'Thời gian TB (ms)', $summary['avg_duration_ms'] === null ? '—' : $summary['avg_duration_ms'] ],
        ];
        $skip = ['task_show','task_start','task_complete'];
        $otherEvents = [];
        foreach ( $events as $etype => $cnt ) {
            if ( in_array( $etype, $skip, true ) ) continue;
            $otherEvents[] = [ $etype, $cnt ];
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MySeoTask - Thống kê', 'my-seo-task' ); ?></h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="myseotask-analytics" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Từ ngày', 'my-seo-task' ); ?></th>
                        <td><input type="date" name="start_date" value="<?php echo esc_attr( $start ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Đến ngày', 'my-seo-task' ); ?></th>
                        <td><input type="date" name="end_date" value="<?php echo esc_attr( $end ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Page type', 'my-seo-task' ); ?></th>
                        <td>
                            <select name="page_type">
                                <?php foreach ( $pageTypes as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $page_type, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Nhiệm vụ', 'my-seo-task' ); ?></th>
                        <td>
                            <select name="task_id">
                                <?php foreach ( $taskOptions as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $task_raw, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Chọn nhãn gộp để cộng dồn tất cả biến thể task đó.', 'my-seo-task' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Lọc', 'my-seo-task' ) ); ?>
            </form>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                <?php wp_nonce_field( 'myseotask_analytics_export' ); ?>
                <input type="hidden" name="action" value="myseotask_export_csv" />
                <input type="hidden" name="start_date" value="<?php echo esc_attr( $start ); ?>" />
                <input type="hidden" name="end_date" value="<?php echo esc_attr( $end ); ?>" />
                <input type="hidden" name="page_type" value="<?php echo esc_attr( $page_type ); ?>" />
                <input type="hidden" name="task_id" value="<?php echo esc_attr( $task_raw ); ?>" />
                <?php submit_button( __( 'Tải CSV', 'my-seo-task' ), 'secondary', 'submit', false ); ?>
                <p class="description"><?php esc_html_e( 'Xuất sự kiện thô (tối đa 20.000 dòng) theo bộ lọc hiện tại.', 'my-seo-task' ); ?></p>
            </form>

            <h2><?php esc_html_e( 'Tóm tắt & Sự kiện', 'my-seo-task' ); ?></h2>
            <table class="widefat striped" style="max-width:600px;">
                <thead><tr><th><?php esc_html_e( 'Chỉ số / Sự kiện', 'my-seo-task' ); ?></th><th><?php esc_html_e( 'Giá trị', 'my-seo-task' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $summaryRows as $row ) : ?>
                        <tr><td><?php echo esc_html( $row[0] ); ?></td><td><?php echo esc_html( $row[1] ); ?></td></tr>
                    <?php endforeach; ?>

                    <?php if ( ! empty( $otherEvents ) ) : ?>
                        <tr><th colspan="2"><?php esc_html_e( 'Sự kiện khác', 'my-seo-task' ); ?></th></tr>
                        <?php foreach ( $otherEvents as $row ) : ?>
                            <tr><td><code><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo esc_html( $row[1] ); ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}