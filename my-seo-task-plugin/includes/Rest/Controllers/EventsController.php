<?php
namespace MySeoTask\Rest\Controllers;

use MySeoTask\Telemetry\EventService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventsController {
    private static $allowed = [
        'flow_start', 'flow_complete',
        'task_show', 'task_start', 'task_complete', 'task_fail',
        'ui_start_button_show', 'ui_start_button_click',
        'ui_popup_show', 'ui_popup_close',
        'ui_navigator_show', 'ui_navigator_hide',
    ];

    public static function store_event( \WP_REST_Request $request ) {
        $event_type = sanitize_text_field( $request->get_param( 'event_type' ) );
        if ( ! in_array( $event_type, self::$allowed, true ) ) {
            return new \WP_Error( 'invalid_event', 'Event type not allowed', [ 'status' => 400 ] );
        }

        $payload = [
            'event_type'  => $event_type,
            'session_id'  => sanitize_text_field( $request->get_param( 'session_id' ) ),
            'user_id'     => get_current_user_id() ?: null,
            'page_type'   => sanitize_text_field( $request->get_param( 'page_type' ) ),
            'task_id'     => sanitize_text_field( $request->get_param( 'task_id' ) ),
            'duration_ms' => intval( $request->get_param( 'duration_ms' ) ),
            'reason_fail' => sanitize_text_field( $request->get_param( 'reason_fail' ) ),
            'meta'        => $request->get_param( 'meta' ),
        ];

        EventService::record( $payload );

        return rest_ensure_response( [ 'ok' => true ] );
    }
}