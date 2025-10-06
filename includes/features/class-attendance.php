<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Attendance {
    public function __construct() {
        try {
            gymlite_log("GymLite_Attendance feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_shortcode('gymlite_attendance_log', [$this, 'attendance_log_shortcode']);
            add_action('wp_ajax_gymlite_checkin', [$this, 'handle_checkin']);
            add_action('wp_ajax_nopriv_gymlite_checkin', [$this, 'handle_checkin']);
            add_action('wp_ajax_gymlite_get_attendance', [$this, 'handle_get_attendance']);
            add_action('wp_ajax_gymlite_mark_attendance', [$this, 'handle_mark_attendance']);
            add_action('wp_ajax_gymlite_delete_attendance', [$this, 'handle_delete_attendance']);
            gymlite_log("GymLite_Attendance feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Attendance: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Attendance', 'gymlite'),
            __('Attendance', 'gymlite'),
            'manage_options',
            'gymlite-attendance',
            [$this, 'attendance_admin_page']
        );
    }

    public function attendance_admin_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-attendance">
            <h1><?php _e('Attendance Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Attendance Logs', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-attendance-logs">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Member ID', 'gymlite'); ?></th>
                            <th><?php _e('Class ID', 'gymlite'); ?></th>
                            <th><?php _e('Date', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Mark Attendance', 'gymlite'); ?></h2>
                <form id="gymlite-mark-attendance-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label for="member_id"><?php _e('Member ID', 'gymlite'); ?></label>
                        <input type="number" id="member_id" name="member_id" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="class_id"><?php _e('Class ID', 'gymlite'); ?></label>
                        <input type="number" id="class_id" name="class_id" class="uk-input" required>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Mark Attendance', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_mark_attendance', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function loadAttendance() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_get_attendance',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var tableBody = $('#gymlite-attendance-logs tbody');
                                tableBody.empty();
                                response.data.attendance.forEach(function(att) {
                                    tableBody.append('<tr><td>' + att.id + '</td><td>' + att.member_id + '</td><td>' + att.class_id + '</td><td>' + att.attendance_date + '</td><td><button class="uk-button uk-button-small uk-button-danger delete-att" data-id="' + att.id + '"><?php _e('Delete', 'gymlite'); ?></button></td></tr>');
                                });
                            }
                        }
                    });
                }
                loadAttendance();

                $('#gymlite-mark-attendance-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_mark_attendance',
                            member_id: $('#member_id').val(),
                            class_id: $('#class_id').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadAttendance();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                $(document).on('click', '.delete-att', function() {
                    if (confirm('<?php _e('Confirm delete?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_delete_attendance',
                                att_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadAttendance();
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

    public function attendance_log_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Login required.', 'gymlite') . '</p>';
        $member_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_attendance';
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE member_id = %d ORDER BY attendance_date DESC LIMIT 20", $member_id));

        ob_start();
        ?>
        <div class="gymlite-attendance-log uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Your Attendance Log', 'gymlite'); ?></h2>
                <?php if ($logs) : ?>
                    <table class="uk-table uk-table-striped">
                        <thead>
                            <tr>
                                <th><?php _e('Class', 'gymlite'); ?></th>
                                <th><?php _e('Date', 'gymlite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html(get_the_title($log->class_id)); ?></td>
                                    <td><?php echo esc_html($log->attendance_date); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="uk-text-center"><?php _e('No attendance records.', 'gymlite'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_checkin', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => __('Login required.', 'gymlite')]);
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) wp_send_json_error(['message' => __('Already checked in.', 'gymlite')]);
        $result = $wpdb->insert($table, [
            'member_id' => $member_id,
            'class_id' => $class_id,
            'attendance_date' => current_time('mysql')
        ]);
        if ($result) {
            gymlite_log("Check-in: member $member_id for class $class_id");
            wp_send_json_success(['message' => __('Checked in successfully!', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
        }
    }

    public function handle_get_attendance() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $attendance = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_attendance ORDER BY attendance_date DESC LIMIT 50");
        gymlite_log("Attendance logs retrieved");
        wp_send_json_success(['attendance' => $attendance]);
    }

    public function handle_mark_attendance() {
        check_ajax_referer('gymlite_mark_attendance', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $member_id = intval($_POST['member_id']);
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) wp_send_json_error(['message' => __('Already marked.', 'gymlite')]);
        $result = $wpdb->insert($table, [
            'member_id' => $member_id,
            'class_id' => $class_id,
            'attendance_date' => current_time('mysql')
        ]);
        if ($result) {
            gymlite_log("Attendance marked: member $member_id for class $class_id");
            wp_send_json_success(['message' => __('Attendance marked.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to mark attendance.', 'gymlite')]);
        }
    }

    public function handle_delete_attendance() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $att_id = intval($_POST['att_id']);
        global $wpdb;
        $result = $wpdb->delete($wpdb->prefix . 'gymlite_attendance', ['id' => $att_id]);
        if ($result) {
            gymlite_log("Attendance deleted: ID $att_id");
            wp_send_json_success(['message' => __('Attendance deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete attendance.', 'gymlite')]);
        }
    }
}
?>