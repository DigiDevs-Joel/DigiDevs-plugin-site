<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Admin {
    public function __construct() {
        try {
            gymlite_log("GymLite_Admin constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post_gymlite_member', [$this, 'save_member_meta']);
            add_action('save_post_gymlite_class', [$this, 'save_class_meta']);
            add_action('save_post_gymlite_staff', [$this, 'save_staff_meta']);
            add_action('save_post_gymlite_waiver', [$this, 'save_waiver_meta']);
            add_action('wp_ajax_gymlite_export_report', [$this, 'export_report']);
            add_action('wp_ajax_gymlite_send_notification', [$this, 'send_notification']);
            add_action('wp_ajax_gymlite_update_progression', [$this, 'handle_update_progression']);
            gymlite_log("GymLite_Admin constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Admin: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('GymLite Dashboard', 'gymlite'),
            __('GymLite', 'gymlite'),
            'manage_options',
            'gymlite-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-analytics'
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Dashboard', 'gymlite'),
            __('Dashboard', 'gymlite'),
            'manage_options',
            'gymlite-dashboard',
            [$this, 'dashboard_page']
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Members', 'gymlite'),
            __('Members', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_member'
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Classes', 'gymlite'),
            __('Classes', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_class'
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Staff', 'gymlite'),
            __('Staff', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_staff'
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Waivers', 'gymlite'),
            __('Waivers', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_waiver'
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Reports', 'gymlite'),
            __('Reports', 'gymlite'),
            'manage_options',
            'gymlite-reports',
            [$this, 'reports_page']
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Marketing', 'gymlite'),
            __('Marketing', 'gymlite'),
            'manage_options',
            'gymlite-marketing',
            [$this, 'marketing_page']
        );
        add_submenu_page(
            'gymlite-dashboard',
            __('Billing', 'gymlite'),
            __('Billing', 'gymlite'),
            'manage_options',
            'gymlite-billing',
            [$this, 'billing_page']
        );
    }

    public function register_settings() {
        register_setting('gymlite_settings', 'gymlite_stripe_key');
        register_setting('gymlite_settings', 'gymlite_license_key');
        register_setting('gymlite_settings', 'gymlite_enable_premium_mode');
        register_setting('gymlite_settings', 'gymlite_zoom_api_key');
        register_setting('gymlite_settings', 'gymlite_zoom_api_secret');
    }

    public function add_meta_boxes() {
        add_meta_box(
            'gymlite_member_details',
            __('Member Details', 'gymlite'),
            [$this, 'member_meta_box'],
            'gymlite_member',
            'normal',
            'high'
        );
        add_meta_box(
            'gymlite_class_details',
            __('Class Details', 'gymlite'),
            [$this, 'class_meta_box'],
            'gymlite_class',
            'normal',
            'high'
        );
        add_meta_box(
            'gymlite_staff_details',
            __('Staff Details', 'gymlite'),
            [$this, 'staff_meta_box'],
            'gymlite_staff',
            'normal',
            'high'
        );
        add_meta_box(
            'gymlite_waiver_details',
            __('Waiver Details', 'gymlite'),
            [$this, 'waiver_meta_box'],
            'gymlite_waiver',
            'normal',
            'high'
        );
    }

    public function member_meta_box($post) {
        wp_nonce_field('gymlite_member_meta', 'gymlite_member_nonce');
        $email = get_post_meta($post->ID, '_gymlite_member_email', true);
        $phone = get_post_meta($post->ID, '_gymlite_member_phone', true);
        $membership_type = get_post_meta($post->ID, '_gymlite_membership_type', true);
        ?>
        <p>
            <label for="gymlite_member_email"><?php _e('Email', 'gymlite'); ?></label>
            <input type="email" id="gymlite_member_email" name="gymlite_member_email" value="<?php echo esc_attr($email); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_member_phone"><?php _e('Phone', 'gymlite'); ?></label>
            <input type="tel" id="gymlite_member_phone" name="gymlite_member_phone" value="<?php echo esc_attr($phone); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_membership_type"><?php _e('Membership Type', 'gymlite'); ?></label>
            <select id="gymlite_membership_type" name="gymlite_membership_type" class="widefat">
                <option value="trial" <?php selected($membership_type, 'trial'); ?>><?php _e('Trial', 'gymlite'); ?></option>
                <option value="basic" <?php selected($membership_type, 'basic'); ?>><?php _e('Basic', 'gymlite'); ?></option>
                <option value="premium" <?php selected($membership_type, 'premium'); ?>><?php _e('Premium', 'gymlite'); ?></option>
            </select>
        </p>
        <?php
    }

    public function class_meta_box($post) {
        wp_nonce_field('gymlite_class_meta', 'gymlite_class_nonce');
        $date = get_post_meta($post->ID, '_gymlite_class_date', true);
        $duration = get_post_meta($post->ID, '_gymlite_class_duration', true);
        $instructor = get_post_meta($post->ID, '_gymlite_class_instructor', true);
        ?>
        <p>
            <label for="gymlite_class_date"><?php _e('Date', 'gymlite'); ?></label>
            <input type="date" id="gymlite_class_date" name="gymlite_class_date" value="<?php echo esc_attr($date); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_class_duration"><?php _e('Duration (minutes)', 'gymlite'); ?></label>
            <input type="number" id="gymlite_class_duration" name="gymlite_class_duration" value="<?php echo esc_attr($duration); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_class_instructor"><?php _e('Instructor', 'gymlite'); ?></label>
            <input type="text" id="gymlite_class_instructor" name="gymlite_class_instructor" value="<?php echo esc_attr($instructor); ?>" class="widefat">
        </p>
        <?php
    }

    public function staff_meta_box($post) {
        wp_nonce_field('gymlite_staff_meta', 'gymlite_staff_nonce');
        $role = get_post_meta($post->ID, '_gymlite_staff_role', true);
        $email = get_post_meta($post->ID, '_gymlite_staff_email', true);
        ?>
        <p>
            <label for="gymlite_staff_role"><?php _e('Role', 'gymlite'); ?></label>
            <input type="text" id="gymlite_staff_role" name="gymlite_staff_role" value="<?php echo esc_attr($role); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_staff_email"><?php _e('Email', 'gymlite'); ?></label>
            <input type="email" id="gymlite_staff_email" name="gymlite_staff_email" value="<?php echo esc_attr($email); ?>" class="widefat">
        </p>
        <?php
    }

    public function waiver_meta_box($post) {
        wp_nonce_field('gymlite_waiver_meta', 'gymlite_waiver_nonce');
        // Additional fields if needed
    }

    public function save_member_meta($post_id) {
        if (!isset($_POST['gymlite_member_nonce']) || !wp_verify_nonce($_POST['gymlite_member_nonce'], 'gymlite_member_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_gymlite_member_email', sanitize_email($_POST['gymlite_member_email']));
        update_post_meta($post_id, '_gymlite_member_phone', sanitize_text_field($_POST['gymlite_member_phone']));
        update_post_meta($post_id, '_gymlite_membership_type', sanitize_text_field($_POST['gymlite_membership_type']));
    }

    public function save_class_meta($post_id) {
        if (!isset($_POST['gymlite_class_nonce']) || !wp_verify_nonce($_POST['gymlite_class_nonce'], 'gymlite_class_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_gymlite_class_date', sanitize_text_field($_POST['gymlite_class_date']));
        update_post_meta($post_id, '_gymlite_class_duration', intval($_POST['gymlite_class_duration']));
        update_post_meta($post_id, '_gymlite_class_instructor', sanitize_text_field($_POST['gymlite_class_instructor']));
    }

    public function save_staff_meta($post_id) {
        if (!isset($_POST['gymlite_staff_nonce']) || !wp_verify_nonce($_POST['gymlite_staff_nonce'], 'gymlite_staff_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_gymlite_staff_role', sanitize_text_field($_POST['gymlite_staff_role']));
        update_post_meta($post_id, '_gymlite_staff_email', sanitize_email($_POST['gymlite_staff_email']));
    }

    public function save_waiver_meta($post_id) {
        if (!isset($_POST['gymlite_waiver_nonce']) || !wp_verify_nonce($_POST['gymlite_waiver_nonce'], 'gymlite_waiver_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Save additional waiver meta if added
    }

    public function dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('GymLite Dashboard', 'gymlite'); ?></h1>
            <div class="uk-grid">
                <div class="uk-width-1-3">
                    <div class="uk-card uk-card-default">
                        <div class="uk-card-header">
                            <h3><?php _e('Total Members', 'gymlite'); ?></h3>
                        </div>
                        <div class="uk-card-body">
                            <?php
                            $member_count = wp_count_posts('gymlite_member')->publish;
                            echo '<p class="uk-text-large">' . esc_html($member_count) . '</p>';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="uk-width-1-3">
                    <div class="uk-card uk-card-default">
                        <div class="uk-card-header">
                            <h3><?php _e('Upcoming Classes', 'gymlite'); ?></h3>
                        </div>
                        <div class="uk-card-body">
                            <?php
                            $upcoming = new WP_Query([
                                'post_type' => 'gymlite_class',
                                'meta_query' => [['key' => '_gymlite_class_date', 'value' => date('Y-m-d'), 'compare' => '>=']],
                                'posts_per_page' => 5,
                            ]);
                            if ($upcoming->have_posts()) {
                                echo '<ul>';
                                while ($upcoming->have_posts()) {
                                    $upcoming->the_post();
                                    echo '<li>' . get_the_title() . ' - ' . get_post_meta(get_the_ID(), '_gymlite_class_date', true) . '</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo '<p>' . __('No upcoming classes.', 'gymlite') . '</p>';
                            }
                            wp_reset_postdata();
                            ?>
                        </div>
                    </div>
                </div>
                <div class="uk-width-1-3">
                    <div class="uk-card uk-card-default">
                        <div class="uk-card-header">
                            <h3><?php _e('Recent Leads', 'gymlite'); ?></h3>
                        </div>
                        <div class="uk-card-body">
                            <?php
                            global $wpdb;
                            $table_name = $wpdb->prefix . 'gymlite_leads';
                            $leads = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
                            if ($leads) {
                                echo '<ul>';
                                foreach ($leads as $lead) {
                                    echo '<li>' . esc_html($lead->name) . ' (' . esc_html($lead->email) . ')</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo '<p>' . __('No recent leads.', 'gymlite') . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function reports_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('GymLite Reports', 'gymlite'); ?></h1>
            <button id="gymlite-export-report" class="uk-button uk-button-primary"><?php _e('Export Report', 'gymlite'); ?></button>
            <!-- Additional report UI -->
        </div>
        <?php
    }

    public function marketing_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('GymLite Marketing', 'gymlite'); ?></h1>
            <!-- Marketing campaigns UI -->
        </div>
        <?php
    }

    public function billing_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('GymLite Billing', 'gymlite'); ?></h1>
            <!-- Billing overview UI -->
        </div>
        <?php
    }

    public function export_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'gymlite')]);
        }
        // Generate CSV or PDF report
        $data = []; // Populate with report data
        // Example: header('Content-Type: text/csv');
        // Output data
        wp_send_json_success(['message' => __('Report exported', 'gymlite')]);
    }

    public function send_notification() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $message = sanitize_text_field($_POST['message']);
        // Send email or notification
        wp_mail(get_post_meta($member_id, '_gymlite_member_email', true), __('GymLite Notification', 'gymlite'), $message);
        gymlite_log("Notification sent to member $member_id");
        wp_send_json_success(['message' => __('Notification sent', 'gymlite')]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')]
        );
        gymlite_log("Progression updated for member $member_id to $level");
        wp_send_json_success(['message' => __('Progression updated', 'gymlite')]);
    }

    public static function billing() {
        if (!class_exists('GymLite_Premium') || !GymLite_Premium::is_premium_active()) {
            return;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $overdues = $wpdb->get_results("SELECT * FROM $table_name WHERE next_billing_date <= CURDATE() AND status = 'active'");
        foreach ($overdues as $overdue) {
            // Process billing with Stripe
            // Update status or notify
        }
        gymlite_log("Billing processed");
    }

    public static function send_daily_notifications() {
        // Send emails for upcoming classes, overdues, etc.
        gymlite_log("Daily notifications sent");
    }
}
?>