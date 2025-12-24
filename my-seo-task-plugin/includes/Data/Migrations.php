<?php
namespace MySeoTask\Data;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Migrations {

    const OPTION_DB_VERSION = 'myseotask_db_version';
    const DB_VERSION = 1;

    public static function run() {
        $current = intval( get_option( self::OPTION_DB_VERSION, 0 ) );
        if ( $current >= self::DB_VERSION ) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
CREATE TABLE $table (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts DATETIME NOT NULL,
  session_id VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  page_type VARCHAR(32) NULL,
  task_id VARCHAR(64) NULL,
  event_type VARCHAR(48) NOT NULL,
  duration_ms INT NULL,
  reason_fail VARCHAR(128) NULL,
  meta_json LONGTEXT NULL,
  PRIMARY KEY (id),
  KEY idx_ts (ts),
  KEY idx_event (event_type),
  KEY idx_task (task_id),
  KEY idx_page (page_type),
  KEY idx_session (session_id)
) $charset;
        ";

        dbDelta( $sql );
        update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'myseotask_events';
    }
}