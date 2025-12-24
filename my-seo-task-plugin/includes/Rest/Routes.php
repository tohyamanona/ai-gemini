<?php
namespace MySeoTask\Rest;

use MySeoTask\Rest\Controllers\ConfigController;
use MySeoTask\Rest\Controllers\EventsController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Routes {
    const REST_NAMESPACE = 'myseotask/v1';

    public static function register_hooks() {
        add_action( 'rest_api_init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/config',
            [
                'methods'             => 'GET',
                'callback'            => [ ConfigController::class, 'get_config' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/events',
            [
                'methods'             => 'POST',
                'callback'            => [ EventsController::class, 'store_event' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'event_type' => [ 'required' => true ],
                ],
            ]
        );
    }
}