<?php
namespace MySeoTask\Rest\Controllers;

use MySeoTask\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConfigController {
    public static function get_config( \WP_REST_Request $request ) {
        $cfg = Config::get_instance()->get_client_config();
        return rest_ensure_response( $cfg );
    }
}