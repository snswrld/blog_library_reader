<?php
if (!defined('ABSPATH')) { exit; }

class PA_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_pa_scan_pdfs', array(__CLASS__, 'handle_scan'));
        add_action('admin_post_pa_prepare_pdf', array(__CLASS__, 'handle_prepare'));
    }
    public static function menu() {
        add_menu_page('Publishing Arcade', 'Publishing Arcade', 'manage_options', 'publishing-arcade', array(__CLASS__, 'render'), 'dashicons-book-alt', 58);
        add_submenu_page('publishing-arcade', 'PDF Migration Manager', 'PDF Migration', 'manage_options', 'publishing-arcade', array(__CLASS__, 'render'));
    }
    private static function notice_from_query() {
        if (!empty($_GET['pa_notice'])) {
            $notice = sanitize_key(wp_unslash($_GET['pa_notice']));
            $messages = array(
                'scanned' => 'PDF scan completed.', 'prepared' => 'Private PDF copy created and checksum verified.',
                'error' => 'The operation failed. Review the error shown below or the migration record.',
            );
            if (isset($messages[$notice])) { echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>'; }
        }
        if (!empty($_GET['pa_error'])) { echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['pa_error']))) . '</p></div>'; }
    }
    public static function render() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to access this page.', 'publishing-arcade')); }
        $rows = PA_Migration_Manager::get_rows(200);
        echo '<div class="wrap"><h1>Publishing Arcade — PDF Migration Manager</h1>';
        self::notice_from_query();
        echo '<p>Scan the Media Library, review every page reference, then stage individual PDFs. Staging does not replace page embeds, change access rules, or disable the original Media Library URL.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="pa_scan_pdfs">';
        wp_nonce_field('pa_scan_pdfs');
        submit_button('Scan Media Library PDFs', 'primary', 'submit', false);
        echo '</form>';
        echo '<hr><h2>Discovered PDFs</h2>';
        if (!$rows) { echo '<p>No PDFs have been scanned yet.</p>'; }
        else {
            echo '<table class="widefat striped"><thead><tr><th>Attachment</th><th>Source</th><th>Status</th><th>Page references</th><th>Private copy</th><th>Action</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $attachment = get_post((int) $row->attachment_id);
                $title = $attachment ? get_the_title($attachment) : '(attachment missing)';
                $refs = PA_Migration_Manager::get_references($row->id);
                echo '<tr><td><strong>' . esc_html($title) . '</strong><br><small>Attachment #' . (int) $row->attachment_id . ' · Migration #' . (int) $row->id . '</small></td>';
                echo '<td>' . esc_html(wp_basename((string) $row->original_path)) . '<br><small>' . esc_html((string) $row->original_url) . '</small></td>';
                echo '<td>' . esc_html($row->status) . ($row->error_message ? '<br><span style="color:#b32d2e">' . esc_html($row->error_message) . '</span>' : '') . '</td>';
                echo '<td>';
                if ($refs) {
                    foreach ($refs as $ref) {
                        $edit = get_edit_post_link($ref->post_id);
                        echo '<div>' . ($edit ? '<a href="' . esc_url($edit) . '">' : '') . esc_html(get_the_title($ref->post_id) ?: '(untitled)') . ' (#' . (int) $ref->post_id . ')' . ($edit ? '</a>' : '') . '</div>';
                    }
                } else { echo 'No references detected'; }
                echo '</td><td>' . ($row->private_storage_key ? '<code>' . esc_html($row->private_storage_key) . '</code><br><small>SHA-256 verified</small>' : 'Not staged') . '</td><td>';
                if ('ready_for_review' !== $row->status) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    echo '<input type="hidden" name="action" value="pa_prepare_pdf"><input type="hidden" name="migration_id" value="' . (int) $row->id . '">';
                    wp_nonce_field('pa_prepare_pdf_' . (int) $row->id);
                    submit_button('Create private copy', 'secondary', 'submit', false);
                    echo '</form>';
                } else { echo 'Ready for manual review'; }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<h2>Important safeguards</h2><ul><li>Original PDFs are not deleted or disabled by this tool.</li><li>Page content and existing viewer shortcodes are not rewritten.</li><li>Each discovered PDF is staged separately and checksum-verified.</li><li>Reference scanning is heuristic: inspect the listed pages and search for custom viewer shortcodes or dynamically generated embeds before converting.</li><li>Local private storage requires web-server access rules. The included .htaccess protects Apache; Nginx and other servers need an equivalent deny rule before storing sensitive files here.</li></ul>';
        echo '</div>';
    }
    public static function handle_scan() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('pa_scan_pdfs');
        $count = PA_Migration_Manager::scan();
        wp_safe_redirect(add_query_arg(array('page' => 'publishing-arcade', 'pa_notice' => 'scanned', 'count' => $count), admin_url('admin.php')));
        exit;
    }
    public static function handle_prepare() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        $id = isset($_POST['migration_id']) ? absint($_POST['migration_id']) : 0;
        check_admin_referer('pa_prepare_pdf_' . $id);
        $result = PA_Migration_Manager::prepare($id);
        if (is_wp_error($result)) {
            $url = add_query_arg(array('page' => 'publishing-arcade', 'pa_notice' => 'error', 'pa_error' => $result->get_error_message()), admin_url('admin.php'));
        } else {
            $url = add_query_arg(array('page' => 'publishing-arcade', 'pa_notice' => 'prepared'), admin_url('admin.php'));
        }
        wp_safe_redirect($url);
        exit;
    }
}
