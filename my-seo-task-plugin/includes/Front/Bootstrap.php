<?php
namespace MySeoTask\Front;

use MySeoTask\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap {

    public static function register_hooks() {
        add_action( 'wp', [ __CLASS__, 'maybe_bootstrap' ], 1 );
    }

    public static function maybe_bootstrap() {
        $cfg = Config::all();
        if ( empty( $cfg['enabled'] ) ) {
            return;
        }
        // Gating nâng cao sẽ bổ sung sau; hiện không chặn gì thêm.
    }
}