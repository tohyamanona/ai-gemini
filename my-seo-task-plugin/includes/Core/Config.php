<?php
namespace MySeoTask\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {
    private static $instance = null;
    private $data = [];

    public static function init() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_instance() {
        return self::$instance ?: self::init();
    }

    private function __construct() {
        $defaults = $this->get_defaults();

        // myseotask_settings: enabled, debug_mode (và có thể chứa targeting cũ)
        $settings = get_option( 'myseotask_settings', [] );
        // myseotask_targeting: option mới cho trang Rules
        $targetingOpt = get_option( 'myseotask_targeting', [] );

        $merged = wp_parse_args( is_array( $settings ) ? $settings : [], $defaults );

        // Gộp targeting từ option riêng
        if ( isset( $merged['targeting'] ) && is_array( $merged['targeting'] ) ) {
            $merged['targeting'] = wp_parse_args( $targetingOpt, $merged['targeting'] );
        } else {
            $merged['targeting'] = wp_parse_args( $targetingOpt, $defaults['targeting'] );
        }

        // Disabled tasks
        $disabled = get_option( 'myseotask_tasks_state', [] );
        $disabledIds = [];
        if ( is_array( $disabled ) ) {
            foreach ( $disabled as $taskId => $enabled ) {
                if ( ! $enabled ) {
                    $disabledIds[] = $taskId;
                }
            }
        }
        $merged['disabledTaskIds'] = $disabledIds;

        $this->data = $merged;
    }

    public function all() {
        return $this->data;
    }

    public function is_enabled() {
        return ! empty( $this->data['enabled'] );
    }

    public function disabled_task_ids() {
        return $this->data['disabledTaskIds'] ?? [];
    }

    public function get_client_config() {
        $cfg = $this->data;
        unset( $cfg['telemetry']['secret'] );
        $cfg['version'] = defined( 'MST_PLUGIN_VERSION' ) ? MST_PLUGIN_VERSION : '0.0.0';
        $cfg['build']   = date( 'Y-m-d' );
        return $cfg;
    }

    private function get_defaults() {
        return [
            'enabled' => true,
            'debug_mode' => false,
            'environments' => [
                'production' => true,
                'staging'    => false,
                'dev'        => false,
            ],
            'targeting' => [
                'include_patterns'   => [],
                'exclude_patterns'   => [],
                'allowed_page_types' => [ 'generic', 'post', 'product', 'category', 'search', 'cart', 'checkout' ],
                'allowed_device'     => 'both',   // both|mobile|desktop
                'allowed_user'       => 'both',   // both|guest|logged_in
            ],
            'performance' => [
                'load_only_when_eligible' => true,
                'delay_load_ms'           => 0,
                'delay_load_scroll_px'    => 0,
                'cache_ttl_seconds'       => 0,
            ],
            'ui' => [
                'start_button' => [
                    'text'                 => 'Bắt đầu nhiệm vụ',
                    'dom_id'               => '',
                    'scroll_threshold'     => 0.5,
                    'delay_ms'             => 1000,
                    'only_if_eligible'     => true,
                    'max_show_per_session' => 3,
                ],
                'gate' => [
                    'enabled'            => true,
                    'min_seconds'        => 18,
                    'min_interactions'   => 6,
                    'min_scroll_px'      => 600,
                    'min_depth_percent'  => 12,
                    'min_pause_ms'       => 2800,
                    'anti_fast_scroll_mode' => 'alert',
                ],
                'task_popup' => [
                    'enabled'           => false,
                    'auto_close_ms'     => 3000,
                    'show_close_button' => true,
                    'show_on'           => 'taskChange',
                    'once_per_session'  => true,
                    'cooldown_minutes'  => 0,
                ],
            ],
            'telemetry' => [
                'retention_days' => 30,
                'log_level'      => 'INFO',
            ],
            'disabledTaskIds' => [],
        ];
    }
}