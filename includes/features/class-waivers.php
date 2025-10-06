<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Waivers {
    public function __construct() {
        try {
            gymlite_log("GymLite_Waivers feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post_gymlite_waiver', [$this, 'save_meta']);
            add_shortcode('gymlite_waivers', [$this, 'waivers_shortcode']);
            add_action('wp_ajax_gymlite_sign_waiver', [$this, 'handle_sign_waiver']);
            add_action('wp_ajax_gymlite_get_waivers', [$this, 'handle_get_waivers']);
            add_action('wp_ajax_gymlite_create_waiver', [$this, 'handle_create_waiver']);
            add_action('wp_ajax_gymlite_update_waiver', [$this, 'handle_update_waiver']);
            add_action('wp_ajax_gymlite_delete_waiver', [$this, 'handle_delete_waiver']);
            add_action('wp_ajax_gymlite_get_signed_waivers', [$this, 'handle_get_signed_waivers']);
            gymlite_log("GymLite_Waivers feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Waivers: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Waivers', 'gymlite'),
            __('Waivers', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_waiver'
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Signed Waivers', 'gymlite'),
            __('Signed Waivers', 'gymlite'),
            'manage_options',
            'gymlite-signed-waivers',
            [$this, 'signed_waivers_page']
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'gymlite_waiver_details',
            __('Waiver Details', 'gymlite'),
            [$this, 'waiver_meta_box'],
            'gymlite_waiver',
            'normal',
            'high'
        );
    }

    public function waiver_meta_box($post) {
        wp_nonce_field('gymlite_waiver_meta', 'gymlite_waiver_nonce');
        $expiration = get_post_meta($post->ID, '_gymlite_waiver_expiration', true);
        $required = get_post_meta($post->ID, '_gymlite_waiver_required', true);
        ?>
        <p>
            <label for="gymlite_waiver_expiration"><?php _e('Expiration Date', 'gymlite'); ?></label>
            <input type="date" id="gymlite_waiver_expiration" name="gymlite_waiver_expiration" value="<?php echo esc_attr($expiration); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_waiver_required"><?php _e('Required for Membership', 'gymlite'); ?></label>
            <input type="checkbox" id="gymlite_waiver_required" name="gymlite_waiver_required" <?php checked($required, 'yes'); ?> value="yes">
        </p>
        <p><?php _e('Use the post editor for waiver content.', 'gymlite'); ?></p>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['gymlite_waiver_nonce']) || !wp_verify_nonce($_POST['gymlite_waiver_nonce'], 'gymlite_waiver_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_gymlite_waiver_expiration', sanitize_text_field($_POST['gymlite_waiver_expiration']));
        update_post_meta($post_id, '_gymlite_waiver_required', isset($_POST['gymlite_waiver_required']) ? 'yes' : 'no');
        gymlite_log("Waiver meta saved for post ID $post_id");
    }

    public function signed_waivers_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-signed-waivers">
            <h1><?php _e('Signed Waivers', 'gymlite'); ?></h1>
            <table class="uk-table uk-table-striped" id="gymlite-signed-waivers-table">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'gymlite'); ?></th>
                        <th><?php _e('Member ID', 'gymlite'); ?></th>
                        <th><?php _e('Waiver ID', 'gymlite'); ?></th>
                        <th><?php _e('Signed Date', 'gymlite'); ?></th>
                        <th><?php _e('Signature', 'gymlite'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gymlite_get_signed_waivers',
                        nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var tableBody = $('#gymlite-signed-waivers-table tbody');
                            response.data.signed.forEach(function(sig) {
                                tableBody.append('<tr><td>' + sig.id + '</td><td>' + sig.member_id + '</td><td>' + sig.waiver_id + '</td><td>' + sig.signed_date + '</td><td>' + sig.signature + '</td></tr>');
                            });
                        }
                    }
                });
            });
        </script>
        <?php
    }

    public function waivers_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Login required.', 'gymlite') . '</p>';
        $member_id = get_current_user_id();
        $waivers = get_posts(['post_type' => 'gymlite_waiver', 'posts_per_page' => -1]);
        global $wpdb;
        $signed_table = $wpdb->prefix . 'gymlite_waivers_signed';

        ob_start();
        ?>
        <div class="gymlite-waivers uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Waivers', 'gymlite'); ?></h2>
                <?php foreach ($waivers as $waiver) : 
                    $signed = $wpdb->get_var($wpdb->prepare("SELECT id FROM $signed_table WHERE member_id = %d AND waiver_id = %d", $member_id, $waiver->ID));
                ?>
                    <div class="uk-card uk-card-default uk-margin">
                        <div class="uk-card-header">
                            <h3 class="uk-card-title"><?php echo esc_html($waiver->post_title); ?></h3>
                        </div>
                        <div class="uk-card-body">
                            <?php echo wp_kses_post($waiver->post_content); ?>
                            <?php if (!$signed) : ?>
                                <form class="gymlite-sign-waiver-form">
                                    <div class="uk-margin">
                                        <label for="signature-<?php echo $waiver->ID; ?>"><?php _e('Signature', 'gymlite'); ?></label>
                                        <input type="text" id="signature-<?php echo $waiver->ID; ?>" name="signature" class="uk-input" required placeholder="<?php _e('Type your name as signature', 'gymlite'); ?>">
                                    </div>
                                    <button type="submit" class="uk-button uk-button-primary" data-waiver-id="<?php echo $waiver->ID; ?>"><?php _e('Sign Waiver', 'gymlite'); ?></button>
                                    <?php wp_nonce_field('gymlite_sign_waiver', 'nonce'); ?>
                                </form>
                            <?php else : ?>
                                <p class="uk-text-success"><?php _e('Signed on ', 'gymlite'); ?><?php echo esc_html($signed->signed_date); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.gymlite-sign-waiver-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_sign_waiver',
                            waiver_id: form.find('button').data('waiver-id'),
                            signature: form.find('input[name="signature"]').val(),
                            nonce: form.find('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_sign_waiver', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => __('Login required.', 'gymlite')]);
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = get_current_user_id();
        if (empty($waiver_id) || empty($signature)) wp_send_json_error(['message' => __('Invalid data.', 'gymlite')]);
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_waivers_signed';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE member_id = %d AND waiver_id = %d", $member_id, $waiver_id));
        if ($exists) wp_send_json_error(['message' => __('Already signed.', 'gymlite')]);
        $result = $wpdb->insert($table, [
            'member_id' => $member_id,
            'waiver_id' => $waiver_id,
            'signed_date' => current_time('mysql'),
            'signature' => $signature
        ]);
        if ($result) {
            gymlite_log("Waiver signed: waiver $waiver_id by member $member_id");
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Signing failed.', 'gymlite')]);
        }
    }

    public function handle_get_waivers() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $waivers = get_posts(['post_type' => 'gymlite_waiver', 'posts_per_page' => -1]);
        $data = [];
        foreach ($waivers as $waiver) {
            $data[] = [
                'id' => $waiver->ID,
                'title' => $waiver->post_title,
                'content' => $waiver->post_content,
                'expiration' => get_post_meta($waiver->ID, '_gymlite_waiver_expiration', true),
                'required' => get_post_meta($waiver->ID, '_gymlite_waiver_required', true),
            ];
        }
        gymlite_log("Waivers retrieved via AJAX");
        wp_send_json_success(['waivers' => $data]);
    }

    public function handle_create_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $expiration = sanitize_text_field($_POST['expiration']);
        $required = sanitize_text_field($_POST['required']);

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'gymlite_waiver',
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) wp_send_json_error(['message' => __('Failed to create waiver.', 'gymlite')]);

        update_post_meta($post_id, '_gymlite_waiver_expiration', $expiration);
        update_post_meta($post_id, '_gymlite_waiver_required', $required);

        gymlite_log("Waiver created: ID $post_id");
        wp_send_json_success(['message' => __('Waiver created.', 'gymlite')]);
    }

    public function handle_update_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $post_id = intval($_POST['post_id']);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $expiration = sanitize_text_field($_POST['expiration']);
        $required = sanitize_text_field($_POST['required']);

        wp_update_post(['ID' => $post_id, 'post_title' => $title, 'post_content' => $content]);

        update_post_meta($post_id, '_gymlite_waiver_expiration', $expiration);
        update_post_meta($post_id, '_gymlite_waiver_required', $required);

        gymlite_log("Waiver updated: ID $post_id");
        wp_send_json_success(['message' => __('Waiver updated.', 'gymlite')]);
    }

    public function handle_delete_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $post_id = intval($_POST['post_id']);
        if (wp_delete_post($post_id, true)) {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'gymlite_waivers_signed', ['waiver_id' => $post_id]);
            gymlite_log("Waiver deleted: ID $post_id");
            wp_send_json_success(['message' => __('Waiver deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete waiver.', 'gymlite')]);
        }
    }

    public function handle_get_signed_waivers() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $signed = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_waivers_signed ORDER BY signed_date DESC");
        gymlite_log("Signed waivers retrieved");
        wp_send_json_success(['signed' => $signed]);
    }
}
?>