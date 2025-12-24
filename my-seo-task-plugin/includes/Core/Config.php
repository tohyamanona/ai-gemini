<?php
namespace MySeoTask\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {

    const OPTION_KEY = 'myseotask_settings';

    protected static $defaults = [
        'enabled'    => true,
        'debug_mode' => false,
        'telemetry'  => [
            'retention_days' => 30,
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
                'enabled'               => true,
                'min_seconds'           => 18,
                'min_interactions'      => 6,
                'min_scroll_px'         => 600,
                'min_depth_percent'     => 12,
                'min_pause_ms'          => 2800,
                'anti_fast_scroll_mode' => 'alert',
            ],
        ],
    ];

    public static function init() {
        // Không ghi đè option
    }

    public static function defaults() {
        return self::$defaults;
    }

    public static function all() {
        $saved = get_option( self::OPTION_KEY, [] );
        return self::merge_recursive( self::$defaults, $saved );
    }

    public static function get( $key = null, $default = null ) {
        $all = self::all();
        if ( $key === null ) return $all;
        $path = explode( '.', $key );
        $val = $all;
        foreach ( $path as $k ) {
            if ( is_array( $val ) && array_key_exists( $k, $val ) ) {
                $val = $val[ $k ];
            } else {
                return $default;
            }
        }
        return $val;
    }

    protected static function merge_recursive( $defaults, $saved ) {
        foreach ( $saved as $k => $v ) {
            if ( is_array( $v ) && isset( $defaults[ $k ] ) && is_array( $defaults[ $k ] ) ) {
                $defaults[ $k ] = self::merge_recursive( $defaults[ $k ], $v );
            } else {
                $defaults[ $k ] = $v;
            }
        }
        return $defaults;
    }
}