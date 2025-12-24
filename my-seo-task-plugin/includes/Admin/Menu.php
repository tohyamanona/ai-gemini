<?php
namespace MySeoTask\Admin;

use MySeoTask\Admin\Pages\SettingsPage;
use MySeoTask\Admin\Pages\TasksPage;
use MySeoTask\Admin\Pages\RulesPage;
use MySeoTask\Admin\Pages\AnalyticsPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Menu {
    public static function register_hooks() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        SettingsPage::register_hooks();
        TasksPage::register_hooks();
        RulesPage::register_hooks();
        AnalyticsPage::register_hooks();
    }

    public static function add_menu() {
        add_menu_page(
            __( 'MySeoTask', 'my-seo-task' ),
            __( 'MySeoTask', 'my-seo-task' ),
            'manage_options',
            'myseotask',
            [ SettingsPage::class, 'render' ],
            'dashicons-visibility',
            56
        );

        add_submenu_page(
            'myseotask',
            __( 'General', 'my-seo-task' ),
            __( 'General', 'my-seo-task' ),
            'manage_options',
            'myseotask',
            [ SettingsPage::class, 'render' ]
        );

        add_submenu_page(
            'myseotask',
            __( 'Tasks', 'my-seo-task' ),
            __( 'Tasks', 'my-seo-task' ),
            'manage_options',
            'myseotask-tasks',
            [ TasksPage::class, 'render' ]
        );

        add_submenu_page(
            'myseotask',
            __( 'Rules', 'my-seo-task' ),
            __( 'Rules', 'my-seo-task' ),
            'manage_options',
            'myseotask-rules',
            [ RulesPage::class, 'render' ]
        );

        add_submenu_page(
            'myseotask',
            __( 'Analytics', 'my-seo-task' ),
            __( 'Analytics', 'my-seo-task' ),
            'manage_options',
            'myseotask-analytics',
            [ AnalyticsPage::class, 'render' ]
        );
    }
}