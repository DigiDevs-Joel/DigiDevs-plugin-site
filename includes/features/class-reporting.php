<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Reporting {
    public function __construct() {
        try {
            gymlite_log("GymLite_Reporting feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('wp_ajax_gymlite_generate_report', [$this, 'handle_generate_report']);
            add_action('wp_ajax_gymlite_export_report', [$this, 'handle_export_report']);
            add_action('wp_ajax_gymlite_get_reports', [$this, 'handle_get_reports']);
            add_action('wp_ajax_gymlite_delete_report', [$this, 'handle_delete_report']);
            gymlite_log("GymLite_Reporting feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Reporting: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Reports', 'gymlite'),
            __('Reports', 'gymlite'),
            'manage_options',
            'gymlite-reports',
            [$this, 'reports_admin_page']
        );
    }

    public function reports_admin_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-reports">
            <h1><?php _e('Reporting Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Generate Report', 'gymlite'); ?></h2>
                <form id="gymlite-generate-report-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label for="report_type"><?php _e('Report Type', 'gymlite'); ?></label>
                        <select id="report_type" name="report_type" class="uk-select" required>
                            <option value="members"><?php _e('Members', 'gymlite'); ?></option>
                            <option value="payments"><?php _e('Payments', 'gymlite'); ?></option>
                            <option value="attendance"><?php _e('Attendance', 'gymlite'); ?></option>
                            <option value="leads"><?php _e('Leads', 'gymlite'); ?></option>
                            <option value="progression"><?php _e('Progression', 'gymlite'); ?></option>
                        </select>
                    </div>
                    <div class="uk-margin">
                        <label for="start_date"><?php _e('Start Date', 'gymlite'); ?></label>
                        <input type="date" id="start_date" name="start_date" class="uk-input">
                    </div>
                    <div class="uk-margin">
                        <label for="end_date"><?php _e('End Date', 'gymlite'); ?></label>
                        <input type="date" id="end_date" name="end_date" class="uk-input">
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Generate Report', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_generate_report', 'nonce'); ?>
                </form>
            </div>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Saved Reports', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-reports-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Type', 'gymlite'); ?></th>
                            <th><?php _e('Generated At', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function loadReports() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_get_reports',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var tableBody = $('#gymlite-reports-table tbody');
                                tableBody.empty();
                                response.data.reports.forEach(function(report) {
                                    tableBody.append('<tr><td>' + report.id + '</td><td>' + report.report_type + '</td><td>' + report.generated_at + '</td><td><button class="uk-button uk-button-small uk-button-primary export-report" data-id="' + report.id + '"><?php _e('Export', 'gymlite'); ?></button> <button class="uk-button uk-button-small uk-button-danger delete-report" data-id="' + report.id + '"><?php _e('Delete', 'gymlite'); ?></button></td></tr>');
                                });
                            }
                        }
                    });
                }
                loadReports();

                $('#gymlite-generate-report-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_generate_report',
                            report_type: $('#report_type').val(),
                            start_date: $('#start_date').val(),
                            end_date: $('#end_date').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadReports();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                $(document).on('click', '.export-report', function() {
                    window.location = '<?php echo admin_url('admin-ajax.php'); ?>?action=gymlite_export_report&report_id=' + $(this).data('id') + '&nonce=<?php echo wp_create_nonce('gymlite_nonce'); ?>';
                });

                $(document).on('click', '.delete-report', function() {
                    if (confirm('<?php _e('Are you sure?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_delete_report',
                                report_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadReports();
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

    public function handle_generate_report() {
        check_ajax_referer('gymlite_generate_report', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $type = sanitize_text_field($_POST['report_type']);
        $start = sanitize_text_field($_POST['start_date']);
        $end = sanitize_text_field($_POST['end_date']);
        global $wpdb;
        $data = [];
        switch ($type) {
            case 'members':
                $data = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type = 'gymlite_member' AND post_status = 'publish'");
                break;
            case 'payments':
                $query = "SELECT * FROM {$wpdb->prefix}gymlite_payments";
                if ($start && $end) $query .= " WHERE payment_date BETWEEN '$start' AND '$end'";
                $data = $wpdb->get_results($query);
                break;
            // Add cases for attendance, leads, progression, etc.
            default:
                wp_send_json_error(['message' => __('Invalid report type.', 'gymlite')]);
        }
        $serialized_data = json_encode($data);
        $result = $wpdb->insert($wpdb->prefix . 'gymlite_reports', [
            'report_type' => $type,
            'generated_at' => current_time('mysql'),
            'data' => $serialized_data
        ]);
        if ($result) {
            gymlite_log("Report generated: type $type");
            wp_send_json_success(['message' => __('Report generated.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to generate report.', 'gymlite')]);
        }
    }

    public function handle_export_report() {
        if (!isset($_GET['report_id']) || !wp_verify_nonce($_GET['nonce'], 'gymlite_nonce')) die('Unauthorized');
        if (!current_user_can('manage_options')) die('Unauthorized');
        $report_id = intval($_GET['report_id']);
        global $wpdb;
        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_reports WHERE id = %d", $report_id));
        if (!$report) die('Report not found');
        $data = json_decode($report->data, true);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="gymlite-' . $report->report_type . '-report.csv"');
        $output = fopen('php://output', 'w');
        // Dynamic headers based on type
        if ($report->report_type === 'payments') {
            fputcsv($output, ['ID', 'Member ID', 'Amount', 'Date', 'Status']);
            foreach ($data as $row) {
                fputcsv($output, [$row->id, $row->member_id, $row->amount, $row->payment_date, $row->status]);
            }
        } // Add for other types
        gymlite_log("Report exported: ID $report_id");
        exit;
    }

    public function handle_get_reports() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $reports = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_reports ORDER BY generated_at DESC");
        gymlite_log("Reports retrieved");
        wp_send_json_success(['reports' => $reports]);
    }

    public function handle_delete_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $report_id = intval($_POST['report_id']);
        global $wpdb;
        $result = $wpdb->delete($wpdb->prefix . 'gymlite_reports', ['id' => $report_id]);
        if ($result) {
            gymlite_log("Report deleted: ID $report_id");
            wp_send_json_success(['message' => __('Report deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete report.', 'gymlite')]);
        }
    }
}
?>