<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Access_Control {
    public function __construct() {
        try {
            gymlite_log("GymLite_Access_Control feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_shortcode('gymlite_access_status', [$this, 'access_status_shortcode']);
            add_action('wp_ajax_gymlite_log_access', [$this, 'handle_log_access']);
            add_action('wp_ajax_gymlite_get_access_logs', [$this, 'handle_get_access_logs']);
            add_action('wp_ajax_gymlite_check_access', [$this, 'handle_check_access']);
            add_action('wp_ajax_gymlite_update_access_rules', [$this, 'handle_update_access_rules']);
            gymlite_log("GymLite_Access_Control feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Access_Control: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Access Control', 'gymlite'),
            __('Access Control', 'gymlite'),
            'manage_options',
            'gymlite-access-control',
            [$this, 'access_control_admin_page']
        );
    }

    public function access_control_admin_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-access-control">
            <h1><?php _e('Access Control Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Access Logs', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-access-logs-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Member ID', 'gymlite'); ?></th>
                            <th><?php _e('Access Time', 'gymlite'); ?></th>
                            <th><?php _e('Status', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Update Access Rules', 'gymlite'); ?></h2>
                <form id="gymlite-access-rules-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label for="rule_type"><?php _e('Rule Type', 'gymlite'); ?></label>
                        <select id="rule_type" name="rule_type" class="uk-select">
                            <option value="membership"><?php _e('Based on Membership', 'gymlite'); ?></option>
                            <option value="time"><?php _e('Time-Based', 'gymlite'); ?></option>
                        </select>
                    </div>
                    <div class="uk-margin">
                        <label for="rule_value"><?php _e('Rule Value', 'gymlite'); ?></label>
                        <input type="text" id="rule_value" name="rule_value" class="uk-input" placeholder="<?php _e('e.g., premium or 00:00-23:59', 'gymlite'); ?>">
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Update Rules', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_update_access_rules', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gymlite_get_access_logs',
                        nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var tableBody = $('#gymlite-access-logs-table tbody');
                            response.data.logs.forEach(function(log) {
                                tableBody.append('<tr><td>' + log.id + '</td><td>' + log.member_id + '</td><td>' + log.access_time + '</td><td>' + log.status + '</td></tr>');
                            });
                        }
                    }
                });

                $('#gymlite-access-rules-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_update_access_rules',
                            rule_type: $('#rule_type').val(),
                            rule_value: $('#rule_value').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function access_status_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Login required.', 'gymlite') . '</p>';
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true);
        $status = ($membership_type && $membership_type !== 'trial') ? __('Access Granted', 'gymlite') : __('Access Denied', 'gymlite');

        ob_start();
        ?>
        <div class="gymlite-access-status uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Your Access Status', 'gymlite'); ?></h2>
                <p class="uk-text-center uk-text-large <?php echo ($status === __('Access Granted', 'gymlite')) ? 'uk-text-success' : 'uk-text-danger'; ?>"><?php echo esc_html($status); ?></p>
                <button class="uk-button uk-button-primary uk-width-1-1" id="gymlite-log-access"><?php _e('Log Access Attempt', 'gymlite'); ?></button>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-log-access').on('click', function() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_log_access',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
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

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => __('Login required.', 'gymlite')]);
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true);
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert($table, [
            'member_id' => $member_id,
            'access_time' => current_time('mysql'),
            'status' => $status
        ]);
        if ($result) {
            gymlite_log("Access logged: member $member_id, status $status");
            wp_send_json_success(['message' => __('Access attempt logged. Status: ' . ucfirst($status), 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Logging failed.', 'gymlite')]);
        }
    }

    public function handle_get_access_logs() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_access_logs ORDER BY access_time DESC LIMIT 100");
        gymlite_log("Access logs retrieved");
        wp_send_json_success(['logs' => $logs]);
    }

    public function handle_check_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        $member_id = intval($_POST['member_id']);
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true);
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        gymlite_log("Access check for member $member_id: $status");
        wp_send_json_success(['status' => $status]);
    }

    public function handle_update_access_rules() {
        check_ajax_referer('gymlite_update_access_rules', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $rule_type = sanitize_text_field($_POST['rule_type']);
        $rule_value = sanitize_text_field($_POST['rule_value']);
        // Save rules to options or DB
        update_option('gymlite_access_rule_' . $rule_type, $rule_value);
        gymlite_log("Access rule updated: $rule_type = $rule_value");
        wp_send_json_success(['message' => __('Access rules updated.', 'gymlite')]);
    }
}
?>