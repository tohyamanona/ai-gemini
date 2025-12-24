<?php
namespace MySeoTask\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsPage {

    const OPTION_KEY = 'myseotask_settings';

    public static function register_hooks() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function register_settings() {
        register_setting(
            'myseotask_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize' ],
                'default'           => [],
            ]
        );

        add_settings_section(
            'myseotask_general_section',
            __( 'Cài đặt chung', 'my-seo-task' ),
            function () {
                echo '<p>' . esc_html__( 'Bật/tắt plugin, debug và cấu hình lưu trữ sự kiện.', 'my-seo-task' ) . '</p>';
            },
            'myseotask'
        );

        add_settings_field(
            'myseotask_enabled',
            __( 'Bật plugin', 'my-seo-task' ),
            [ __CLASS__, 'field_checkbox' ],
            'myseotask',
            'myseotask_general_section',
            [ 'key' => 'enabled', 'label' => __( 'Kích hoạt runtime phía front-end', 'my-seo-task' ) ]
        );

        add_settings_field(
            'myseotask_debug_mode',
            __( 'Chế độ debug', 'my-seo-task' ),
            [ __CLASS__, 'field_checkbox' ],
            'myseotask',
            'myseotask_general_section',
            [ 'key' => 'debug_mode', 'label' => __( 'Bật log/overlay debug (nếu có)', 'my-seo-task' ) ]
        );

        add_settings_field(
            'myseotask_retention_days',
            __( 'Giữ log sự kiện (ngày)', 'my-seo-task' ),
            [ __CLASS__, 'field_number' ],
            'myseotask',
            'myseotask_general_section',
            [
                'key'   => 'telemetry.retention_days',
                'label' => __( 'Xoá sự kiện cũ hơn N ngày (cron hàng ngày)', 'my-seo-task' ),
                'min'   => 1,
                'max'   => 365,
                'step'  => 1,
            ]
        );
    }

    public static function sanitize( $input ) {
        $existing = get_option( self::OPTION_KEY, [] );
        $out = $existing;

        $out['enabled']    = ! empty( $input['enabled'] );
        $out['debug_mode'] = ! empty( $input['debug_mode'] );

        $ret = 30;
        if ( isset( $input['telemetry']['retention_days'] ) ) {
            $ret = intval( $input['telemetry']['retention_days'] );
        }
        if ( $ret < 1 )   $ret = 1;
        if ( $ret > 365 ) $ret = 365;
        $out['telemetry']['retention_days'] = $ret;

        if ( isset( $input['ui'] ) && is_array( $input['ui'] ) ) {
            $out['ui'] = self::sanitize_ui( $input['ui'], $existing['ui'] ?? [] );
        }

        return $out;
    }

    // Trong sanitize_ui:
    protected static function sanitize_ui( $ui, $existing_ui ) {
        $out = $existing_ui;

        $sb_in = $ui['start_button'] ?? [];
        $out['start_button'] = [
            'text'                 => sanitize_text_field( $sb_in['text'] ?? ( $existing_ui['start_button']['text'] ?? 'Bắt đầu nhiệm vụ' ) ),
            'dom_id'               => sanitize_text_field( $sb_in['dom_id'] ?? ( $existing_ui['start_button']['dom_id'] ?? '' ) ),
            'scroll_threshold'     => max( 0, min( 1, floatval( $sb_in['scroll_threshold'] ?? ( $existing_ui['start_button']['scroll_threshold'] ?? 0.5 ) ) ) ),
            'delay_ms'             => max( 0, intval( $sb_in['delay_ms'] ?? ( $existing_ui['start_button']['delay_ms'] ?? 1000 ) ) ),
            'only_if_eligible'     => isset( $sb_in['only_if_eligible'] ) ? (bool) $sb_in['only_if_eligible'] : false,
            'max_show_per_session' => max( 0, intval( $sb_in['max_show_per_session'] ?? ( $existing_ui['start_button']['max_show_per_session'] ?? 3 ) ) ),
        ];

        $gate_in = $ui['gate'] ?? [];
        $afsm = sanitize_text_field( $gate_in['anti_fast_scroll_mode'] ?? ( $existing_ui['gate']['anti_fast_scroll_mode'] ?? 'alert' ) );
        if ( ! in_array( $afsm, [ 'alert', 'none', 'strict' ], true ) ) {
            $afsm = 'alert';
        }

        $out['gate'] = [
            'enabled'               => isset( $gate_in['enabled'] ) ? (bool) $gate_in['enabled'] : false,
            'min_seconds'           => max( 0, intval( $gate_in['min_seconds'] ?? ( $existing_ui['gate']['min_seconds'] ?? 18 ) ) ),
            'min_interactions'      => max( 0, intval( $gate_in['min_interactions'] ?? ( $existing_ui['gate']['min_interactions'] ?? 6 ) ) ),
            'min_scroll_px'         => max( 0, intval( $gate_in['min_scroll_px'] ?? ( $existing_ui['gate']['min_scroll_px'] ?? 600 ) ) ),
            'min_depth_percent'     => max( 0, min( 100, intval( $gate_in['min_depth_percent'] ?? ( $existing_ui['gate']['min_depth_percent'] ?? 12 ) ) ) ),
            'min_pause_ms'          => max( 0, intval( $gate_in['min_pause_ms'] ?? ( $existing_ui['gate']['min_pause_ms'] ?? 2800 ) ) ),
            'anti_fast_scroll_mode' => $afsm,
        ];

        return $out;
    }

    public static function field_checkbox( $args ) {
        $options = get_option( self::OPTION_KEY, [] );
        $key = $args['key'];
        $label = $args['label'];
        $checked = ! empty( $options[ $key ] );
        printf(
            '<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            checked( $checked, true, false ),
            esc_html( $label )
        );
    }

    public static function field_number( $args ) {
        $options = get_option( self::OPTION_KEY, [] );
        $keyPath = explode( '.', $args['key'] );
        $val = $options;
        foreach ( $keyPath as $k ) {
            if ( isset( $val[ $k ] ) ) {
                $val = $val[ $k ];
            } else {
                $val = '';
                break;
            }
        }
        $value = $val === '' ? '' : intval( $val );
        $name = self::OPTION_KEY;
        foreach ( $keyPath as $k ) {
            $name .= '[' . $k . ']';
        }
        $min  = isset( $args['min'] ) ? intval( $args['min'] ) : 1;
        $max  = isset( $args['max'] ) ? intval( $args['max'] ) : 365;
        $step = isset( $args['step'] ) ? intval( $args['step'] ) : 1;
        $label = isset( $args['label'] ) ? $args['label'] : '';
        printf(
            '<input type="number" name="%s" value="%s" min="%d" max="%d" step="%d" class="small-text" /> <span class="description">%s</span>',
            esc_attr( $name ),
            esc_attr( $value ),
            $min,
            $max,
            $step,
            esc_html( $label )
        );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MySeoTask - Cài đặt chung', 'my-seo-task' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'myseotask_settings_group' );
                do_settings_sections( 'myseotask' );
                submit_button( __( 'Lưu cài đặt', 'my-seo-task' ) );
                ?>
            </form>
        </div>
        <?php
    }
}