<?php
namespace MySeoTask\Telemetry;

use MySeoTask\Data\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cleanup {

    /**
     * Xoá sự kiện cũ theo retention_days (default 30).
     */
    public static function run() {
        global $wpdb;

        $table = Migrations::table_name();
        // Nếu bảng chưa tồn tại, bỏ qua
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists !== $table ) {
            return;
        }

        // Lấy retention từ option myseotask_settings.telemetry.retention_days
        $settings = get_option( 'myseotask_settings', [] );
        $retention = 30;
        if ( isset( $settings['telemetry']['retention_days'] ) && intval( $settings['telemetry']['retention_days'] ) > 0 ) {
            $retention = intval( $settings['telemetry']['retention_days'] );
        }

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $retention * DAY_IN_SECONDS );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE ts < %s",
                $cutoff
            )
        );
    }
}