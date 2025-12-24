<?php
namespace MySeoTask\Telemetry;

use MySeoTask\Data\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventService {

    public static function record( array $payload ) {
        global $wpdb;
        $table = Migrations::table_name();

        // Nếu bảng chưa tồn tại, log vào debug.log để tránh fatal.
        if ( $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s", $table
        ) ) !== $table ) {
            self::log_debug( $payload );
            return;
        }

        $event_type  = isset( $payload['event_type'] ) ? sanitize_text_field( $payload['event_type'] ) : '';
        if ( ! $event_type ) return;

        $session_id  = isset( $payload['session_id'] ) ? sanitize_text_field( $payload['session_id'] ) : '';
        $page_type   = isset( $payload['page_type'] ) ? sanitize_text_field( $payload['page_type'] ) : '';
        $task_id     = isset( $payload['task_id'] ) ? sanitize_text_field( $payload['task_id'] ) : '';
        $duration_ms = isset( $payload['duration_ms'] ) ? intval( $payload['duration_ms'] ) : null;
        $reason_fail = isset( $payload['reason_fail'] ) ? sanitize_text_field( $payload['reason_fail'] ) : null;
        $user_id     = isset( $payload['user_id'] ) ? intval( $payload['user_id'] ) : ( get_current_user_id() ?: null );
        $ts          = current_time( 'mysql' );

        $meta = isset( $payload['meta'] ) ? $payload['meta'] : null;
        // Giới hạn độ dài để tránh phình DB
        $meta_json = $meta ? wp_json_encode( $meta ) : null;
        if ( $meta_json && strlen( $meta_json ) > 5000 ) {
            $meta_json = substr( $meta_json, 0, 5000 );
        }

        $wpdb->insert(
            $table,
            [
                'ts'          => $ts,
                'session_id'  => $session_id,
                'user_id'     => $user_id ?: null,
                'page_type'   => $page_type ?: null,
                'task_id'     => $task_id ?: null,
                'event_type'  => $event_type,
                'duration_ms' => $duration_ms ?: null,
                'reason_fail' => $reason_fail ?: null,
                'meta_json'   => $meta_json,
            ],
            [
                '%s','%s','%d','%s','%s','%s','%d','%s','%s'
            ]
        );
    }

    private static function log_debug( $payload ) {
        $msg = '[MySeoTask Event] ' . wp_json_encode( $payload );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( $msg );
        }
    }
}