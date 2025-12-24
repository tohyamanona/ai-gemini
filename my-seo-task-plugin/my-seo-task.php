<?php
/**
 * Plugin Name: My SEO Task
 * Description: Mini-game nhiệm vụ SEO (Start Button, Flow, Overlay, Diamonds, TOTP popup). Skeleton kiến trúc mới, giữ nguyên hành vi front-end hiện tại.
 * Version: 0.6.0
 * Author: Bạn
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MST_PLUGIN_FILE', __FILE__ );
define( 'MST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MST_PLUGIN_VERSION', '0.6.0' );

// Autoload tối giản (thủ công)
require_once MST_PLUGIN_DIR . 'includes/Core/Plugin.php';
require_once MST_PLUGIN_DIR . 'includes/Core/Config.php';
require_once MST_PLUGIN_DIR . 'includes/Core/Assets.php';
require_once MST_PLUGIN_DIR . 'includes/Front/Bootstrap.php';
require_once MST_PLUGIN_DIR . 'includes/Admin/Menu.php';
require_once MST_PLUGIN_DIR . 'includes/Admin/Pages/SettingsPage.php';
require_once MST_PLUGIN_DIR . 'includes/Admin/Pages/TasksPage.php';
require_once MST_PLUGIN_DIR . 'includes/Admin/Pages/RulesPage.php';
require_once MST_PLUGIN_DIR . 'includes/Admin/Pages/AnalyticsPage.php';
require_once MST_PLUGIN_DIR . 'includes/Admin/Pages/UIPage.php'; // mới
require_once MST_PLUGIN_DIR . 'includes/Rest/Routes.php';
require_once MST_PLUGIN_DIR . 'includes/Rest/Controllers/ConfigController.php';
require_once MST_PLUGIN_DIR . 'includes/Rest/Controllers/EventsController.php';
require_once MST_PLUGIN_DIR . 'includes/Telemetry/EventService.php';
require_once MST_PLUGIN_DIR . 'includes/Telemetry/Aggregator.php';
require_once MST_PLUGIN_DIR . 'includes/Telemetry/Cleanup.php';
require_once MST_PLUGIN_DIR . 'includes/Data/Migrations.php';

MySeoTask\Core\Plugin::init();