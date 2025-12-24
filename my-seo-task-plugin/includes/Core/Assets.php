<?php
namespace MySeoTask\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {

    public static function register_hooks() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_front' ] );
    }

    private static function is_bot_user_agent() {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return false;
        }
        $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
        $bot_signatures = [
            'googlebot','bingbot','slurp','duckduckbot','baiduspider','yandexbot','sogou','exabot',
            'facebookexternalhit','facebot','ia_archiver','ahrefsbot','semrushbot','mj12bot',
            'bot/','crawler','spider','uptimerobot','pingdom','chrome-lighthouse',
        ];
        foreach ( $bot_signatures as $sig ) {
            if ( strpos( $ua, $sig ) !== false ) {
                return true;
            }
        }
        return false;
    }

    private static function match_pattern( $pattern, $url ) {
        $pattern = trim( $pattern );
        if ( $pattern === '' ) return false;
        // Nếu dạng /regex/ hoặc #regex# thì coi là regex
        $delim = substr( $pattern, 0, 1 );
        $end   = substr( $pattern, -1 );
        if ( $delim === $end && ($delim === '/' || $delim === '#') && strlen( $pattern ) > 2 ) {
            $regex = $pattern;
            return @preg_match( $regex, $url ) === 1;
        }
        // fallback: contains
        return (stripos( $url, $pattern ) !== false);
    }

    private static function passes_targeting() {
        $cfg = Config::get_instance()->all();
        $t   = isset( $cfg['targeting'] ) ? $cfg['targeting'] : [];

        $current_url = ( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
        $is_mobile   = function_exists( 'wp_is_mobile' ) ? wp_is_mobile() : false;
        $is_logged   = is_user_logged_in();

        // include_patterns: nếu có, URL phải match ít nhất một
        if ( ! empty( $t['include_patterns'] ) && is_array( $t['include_patterns'] ) ) {
            $ok = false;
            foreach ( $t['include_patterns'] as $pat ) {
                if ( self::match_pattern( $pat, $current_url ) ) {
                    $ok = true;
                    break;
                }
            }
            if ( ! $ok ) return false;
        }

        // exclude_patterns: nếu match thì loại
        if ( ! empty( $t['exclude_patterns'] ) && is_array( $t['exclude_patterns'] ) ) {
            foreach ( $t['exclude_patterns'] as $pat ) {
                if ( self::match_pattern( $pat, $current_url ) ) {
                    return false;
                }
            }
        }

        // device
        $allowed_device = isset( $t['allowed_device'] ) ? $t['allowed_device'] : 'both';
        if ( $allowed_device === 'mobile' && ! $is_mobile ) return false;
        if ( $allowed_device === 'desktop' && $is_mobile ) return false;

        // user
        $allowed_user = isset( $t['allowed_user'] ) ? $t['allowed_user'] : 'both';
        if ( $allowed_user === 'guest' && $is_logged ) return false;
        if ( $allowed_user === 'logged_in' && ! $is_logged ) return false;

        return true;
    }

    public static function enqueue_front() {
        if ( is_admin() ) return;
        if ( self::is_bot_user_agent() ) return;

        $cfgObj = Config::get_instance();
        if ( ! $cfgObj->is_enabled() ) return;
        if ( ! self::passes_targeting() ) return;

        $cfg = $cfgObj->get_client_config();
        wp_register_script( 'my-seo-task-config', '', [], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-config' );
        wp_add_inline_script( 'my-seo-task-config', 'window.__MYSEOTASK_CONFIG__ = ' . wp_json_encode( $cfg ) . ';', 'before' );

        // CSS
        wp_enqueue_style( 'my-seo-task-progress-css', MST_PLUGIN_URL . 'assets/css/progress-bar.css', [], MST_PLUGIN_VERSION );
        wp_enqueue_style( 'my-seo-task-start-button-css', MST_PLUGIN_URL . 'assets/css/start-button.css', [], MST_PLUGIN_VERSION );
        wp_enqueue_style( 'my-seo-task-tasks-overlay-css', MST_PLUGIN_URL . 'assets/css/tasks-overlay.css', [], MST_PLUGIN_VERSION );
        wp_enqueue_style( 'my-seo-task-diamond-css', MST_PLUGIN_URL . 'assets/css/diamond.css', [], MST_PLUGIN_VERSION );
        wp_enqueue_style( 'my-seo-task-internal-link-hint-css', MST_PLUGIN_URL . 'assets/css/internal-link-hint.css', [], MST_PLUGIN_VERSION );

        // JS core
        wp_enqueue_script( 'my-seo-task-session-manager', MST_PLUGIN_URL . 'assets/js/session-manager.js', [], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-progress-bar', MST_PLUGIN_URL . 'assets/js/progress-bar.js', [ 'my-seo-task-session-manager' ], MST_PLUGIN_VERSION, true );

        // Task engine
        wp_enqueue_script( 'my-seo-task-tasks-registry', MST_PLUGIN_URL . 'assets/js/tasks-registry.js', [], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-tasks-generator', MST_PLUGIN_URL . 'assets/js/tasks-generator.js', [ 'my-seo-task-tasks-registry' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-task-telemetry', MST_PLUGIN_URL . 'assets/js/task-telemetry.js', [], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-diamond-manager', MST_PLUGIN_URL . 'assets/js/diamond-manager.js', [], MST_PLUGIN_VERSION, true );

        // Validators
        wp_enqueue_script( 'my-seo-task-validator-utils', MST_PLUGIN_URL . 'assets/js/validators/validator-utils.js', [], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-state', MST_PLUGIN_URL . 'assets/js/validators/validator-state.js', [ 'my-seo-task-validator-utils' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-telemetry-bridge', MST_PLUGIN_URL . 'assets/js/validators/validator-telemetry-bridge.js', [ 'my-seo-task-validator-utils', 'my-seo-task-task-telemetry' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-guided-arrow', MST_PLUGIN_URL . 'assets/js/validators/validator-guided-arrow.js', [ 'my-seo-task-validator-utils', 'my-seo-task-validator-state' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-diamonds', MST_PLUGIN_URL . 'assets/js/validators/validator-diamonds.js', [ 'my-seo-task-validator-utils', 'my-seo-task-validator-state', 'my-seo-task-diamond-manager', 'my-seo-task-validator-telemetry-bridge' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-internal-link', MST_PLUGIN_URL . 'assets/js/validators/validator-internal-link.js', [ 'my-seo-task-validator-utils', 'my-seo-task-validator-state', 'my-seo-task-validator-guided-arrow' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-image', MST_PLUGIN_URL . 'assets/js/validators/validator-image.js', [ 'my-seo-task-validator-utils', 'my-seo-task-validator-state', 'my-seo-task-validator-guided-arrow', 'my-seo-task-validator-telemetry-bridge' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-validator-scroll', MST_PLUGIN_URL . 'assets/js/validators/validator-scroll.js', [ 'my-seo-task-validator-utils', 'my-seo-task-validator-telemetry-bridge' ], MST_PLUGIN_VERSION, true );

        wp_enqueue_script( 'my-seo-task-tasks-validator', MST_PLUGIN_URL . 'assets/js/validators/validator-orchestrator.js', [
            'my-seo-task-validator-utils',
            'my-seo-task-validator-state',
            'my-seo-task-validator-telemetry-bridge',
            'my-seo-task-validator-guided-arrow',
            'my-seo-task-validator-diamonds',
            'my-seo-task-validator-internal-link',
            'my-seo-task-validator-image',
            'my-seo-task-validator-scroll',
        ], MST_PLUGIN_VERSION, true );

        wp_enqueue_script( 'my-seo-task-tasks-overlay', MST_PLUGIN_URL . 'assets/js/tasks-ui-overlay.js', [ 'my-seo-task-task-telemetry' ], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-tasks-flow', MST_PLUGIN_URL . 'assets/js/tasks-flow-manager.js', [
            'my-seo-task-tasks-generator',
            'my-seo-task-tasks-overlay',
            'my-seo-task-tasks-validator',
            'my-seo-task-progress-bar',
            'my-seo-task-session-manager',
        ], MST_PLUGIN_VERSION, true );

        wp_enqueue_script( 'my-seo-task-start-button', MST_PLUGIN_URL . 'assets/js/start-button.js', [
            'my-seo-task-session-manager',
            'my-seo-task-progress-bar',
            'my-seo-task-tasks-flow',
        ], MST_PLUGIN_VERSION, true );

        wp_enqueue_script( 'my-seo-task-page-detector', MST_PLUGIN_URL . 'assets/js/page-detector.js', [], MST_PLUGIN_VERSION, true );
        wp_enqueue_script( 'my-seo-task-popup-totp', MST_PLUGIN_URL . 'assets/js/popup-totp.js', [], MST_PLUGIN_VERSION, true );
    }
}