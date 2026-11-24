<?php
if (!defined('ABSPATH')) { exit; }

class PA_Database {
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'pa_' . $name;
    }
    public static function activate() {
        self::create_tables();
        update_option('pa_db_version', PA_DB_VERSION);
        update_option('pa_storage_driver', get_option('pa_storage_driver', 'local'));
    }
    public static function maybe_upgrade() {
        if (get_option('pa_db_version') !== PA_DB_VERSION) {
            self::create_tables();
            update_option('pa_db_version', PA_DB_VERSION);
        }
    }
    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $migrations = self::table('pdf_migrations');
        $references = self::table('pdf_page_references');
        $folders = self::table('library_folders');
        $items = self::table('library_items');
        $files = self::table('content_files');
        $progress = self::table('reader_progress');
        $bookmarks = self::table('reader_bookmarks');
        $audit = self::table('audit_log');

        $sql = array();
        $sql[] = "CREATE TABLE $migrations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL,
            original_path text NOT NULL,
            original_url text NULL,
            original_checksum char(64) NULL,
            private_storage_key text NULL,
            private_checksum char(64) NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            activation_status varchar(32) NOT NULL DEFAULT 'not_activated',
            error_message text NULL,
            scanned_at datetime NULL,
            processed_at datetime NULL,
            activated_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY attachment_id (attachment_id),
            KEY status (status)
        ) $charset;";
        $sql[] = "CREATE TABLE $references (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            migration_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            post_type varchar(32) NOT NULL,
            original_reference text NULL,
            reference_type varchar(32) NOT NULL DEFAULT 'content_match',
            replacement_status varchar(32) NOT NULL DEFAULT 'not_replaced',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY migration_post (migration_id,post_id),
            KEY post_id (post_id)
        ) $charset;";
        $sql[] = "CREATE TABLE $folders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(190) NOT NULL,
            slug varchar(190) NOT NULL,
            description text NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY parent_id (parent_id),
            KEY slug (slug)
        ) $charset;";
        $sql[] = "CREATE TABLE $items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            publication_id bigint(20) unsigned NOT NULL,
            folder_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL,
            post_id bigint(20) unsigned NULL,
            creator_user_id bigint(20) unsigned NULL,
            status varchar(32) NOT NULL DEFAULT 'draft',
            access_mode varchar(32) NOT NULL DEFAULT 'inherit',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY publication_id (publication_id),
            KEY folder_id (folder_id),
            KEY post_id (post_id)
        ) $charset;";
        $sql[] = "CREATE TABLE $files (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            publication_id bigint(20) unsigned NOT NULL,
            storage_key text NOT NULL,
            mime_type varchar(100) NOT NULL DEFAULT 'application/pdf',
            checksum char(64) NOT NULL,
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            is_source_backup tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY publication_id (publication_id)
        ) $charset;";
        $sql[] = "CREATE TABLE $progress (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            publication_id bigint(20) unsigned NOT NULL,
            current_page int(10) unsigned NOT NULL DEFAULT 1,
            position_data longtext NULL,
            completion_percent decimal(6,3) NOT NULL DEFAULT 0,
            sequence_no bigint(20) unsigned NOT NULL DEFAULT 0,
            last_read_at datetime NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_publication (user_id,publication_id),
            KEY last_read_at (last_read_at)
        ) $charset;";
        $sql[] = "CREATE TABLE $bookmarks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            publication_id bigint(20) unsigned NOT NULL,
            page_number int(10) unsigned NOT NULL,
            label varchar(190) NULL,
            position_data longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_publication (user_id,publication_id)
        ) $charset;";
        $sql[] = "CREATE TABLE $audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_user_id bigint(20) unsigned NULL,
            event_type varchar(100) NOT NULL,
            object_type varchar(100) NULL,
            object_id bigint(20) unsigned NULL,
            event_data longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY object_lookup (object_type,object_id)
        ) $charset;";
        foreach ($sql as $statement) { dbDelta($statement); }
    }
    public static function audit($event, $object_type = '', $object_id = 0, $data = array()) {
        global $wpdb;
        $wpdb->insert(self::table('audit_log'), array(
            'actor_user_id' => get_current_user_id() ?: null,
            'event_type' => sanitize_key($event),
            'object_type' => sanitize_key($object_type),
            'object_id' => absint($object_id) ?: null,
            'event_data' => wp_json_encode($data),
            'created_at' => current_time('mysql', true),
        ));
    }
}
