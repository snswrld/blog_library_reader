<?php
if (!defined('ABSPATH')) { exit; }

class PA_Migration_Manager {
    public static function scan() {
        global $wpdb;
        $table = PA_Database::table('pdf_migrations');
        $attachments = get_posts(array(
            'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf',
            'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
        ));
        $count = 0;
        foreach ($attachments as $attachment_id) {
            $path = get_attached_file($attachment_id, true);
            $url = wp_get_attachment_url($attachment_id);
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE attachment_id = %d", $attachment_id));
            $data = array(
                'attachment_id' => $attachment_id,
                'original_path' => $path ? $path : '',
                'original_url' => $url ? $url : '',
                'original_checksum' => ($path && is_file($path)) ? hash_file('sha256', $path) : null,
                'status' => 'pending', 'scanned_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            );
            if ($existing) {
                $wpdb->update($table, $data, array('id' => $existing));
                $migration_id = (int) $existing;
            } else {
                $data['created_at'] = current_time('mysql', true);
                $wpdb->insert($table, $data);
                $migration_id = (int) $wpdb->insert_id;
            }
            if ($migration_id) {
                self::scan_references($migration_id, (int) $attachment_id, $url);
                $count++;
            }
        }
        PA_Database::audit('pdf_library_scanned', 'migration', 0, array('pdf_count' => $count));
        return $count;
    }
    private static function scan_references($migration_id, $attachment_id, $url) {
        global $wpdb;
        $refs = PA_Database::table('pdf_page_references');
        $posts = $wpdb->posts;
        $like_id = '%' . $wpdb->esc_like((string) $attachment_id) . '%';
        $like_url = $url ? '%' . $wpdb->esc_like($url) . '%' : null;
        $where = "post_status NOT IN ('trash','auto-draft') AND post_type NOT IN ('attachment','revision') AND (post_content LIKE %s";
        $args = array($like_id);
        if ($like_url) { $where .= ' OR post_content LIKE %s'; $args[] = $like_url; }
        $where .= ')';
        $query = $wpdb->prepare("SELECT ID, post_type, post_content FROM $posts WHERE $where", $args);
        $matches = $wpdb->get_results($query);
        $wpdb->delete($refs, array('migration_id' => $migration_id));
        foreach ($matches as $post) {
            $reference = '';
            if ($url && strpos($post->post_content, $url) !== false) { $reference = $url; }
            elseif (strpos($post->post_content, (string) $attachment_id) !== false) { $reference = 'attachment_id:' . $attachment_id; }
            $wpdb->insert($refs, array(
                'migration_id' => $migration_id, 'post_id' => (int) $post->ID,
                'post_type' => sanitize_key($post->post_type), 'original_reference' => $reference,
                'reference_type' => 'content_match', 'replacement_status' => 'not_replaced',
                'created_at' => current_time('mysql', true),
            ));
        }
    }
    public static function prepare($migration_id) {
        global $wpdb;
        $table = PA_Database::table('pdf_migrations');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $migration_id));
        if (!$row) { return new WP_Error('pa_migration_missing', 'Migration record not found.'); }
        $attachment = get_post((int) $row->attachment_id);
        if (!$attachment || 'attachment' !== $attachment->post_type || 'application/pdf' !== get_post_mime_type($attachment)) {
            return new WP_Error('pa_invalid_attachment', 'The selected attachment is not a PDF.');
        }
        $result = PA_Storage::put_attachment((int) $row->attachment_id);
        if (is_wp_error($result)) {
            $wpdb->update($table, array('status' => 'failed', 'error_message' => $result->get_error_message(), 'updated_at' => current_time('mysql', true)), array('id' => $migration_id));
            return $result;
        }
        $wpdb->update($table, array(
            'private_storage_key' => $result['storage_key'], 'private_checksum' => $result['checksum'],
            'original_checksum' => $result['source_checksum'], 'status' => 'ready_for_review',
            'error_message' => null, 'processed_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true),
        ), array('id' => $migration_id));
        PA_Database::audit('pdf_private_copy_created', 'migration', $migration_id, array('attachment_id' => (int) $row->attachment_id, 'storage_key' => $result['storage_key'], 'checksum' => $result['checksum']));
        return $result;
    }
    public static function get_rows($limit = 100) {
        global $wpdb;
        $table = PA_Database::table('pdf_migrations');
        $limit = max(1, min(500, absint($limit)));
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT " . $limit);
    }
    public static function get_references($migration_id) {
        global $wpdb;
        $table = PA_Database::table('pdf_page_references');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE migration_id = %d ORDER BY post_id ASC", $migration_id));
    }
}
