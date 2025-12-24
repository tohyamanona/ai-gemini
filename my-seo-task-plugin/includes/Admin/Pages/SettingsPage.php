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
        $out = [];
        $out['enabled']    = ! empty( $input['enabled'] );
        $out['debug_mode'] = ! empty( $input['debug_mode'] );

        $ret = 30;
        if ( isset( $input['telemetry']['retention_days'] ) ) {
            $ret = intval( $input['telemetry']['retention_days'] );
        }
        if ( $ret < 1 )   $ret = 1;
        if ( $ret > 365 ) $ret = 365;
        $out['telemetry'] = [
            'retention_days' => $ret,
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