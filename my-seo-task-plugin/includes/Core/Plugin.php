<?php
namespace MySeoTask\Core;

use MySeoTask\Core\Assets;
use MySeoTask\Core\Config;
use MySeoTask\Front\Bootstrap as FrontBootstrap;
use MySeoTask\Admin\Menu as AdminMenu;
use MySeoTask\Rest\Routes as RestRoutes;
use MySeoTask\Data\Migrations;
use MySeoTask\Telemetry\Cleanup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    const CRON_HOOK = 'myseotask_cleanup_events';

    public static function init() {
        add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );

        Config::init();
        Assets::register_hooks();
        FrontBootstrap::register_hooks();
        AdminMenu::register_hooks();
        RestRoutes::register_hooks();

        register_activation_hook( MST_PLUGIN_FILE, [ __CLASS__, 'on_activate' ] );
        register_deactivation_hook( MST_PLUGIN_FILE, [ __CLASS__, 'on_deactivate' ] );

        // Cron cleanup
        add_action( self::CRON_HOOK, [ Cleanup::class, 'run' ] );

        // Optional: check version on load
        add_action( 'init', [ __CLASS__, 'maybe_upgrade' ], 1 );
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'my-seo-task', false, dirname( plugin_basename( MST_PLUGIN_FILE ) ) . '/languages' );
    }

    public static function on_activate() {
        Migrations::run();
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'daily', self::CRON_HOOK );
        }
    }

    public static function on_deactivate() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }

    public static function maybe_upgrade() {
        Migrations::run();
    }
}