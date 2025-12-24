<?php
namespace MySeoTask\Telemetry;

use MySeoTask\Data\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aggregator {

    /**
     * @param array $args [
     *   'start_date' => 'Y-m-d',  // optional
     *   'end_date'   => 'Y-m-d',  // optional
     *   'page_type'  => string|null,
     *   'task_id'    => string|null,   // single id
     *   'task_ids'   => array|null,    // multiple ids (takes precedence if non-empty)
     * ]
     * @return array { impressions, attempts, success, completion_rate, avg_duration_ms }
     */
    public static function summary( array $args = [] ) {
        global $wpdb;
        $table = Migrations::table_name();

        $whereParts = [];
        $params = [];

        if ( ! empty( $args['start_date'] ) ) {
            $whereParts[] = 'ts >= %s';
            $params[] = $args['start_date'] . ' 00:00:00';
        }
        if ( ! empty( $args['end_date'] ) ) {
            $whereParts[] = 'ts <= %s';
            $params[] = $args['end_date'] . ' 23:59:59';
        }
        if ( ! empty( $args['page_type'] ) ) {
            $whereParts[] = 'page_type = %s';
            $params[] = $args['page_type'];
        }

        $taskIds = [];
        if ( ! empty( $args['task_ids'] ) && is_array( $args['task_ids'] ) ) {
            $taskIds = array_filter( array_map( 'sanitize_text_field', $args['task_ids'] ) );
        } elseif ( ! empty( $args['task_id'] ) ) {
            $taskIds = [ sanitize_text_field( $args['task_id'] ) ];
        }
        if ( $taskIds ) {
            $placeholders = implode( ',', array_fill( 0, count( $taskIds ), '%s' ) );
            $whereParts[] = "task_id IN ($placeholders)";
            $params = array_merge( $params, $taskIds );
        }

        $whereSql = $whereParts ? ('WHERE ' . implode( ' AND ', $whereParts )) : '';
        $p = $params;

        $impressions = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table $whereSql AND event_type = 'task_show'",
            $p
        ) );

        $attempts = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table $whereSql AND event_type = 'task_start'",
            $p
        ) );

        $success = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table $whereSql AND event_type = 'task_complete'",
            $p
        ) );

        $avg_duration_ms = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(duration_ms) FROM $table $whereSql AND event_type = 'task_complete'",
            $p
        ) );
        $avg_duration_ms = $avg_duration_ms !== null ? (int) $avg_duration_ms : null;

        $rate = ($attempts > 0) ? round( ($success / $attempts) * 100, 2 ) : 0;

        return [
            'impressions'      => $impressions,
            'attempts'         => $attempts,
            'success'          => $success,
            'completion_rate'  => $rate,
            'avg_duration_ms'  => $avg_duration_ms,
        ];
    }

    /**
     * Đếm số lần mỗi event_type trong phạm vi lọc, hỗ trợ nhiều task_id.
     */
    public static function event_breakdown( array $args = [] ) {
        global $wpdb;
        $table = Migrations::table_name();

        $whereParts = [];
        $params = [];

        if ( ! empty( $args['start_date'] ) ) {
            $whereParts[] = 'ts >= %s';
            $params[] = $args['start_date'] . ' 00:00:00';
        }
        if ( ! empty( $args['end_date'] ) ) {
            $whereParts[] = 'ts <= %s';
            $params[] = $args['end_date'] . ' 23:59:59';
        }
        if ( ! empty( $args['page_type'] ) ) {
            $whereParts[] = 'page_type = %s';
            $params[] = $args['page_type'];
        }

        $taskIds = [];
        if ( ! empty( $args['task_ids'] ) && is_array( $args['task_ids'] ) ) {
            $taskIds = array_filter( array_map( 'sanitize_text_field', $args['task_ids'] ) );
        } elseif ( ! empty( $args['task_id'] ) ) {
            $taskIds = [ sanitize_text_field( $args['task_id'] ) ];
        }
        if ( $taskIds ) {
            $placeholders = implode( ',', array_fill( 0, count( $taskIds ), '%s' ) );
            $whereParts[] = "task_id IN ($placeholders)";
            $params = array_merge( $params, $taskIds );
        }

        $whereSql = $whereParts ? ('WHERE ' . implode( ' AND ', $whereParts )) : '';

        $sql = "SELECT event_type, COUNT(*) AS cnt FROM $table $whereSql GROUP BY event_type ORDER BY cnt DESC";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        $out = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $out[ $r['event_type'] ] = (int) $r['cnt'];
            }
        }
        return $out;
    }
}