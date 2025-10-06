<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Progression {
    public function __construct() {
        try {
            gymlite_log("GymLite_Progression feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_shortcode('gymlite_progression', [$this, 'progression_shortcode']);
            add_action('wp_ajax_gymlite_promote_member', [$this, 'handle_promote_member']);
            add_action('wp_ajax_gymlite_get_progression', [$this, 'handle_get_progression']);
            add_action('wp_ajax_gymlite_update_progression', [$this, 'handle_update_progression']);
            add_action('wp_ajax_gymlite_delete_progression', [$this, 'handle_delete_progression']);
            gymlite_log("GymLite_Progression feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Progression: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Progression Tracking', 'gymlite'),
            __('Progression', 'gymlite'),
            'manage_options',
            'gymlite-progression',
            [$this, 'progression_admin_page']
        );
    }

    public function progression_admin_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-progression">
            <h1><?php _e('Progression Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Promote Member', 'gymlite'); ?></h2>
                <form id="gymlite-promote-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label for="member_id"><?php _e('Member ID', 'gymlite'); ?></label>
                        <input type="number" id="member_id" name="member_id" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="level"><?php _e('New Level (e.g., White Belt)', 'gymlite'); ?></label>
                        <input type="text" id="level" name="level" class="uk-input" required>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Promote', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_promote_member', 'nonce'); ?>
                </form>
            </div>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Progression History', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-progression-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Member ID', 'gymlite'); ?></th>
                            <th><?php _e('Level', 'gymlite'); ?></th>
                            <th><?php _e('Promoted Date', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function loadProgression() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_get_progression',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var tableBody = $('#gymlite-progression-table tbody');
                                tableBody.empty();
                                response.data.progression.forEach(function(prog) {
                                    tableBody.append('<tr><td>' + prog.id + '</td><td>' + prog.member_id + '</td><td>' + prog.level + '</td><td>' + prog.promoted_date + '</td><td><button class="uk-button uk-button-small uk-button-primary edit-prog" data-id="' + prog.id + '" data-level="' + prog.level + '"><?php _e('Edit', 'gymlite'); ?></button> <button class="uk-button uk-button-small uk-button-danger delete-prog" data-id="' + prog.id + '"><?php _e('Delete', 'gymlite'); ?></button></td></tr>');
                                });
                            }
                        }
                    });
                }
                loadProgression();

                $('#gymlite-promote-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_promote_member',
                            member_id: $('#member_id').val(),
                            level: $('#level').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadProgression();
                                $('#gymlite-promote-form')[0].reset();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                $(document).on('click', '.edit-prog', function() {
                    var id = $(this).data('id');
                    var newLevel = prompt('<?php _e('New Level:', 'gymlite'); ?>', $(this).data('level'));
                    if (newLevel) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_update_progression',
                                prog_id: id,
                                level: newLevel,
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadProgression();
                                } else {
                                    alert(response.data.message);
                                }
                            }
                        });
                    }
                });

                $(document).on('click', '.delete-prog', function() {
                    if (confirm('<?php _e('Are you sure?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_delete_progression',
                                prog_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadProgression();
                                } else {
                                    alert(response.data.message);
                                }
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    public function progression_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Login required.', 'gymlite') . '</p>';
        $member_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_progression';
        $history = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE member_id = %d ORDER BY promoted_date ASC", $member_id));

        ob_start();
        ?>
        <div class="gymlite-progression uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Your Progression History', 'gymlite'); ?></h2>
                <?php if ($history) : ?>
                    <ul class="uk-list uk-list-striped">
                        <?php foreach ($history as $prog) : ?>
                            <li><?php echo esc_html($prog->level); ?> - <?php echo esc_html($prog->promoted_date); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="uk-text-center"><?php _e('No progression records yet.', 'gymlite'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_promote_member() {
        check_ajax_referer('gymlite_promote_member', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($member_id) || empty($level)) wp_send_json_error(['message' => __('Invalid data.', 'gymlite')]);
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert($table, [
            'member_id' => $member_id,
            'level' => $level,
            'promoted_date' => current_time('mysql')
        ]);
        if ($result) {
            update_post_meta($member_id, '_gymlite_current_level', $level); // Update member's current level
            gymlite_log("Member promoted: $member_id to $level");
            wp_send_json_success(['message' => __('Member promoted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
        }
    }

    public function handle_get_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $progression = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_progression ORDER BY promoted_date DESC LIMIT 100");
        gymlite_log("Progression history retrieved");
        wp_send_json_success(['progression' => $progression]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $prog_id = intval($_POST['prog_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $result = $wpdb->update($wpdb->prefix . 'gymlite_progression', ['level' => $level], ['id' => $prog_id]);
        if ($result !== false) {
            gymlite_log("Progression updated: ID $prog_id to $level");
            wp_send_json_success(['message' => __('Progression updated.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Update failed.', 'gymlite')]);
        }
    }

    public function handle_delete_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $prog_id = intval($_POST['prog_id']);
        global $wpdb;
        $result = $wpdb->delete($wpdb->prefix . 'gymlite_progression', ['id' => $prog_id]);
        if ($result) {
            gymlite_log("Progression deleted: ID $prog_id");
            wp_send_json_success(['message' => __('Progression deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Delete failed.', 'gymlite')]);
        }
    }
}
?>