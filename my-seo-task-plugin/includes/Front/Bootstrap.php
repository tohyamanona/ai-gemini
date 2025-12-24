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
        // Gating tối thiểu: đã kiểm tra is_admin và bot ở Assets; ở đây chỉ giữ chỗ cho future targeting.
        $cfg = Config::get_instance();
        if ( ! $cfg->is_enabled() ) {
            return;
        }
        // Future: apply targeting rules (URL include/exclude, device, user, pageType)
    }
}