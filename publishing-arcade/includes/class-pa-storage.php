<?php
if (!defined('ABSPATH')) { exit; }

/** Storage abstraction. Local storage is a staging adapter, not a complete access-control boundary. */
class PA_Storage {
    public static function private_root() {
        $configured = apply_filters('pa_private_storage_root', WP_CONTENT_DIR . '/pa-private');
        return untrailingslashit($configured);
    }
    public static function ensure_root() {
        $root = self::private_root();
        if (!is_dir($root) && !wp_mkdir_p($root)) {
            return new WP_Error('pa_storage_create_failed', 'Could not create private storage directory.');
        }
        if (!is_writable($root)) {
            return new WP_Error('pa_storage_not_writable', 'Private storage directory is not writable.');
        }
        $deny = "# Publishing Arcade private storage\nRequire all denied\n";
        if (!file_exists($root . '/.htaccess')) { @file_put_contents($root . '/.htaccess', $deny); }
        if (!file_exists($root . '/web.config')) {
            @file_put_contents($root . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
        }
        if (!file_exists($root . '/index.php')) { @file_put_contents($root . '/index.php', "<?php\n// Silence is golden.\n"); }
        return $root;
    }
    public static function put_attachment($attachment_id) {
        $source = get_attached_file($attachment_id, true);
        if (!$source || !is_file($source) || !is_readable($source)) {
            return new WP_Error('pa_source_missing', 'The original Media Library file is missing or unreadable.');
        }
        $root = self::ensure_root();
        if (is_wp_error($root)) { return $root; }
        $subdir = 'pdfs/' . gmdate('Y') . '/' . gmdate('m');
        $destination_dir = trailingslashit($root) . $subdir;
        if (!wp_mkdir_p($destination_dir)) { return new WP_Error('pa_storage_subdir_failed', 'Could not create private storage subdirectory.'); }
        $filename = 'attachment-' . absint($attachment_id) . '-' . wp_generate_password(12, false, false) . '.pdf';
        $destination = trailingslashit($destination_dir) . $filename;
        if (!copy($source, $destination)) { return new WP_Error('pa_copy_failed', 'Could not copy the source PDF into private storage.'); }
        $source_hash = hash_file('sha256', $source);
        $copy_hash = hash_file('sha256', $destination);
        if (!$source_hash || !$copy_hash || !hash_equals($source_hash, $copy_hash)) {
            @unlink($destination);
            return new WP_Error('pa_checksum_mismatch', 'The private copy did not pass SHA-256 integrity verification.');
        }
        return array(
            'storage_key' => $subdir . '/' . $filename,
            'checksum' => $copy_hash,
            'size' => filesize($destination),
            'source_checksum' => $source_hash,
        );
    }
}
