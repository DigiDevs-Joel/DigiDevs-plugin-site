<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Admin {
    public function __construct() {
        try {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post_gymlite_member', [$this, 'save_member_meta']);
            add_action('save_post_gymlite_class', [$this, 'save_class_meta']);
            add_action('save_post_gymlite_staff', [$this, 'save_staff_meta']);
            add_action('wp_ajax_gymlite_export_report', [$this, 'export_report']);
            add_action('wp_ajax_gymlite_manual_checkin', [$this, 'handle_manual_checkin']);
            add_action('wp_ajax_gymlite_bulk_checkin', [$this, 'handle_bulk_checkin']);
            add_action('wp_ajax_gymlite_export_growth_report', [$this, 'export_growth_report']);
            add_action('wp_ajax_gymlite_export_pos_report', [$this, 'export_pos_report']);
            add_action('wp_ajax_gymlite_process_overdue', [$this, 'handle_overdue']);
            add_action('wp_ajax_gymlite_create_sale', [$this, 'handle_sale']);
            add_action('wp_ajax_gymlite_send_campaign', [$this, 'handle_send_campaign']);
            add_action('wp_ajax_gymlite_create_campaign', [$this, 'handle_create_campaign']);
            add_action('wp_ajax_gymlite_update_campaign', [$this, 'handle_update_campaign']);
            add_action('wp_ajax_gymlite_sign_waiver', [$this, 'handle_sign_waiver']);
            add_action('wp_ajax_gymlite_update_waiver', [$this, 'handle_update_waiver']);
            add_action('wp_ajax_gymlite_log_access', [$this, 'handle_log_access']);
            add_action('wp_ajax_gymlite_update_access', [$this, 'handle_update_access']);
            add_action('wp_ajax_gymlite_promote_member', [$this, 'handle_promote']);
            add_action('wp_ajax_gymlite_update_progression', [$this, 'handle_update_progression']);
            add_action('wp_ajax_gymlite_export_comms_log', [$this, 'export_comms_log']);
            gymlite_log("GymLite_Admin initialized at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Admin: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public static function is_premium_active() {
        $premium_mode = get_option('gymlite_enable_premium_mode', 'no') === 'yes';
        if ($premium_mode) {
            return true;
        }
        $license_key = get_option('gymlite_license_key', '');
        return !empty($license_key) && $license_key === 'valid_license_key';
    }

    public function add_admin_menu() {
        try {
            add_menu_page(
                __('GymLite Dashboard', 'gymlite'),
                __('GymLite', 'gymlite'),
                'manage_options',
                'gymlite',
                [$this, 'dashboard_page'],
                'dashicons-fitness',
                30
            );
            add_submenu_page(
                'gymlite',
                __('Upgrade to Pro', 'gymlite'),
                __('Upgrade to Pro', 'gymlite'),
                'manage_options',
                'gymlite-upgrade',
                [$this, 'upgrade_page']
            );
            add_submenu_page(
                'gymlite',
                __('Admin Check-ins', 'gymlite'),
                __('Admin Check-ins', 'gymlite'),
                'manage_options',
                'gymlite-admin-checkin',
                [$this, 'admin_checkin_page']
            );
            add_submenu_page(
                'gymlite',
                __('Attendance Report', 'gymlite'),
                __('Attendance Report', 'gymlite'),
                'manage_options',
                'gymlite-attendance-report',
                [$this, 'attendance_report_page']
            );
            if (self::is_premium_active()) {
                add_submenu_page(
                    'gymlite',
                    __('Leads Report', 'gymlite'),
                    __('Leads Report', 'gymlite'),
                    'manage_options',
                    'gymlite-leads-report',
                    [$this, 'leads_report_page']
                );
                add_submenu_page(
                    'gymlite',
                    __('Churn Report', 'gymlite'),
                    __('Churn Report', 'gymlite'),
                    'manage_options',
                    'gymlite-churn-report',
                    [$this, 'churn_report_page']
                );
                add_submenu_page(
                    'gymlite',
                    __('Revenue Report', 'gymlite'),
                    __('Revenue Report', 'gymlite'),
                    'manage_options',
                    'gymlite-revenue-report',
                    [$this, 'reporting']
                );
                add_submenu_page(
                    'gymlite',
                    __('Billing Overview', 'gymlite'),
                    __('Billing', 'gymlite'),
                    'manage_options',
                    'gymlite-billing-overview',
                    [$this, 'billing_overview_page']
                );
                add_submenu_page(
                    'gymlite',
                    __('Billing Growth', 'gymlite'),
                    __('Growth', 'gymlite'),
                    'manage_options',
                    'gymlite-billing-growth',
                    [$this, 'billing_growth_page']
                );
                add_submenu_page(
                    'gymlite',
                    __('Point of Sale', 'gymlite'),
                    __('POS', 'gymlite'),
                    'manage_options',
                    'gymlite-pos',
                    [$this, 'pos_page']
                );
                add_submenu_page(
                    'gymlite',
                    __('Marketing Campaigns', 'gymlite'),
                    __('Marketing', 'gymlite'),
                    'manage_options',
                    'gymlite-marketing',
                    [$this, 'marketing_page']
                );
            }
            gymlite_log("Admin menu added at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error in add_admin_menu: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $active_members = wp_count_posts('gymlite_member')->publish;
        $upcoming_classes = wp_count_posts('gymlite_class')->publish;
        $recent_attendance = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC LIMIT 5");
        ?>
        <div class="wrap">
            <h1><?php _e('GymLite Dashboard', 'gymlite'); ?></h1>
            <div class="uk-grid-small" uk-grid>
                <div class="uk-width-1-3@m">
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3><?php _e('Quick Stats', 'gymlite'); ?></h3>
                        <p><?php echo sprintf(__('Active Members: %d', 'gymlite'), $active_members); ?></p>
                        <p><?php echo sprintf(__('Upcoming Classes: %d', 'gymlite'), $upcoming_classes); ?></p>
                    </div>
                </div>
                <div class="uk-width-2-3@m">
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3><?php _e('Recent Activity', 'gymlite'); ?></h3>
                        <?php if ($recent_attendance) : ?>
                            <ul class="uk-list uk-list-divider">
                                <?php foreach ($recent_attendance as $entry) : ?>
                                    <li><?php echo esc_html($entry->member_name . ' checked into ' . $entry->class_name . ' on ' . date('Y-m-d H:i', strtotime($entry->attendance_date))); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p><?php _e('No recent activity.', 'gymlite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function upgrade_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Upgrade to GymLite Pro', 'gymlite'); ?></h1>
            <p><?php _e('Unlock premium features like advanced billing, marketing tools, and integrations. Visit <a href="https://example.com/gymlite-pro" target="_blank">our site</a> to upgrade.', 'gymlite'); ?></p>
        </div>
        <?php
    }

    public function admin_checkin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
        $classes = get_posts(['post_type' => 'gymlite_class', 'posts_per_page' => -1, 'post_status' => 'publish']);
        ?>
        <div class="wrap">
            <h1><?php _e('Admin Check-ins', 'gymlite'); ?></h1>
            <form id="gymlite-manual-checkin" class="uk-form-stacked">
                <div class="uk-margin">
                    <label class="uk-form-label" for="member_id"><?php _e('Member', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="member_id" id="member_id" required>
                            <?php foreach ($members as $member) : ?>
                                <option value="<?php echo esc_attr($member->post_author); ?>"><?php echo esc_html($member->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="class_id"><?php _e('Class', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="class_id" id="class_id" required>
                            <?php foreach ($classes as $class) : ?>
                                <option value="<?php echo esc_attr($class->ID); ?>"><?php echo esc_html($class->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="uk-margin">
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Check In', 'gymlite'); ?></button>
                </div>
                <?php wp_nonce_field('gymlite_manual_checkin', 'nonce'); ?>
            </form>
            <div id="bulk-checkin-section" class="uk-margin-top">
                <h3><?php _e('Bulk Check-in', 'gymlite'); ?></h3>
                <textarea class="uk-textarea" id="bulk-members" placeholder="<?php _e('Enter member IDs (one per line)', 'gymlite'); ?>"></textarea>
                <button class="uk-button uk-button-primary" id="bulk-checkin-btn"><?php _e('Process Bulk Check-in', 'gymlite'); ?></button>
                <?php wp_nonce_field('gymlite_bulk_checkin', 'bulk_nonce'); ?>
            </div>
        </div>
        <?php
    }

    public function attendance_report_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC");
        ?>
        <div class="wrap">
            <h1><?php _e('Attendance Report', 'gymlite'); ?></h1>
            <form method="post" action="">
                <input type="submit" name="export_attendance" class="uk-button uk-button-primary" value="<?php _e('Export to CSV', 'gymlite'); ?>">
            </form>
            <?php if ($results) : ?>
                <table class="uk-table uk-table-striped">
                    <thead>
                        <tr>
                            <th><?php _e('Member', 'gymlite'); ?></th>
                            <th><?php _e('Class', 'gymlite'); ?></th>
                            <th><?php _e('Date', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->member_name); ?></td>
                                <td><?php echo esc_html($row->class_name); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($row->attendance_date))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No attendance records found.', 'gymlite'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function leads_report_page() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_leads ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1><?php _e('Leads Report', 'gymlite'); ?></h1>
            <form method="post" action="">
                <input type="submit" name="export_leads" class="uk-button uk-button-primary" value="<?php _e('Export to CSV', 'gymlite'); ?>">
            </form>
            <?php if ($results) : ?>
                <table class="uk-table uk-table-striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'gymlite'); ?></th>
                            <th><?php _e('Email', 'gymlite'); ?></th>
                            <th><?php _e('Phone', 'gymlite'); ?></th>
                            <th><?php _e('Date', 'gymlite'); ?></th>
                            <th><?php _e('Status', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->name); ?></td>
                                <td><?php echo esc_html($row->email); ?></td>
                                <td><?php echo esc_html($row->phone); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($row->created_at))); ?></td>
                                <td><?php echo esc_html($row->status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No leads found.', 'gymlite'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function churn_report_page() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.status 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            WHERE r.status = 'cancelled' AND r.next_billing_date < CURDATE() - INTERVAL 30 DAY");
        ?>
        <div class="wrap">
            <h1><?php _e('Churn Report', 'gymlite'); ?></h1>
            <form method="post" action="">
                <input type="submit" name="export_churn" class="uk-button uk-button-primary" value="<?php _e('Export to CSV', 'gymlite'); ?>">
            </form>
            <?php if ($results) : ?>
                <table class="uk-table uk-table-striped">
                    <thead>
                        <tr>
                            <th><?php _e('Member', 'gymlite'); ?></th>
                            <th><?php _e('Plan', 'gymlite'); ?></th>
                            <th><?php _e('Status', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->member_name); ?></td>
                                <td><?php echo esc_html($row->plan_type); ?></td>
                                <td><?php echo esc_html($row->status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No churn data found.', 'gymlite'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function reporting() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}gymlite_payments");
        $monthly_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}gymlite_payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
        ?>
        <div class="wrap">
            <h1><?php _e('Revenue Report', 'gymlite'); ?></h1>
            <p><?php echo sprintf(__('Total Revenue: %s', 'gymlite'), esc_html($total_revenue ? '$' . number_format($total_revenue, 2) : '$0.00')); ?></p>
            <p><?php echo sprintf(__('Monthly Revenue: %s', 'gymlite'), esc_html($monthly_revenue ? '$' . number_format($monthly_revenue, 2) : '$0.00')); ?></p>
            <form method="post" action="">
                <input type="submit" name="export_revenue" class="uk-button uk-button-primary" value="<?php _e('Export to CSV', 'gymlite'); ?>">
            </form>
        </div>
        <?php
    }

    public function billing_overview_page() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.amount, r.next_billing_date, r.status 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            ORDER BY r.next_billing_date ASC");
        ?>
        <div class="wrap">
            <h1><?php _e('Billing Overview', 'gymlite'); ?></h1>
            <?php if ($results) : ?>
                <table class="uk-table uk-table-striped">
                    <thead>
                        <tr>
                            <th><?php _e('Member', 'gymlite'); ?></th>
                            <th><?php _e('Plan', 'gymlite'); ?></th>
                            <th><?php _e('Amount', 'gymlite'); ?></th>
                            <th><?php _e('Next Billing', 'gymlite'); ?></th>
                            <th><?php _e('Status', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->member_name); ?></td>
                                <td><?php echo esc_html($row->plan_type); ?></td>
                                <td><?php echo esc_html('$' . number_format($row->amount, 2)); ?></td>
                                <td><?php echo esc_html($row->next_billing_date); ?></td>
                                <td><?php echo esc_html($row->status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No billing records found.', 'gymlite'); ?></p>
            <?php endif; ?>
            <form method="post" action="">
                <input type="submit" name="process_overdue" class="uk-button uk-button-primary" value="<?php _e('Process Overdue Payments', 'gymlite'); ?>">
            </form>
        </div>
        <?php
    }

    public function billing_growth_page() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $growth = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gymlite_recurring WHERE status = 'active' AND next_billing_date > CURDATE() - INTERVAL 30 DAY");
        ?>
        <div class="wrap">
            <h1><?php _e('Billing Growth', 'gymlite'); ?></h1>
            <p><?php echo sprintf(__('New active plans in the last 30 days: %d', 'gymlite'), $growth); ?></p>
            <form method="post" action="">
                <input type="submit" name="export_growth" class="uk-button uk-button-primary" value="<?php _e('Export to CSV', 'gymlite'); ?>">
            </form>
        </div>
        <?php
    }

    public function pos_page() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $products = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_products");
        ?>
        <div class="wrap">
            <h1><?php _e('Point of Sale', 'gymlite'); ?></h1>
            <form id="gymlite-pos-form" class="uk-form-stacked">
                <div class="uk-margin">
                    <label class="uk-form-label" for="product_id"><?php _e('Product', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="product_id" id="product_id" required>
                            <?php foreach ($products as $product) : ?>
                                <option value="<?php echo esc_attr($product->id); ?>"><?php echo esc_html($product->name . ' - $' . number_format($product->price, 2)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="member_id"><?php _e('Member (Optional)', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="member_id" id="member_id">
                            <option value="0"><?php _e('Non-Member', 'gymlite'); ?></option>
                            <?php $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
                            foreach ($members as $member) : ?>
                                <option value="<?php echo esc_attr($member->post_author); ?>"><?php echo esc_html($member->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="quantity"><?php _e('Quantity', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <input class="uk-input" type="number" name="quantity" id="quantity" value="1" min="1" required>
                    </div>
                </div>
                <div class="uk-margin">
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Process Sale', 'gymlite'); ?></button>
                </div>
                <?php wp_nonce_field('gymlite_create_sale', 'nonce'); ?>
            </form>
        </div>
        <?php
    }

    public function marketing_page() {
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gymlite'));
        }
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_campaigns ORDER BY sent_date DESC");
        ?>
        <div class="wrap">
            <h1><?php _e('Marketing Campaigns', 'gymlite'); ?></h1>
            <form id="gymlite-create-campaign" class="uk-form-stacked">
                <div class="uk-margin">
                    <label class="uk-form-label" for="campaign_name"><?php _e('Campaign Name', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <input class="uk-input" type="text" name="campaign_name" id="campaign_name" required>
                    </div>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="campaign_type"><?php _e('Type', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="campaign_type" id="campaign_type" required>
                            <option value="email"><?php _e('Email', 'gymlite'); ?></option>
                            <option value="sms"><?php _e('SMS', 'gymlite'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="campaign_content"><?php _e('Content', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <textarea class="uk-textarea" name="campaign_content" id="campaign_content" required></textarea>
                    </div>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="campaign_target"><?php _e('Target', 'gymlite'); ?></label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="campaign_target" id="campaign_target" required>
                            <option value="all"><?php _e('All Members', 'gymlite'); ?></option>
                            <option value="active"><?php _e('Active Members', 'gymlite'); ?></option>
                            <option value="trial"><?php _e('Trial Members', 'gymlite'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="uk-margin">
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Create Campaign', 'gymlite'); ?></button>
                </div>
                <?php wp_nonce_field('gymlite_create_campaign', 'nonce'); ?>
            </form>
            <h3><?php _e('Existing Campaigns', 'gymlite'); ?></h3>
            <?php if ($campaigns) : ?>
                <table class="uk-table uk-table-striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'gymlite'); ?></th>
                            <th><?php _e('Type', 'gymlite'); ?></th>
                            <th><?php _e('Sent Date', 'gymlite'); ?></th>
                            <th><?php _e('Status', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign) : ?>
                            <tr>
                                <td><?php echo esc_html($campaign->name); ?></td>
                                <td><?php echo esc_html($campaign->type); ?></td>
                                <td><?php echo esc_html($campaign->sent_date ? $campaign->sent_date : __('Not sent', 'gymlite')); ?></td>
                                <td><?php echo esc_html($campaign->status); ?></td>
                                <td>
                                    <?php if ($campaign->status === 'draft') : ?>
                                        <button class="uk-button uk-button-primary uk-button-small send-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php _e('Send', 'gymlite'); ?></button>
                                        <button class="uk-button uk-button-secondary uk-button-small update-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php _e('Edit', 'gymlite'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No campaigns found.', 'gymlite'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_meta_boxes() {
        add_meta_box('gymlite_member_meta', __('Member Details', 'gymlite'), [$this, 'member_meta_box'], 'gymlite_member', 'normal', 'high');
        add_meta_box('gymlite_class_meta', __('Class Details', 'gymlite'), [$this, 'class_meta_box'], 'gymlite_class', 'normal', 'high');
        add_meta_box('gymlite_staff_meta', __('Staff Details', 'gymlite'), [$this, 'staff_meta_box'], 'gymlite_staff', 'normal', 'high');
    }

    public function member_meta_box($post) {
        $email = get_post_meta($post->ID, '_gymlite_member_email', true);
        $phone = get_post_meta($post->ID, '_gymlite_member_phone', true);
        $membership_type = get_post_meta($post->ID, '_gymlite_membership_type', true);
        wp_nonce_field('gymlite_member_meta', 'gymlite_member_meta_nonce');
        ?>
        <p>
            <label for="gymlite_member_email"><?php _e('Email:', 'gymlite'); ?></label>
            <input type="email" name="gymlite_member_email" id="gymlite_member_email" value="<?php echo esc_attr($email); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_member_phone"><?php _e('Phone:', 'gymlite'); ?></label>
            <input type="tel" name="gymlite_member_phone" id="gymlite_member_phone" value="<?php echo esc_attr($phone); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_membership_type"><?php _e('Membership Type:', 'gymlite'); ?></label>
            <select name="gymlite_membership_type" id="gymlite_membership_type" class="widefat">
                <option value="trial" <?php selected($membership_type, 'trial'); ?>><?php _e('Trial', 'gymlite'); ?></option>
                <option value="basic" <?php selected($membership_type, 'basic'); ?>><?php _e('Basic', 'gymlite'); ?></option>
                <option value="premium" <?php selected($membership_type, 'premium'); ?>><?php _e('Premium', 'gymlite'); ?></option>
            </select>
        </p>
        <?php
    }

    public function class_meta_box($post) {
        $date = get_post_meta($post->ID, '_gymlite_class_date', true);
        $duration = get_post_meta($post->ID, '_gymlite_class_duration', true);
        $instructor = get_post_meta($post->ID, '_gymlite_class_instructor', true);
        wp_nonce_field('gymlite_class_meta', 'gymlite_class_meta_nonce');
        ?>
        <p>
            <label for="gymlite_class_date"><?php _e('Date & Time:', 'gymlite'); ?></label>
            <input type="datetime-local" name="gymlite_class_date" id="gymlite_class_date" value="<?php echo esc_attr($date); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_class_duration"><?php _e('Duration (minutes):', 'gymlite'); ?></label>
            <input type="number" name="gymlite_class_duration" id="gymlite_class_duration" value="<?php echo esc_attr($duration ?: 60); ?>" class="widefat" min="1">
        </p>
        <p>
            <label for="gymlite_class_instructor"><?php _e('Instructor:', 'gymlite'); ?></label>
            <input type="text" name="gymlite_class_instructor" id="gymlite_class_instructor" value="<?php echo esc_attr($instructor); ?>" class="widefat">
        </p>
        <?php
    }

    public function staff_meta_box($post) {
        $phone = get_post_meta($post->ID, '_gymlite_staff_phone', true);
        $email = get_post_meta($post->ID, '_gymlite_staff_email', true);
        wp_nonce_field('gymlite_staff_meta', 'gymlite_staff_meta_nonce');
        ?>
        <p>
            <label for="gymlite_staff_phone"><?php _e('Phone:', 'gymlite'); ?></label>
            <input type="tel" name="gymlite_staff_phone" id="gymlite_staff_phone" value="<?php echo esc_attr($phone); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_staff_email"><?php _e('Email:', 'gymlite'); ?></label>
            <input type="email" name="gymlite_staff_email" id="gymlite_staff_email" value="<?php echo esc_attr($email); ?>" class="widefat">
        </p>
        <?php
    }

    public function save_member_meta($post_id) {
        if (!isset($_POST['gymlite_member_meta_nonce']) || !wp_verify_nonce($_POST['gymlite_member_meta_nonce'], 'gymlite_member_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['gymlite_member_email'])) {
            update_post_meta($post_id, '_gymlite_member_email', sanitize_email($_POST['gymlite_member_email']));
        }
        if (isset($_POST['gymlite_member_phone'])) {
            update_post_meta($post_id, '_gymlite_member_phone', sanitize_text_field($_POST['gymlite_member_phone']));
        }
        if (isset($_POST['gymlite_membership_type'])) {
            update_post_meta($post_id, '_gymlite_membership_type', sanitize_text_field($_POST['gymlite_membership_type']));
        }
    }

    public function save_class_meta($post_id) {
        if (!isset($_POST['gymlite_class_meta_nonce']) || !wp_verify_nonce($_POST['gymlite_class_meta_nonce'], 'gymlite_class_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['gymlite_class_date'])) {
            update_post_meta($post_id, '_gymlite_class_date', sanitize_text_field($_POST['gymlite_class_date']));
        }
        if (isset($_POST['gymlite_class_duration'])) {
            update_post_meta($post_id, '_gymlite_class_duration', intval($_POST['gymlite_class_duration']));
        }
        if (isset($_POST['gymlite_class_instructor'])) {
            update_post_meta($post_id, '_gymlite_class_instructor', sanitize_text_field($_POST['gymlite_class_instructor']));
        }
    }

    public function save_staff_meta($post_id) {
        if (!isset($_POST['gymlite_staff_meta_nonce']) || !wp_verify_nonce($_POST['gymlite_staff_meta_nonce'], 'gymlite_staff_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['gymlite_staff_phone'])) {
            update_post_meta($post_id, '_gymlite_staff_phone', sanitize_text_field($_POST['gymlite_staff_phone']));
        }
        if (isset($_POST['gymlite_staff_email'])) {
            update_post_meta($post_id, '_gymlite_staff_email', sanitize_email($_POST['gymlite_staff_email']));
        }
    }

    public function export_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC");
        if ($results) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="attendance_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Class', 'Date']);
            foreach ($results as $row) {
                fputcsv($output, [$row->member_name, $row->class_name, $row->attendance_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No data to export.', 'gymlite')]);
    }

    public function handle_manual_checkin() {
        check_ajax_referer('gymlite_manual_checkin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result === false) {
            wp_send_json_error(['message' => __('Check-in failed: ' . $wpdb->last_error, 'gymlite')]);
        }
        gymlite_log("Manual check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
    }

    public function handle_bulk_checkin() {
        check_ajax_referer('gymlite_bulk_checkin', 'bulk_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_ids = array_map('intval', explode("\n", trim($_POST['member_ids'])));
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $success_count = 0;
        foreach ($member_ids as $member_id) {
            if ($member_id) {
                $result = $wpdb->insert(
                    $table_name,
                    ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
                    ['%d', '%d', '%s']
                );
                if ($result !== false) {
                    $success_count++;
                }
            }
        }
        gymlite_log("Bulk check-in processed $success_count members into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => sprintf(__('Successfully checked in %d members.', 'gymlite'), $success_count)]);
    }

    public function export_growth_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $growth = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.next_billing_date 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            WHERE r.next_billing_date > CURDATE() - INTERVAL 30 DAY AND r.status = 'active'");
        if ($growth) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="growth_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Plan Type', 'Next Billing Date']);
            foreach ($growth as $row) {
                fputcsv($output, [$row->member_name, $row->plan_type, $row->next_billing_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No growth data to export.', 'gymlite')]);
    }

    public function export_pos_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $sales = $wpdb->get_results("SELECT s.sale_date, p.name AS product_name, s.quantity, s.amount, m.post_title AS member_name 
            FROM {$wpdb->prefix}gymlite_sales s 
            LEFT JOIN {$wpdb->posts} m ON s.member_id = m.post_author 
            JOIN {$wpdb->prefix}gymlite_products p ON s.product_id = p.id 
            ORDER BY s.sale_date DESC");
        if ($sales) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="pos_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Product', 'Quantity', 'Amount', 'Member']);
            foreach ($sales as $row) {
                fputcsv($output, [$row->sale_date, $row->product_name, $row->quantity, $row->amount, $row->member_name ?: 'Non-Member']);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No POS data to export.', 'gymlite')]);
    }

    public function handle_overdue() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        if ($updated !== false) {
            gymlite_log("Processed $updated overdue payments at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => sprintf(__('Processed %d overdue payments.', 'gymlite'), $updated)]);
        }
        wp_send_json_error(['message' => __('No overdue payments processed.', 'gymlite')]);
    }

    public function handle_sale() {
        check_ajax_referer('gymlite_create_sale', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $product_id = intval($_POST['product_id']);
        $member_id = intval($_POST['member_id']);
        $quantity = intval($_POST['quantity']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_sales';
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM {$wpdb->prefix}gymlite_products WHERE id = %d", $product_id));
        if ($product) {
            $amount = $product->price * $quantity;
            $result = $wpdb->insert(
                $table_name,
                ['member_id' => $member_id, 'product_id' => $product_id, 'quantity' => $quantity, 'amount' => $amount, 'sale_date' => current_time('mysql')],
                ['%d', '%d', '%d', '%f', '%s']
            );
            if ($result !== false) {
                gymlite_log("Sale processed for product ID $product_id, member ID $member_id at " . current_time('Y-m-d H:i:s'));
                wp_send_json_success(['message' => __('Sale processed successfully.', 'gymlite')]);
            }
        }
        wp_send_json_error(['message' => __('Sale processing failed.', 'gymlite')]);
    }

    public function handle_send_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_campaigns WHERE id = %d", $campaign_id));
        if ($campaign && $campaign->status === 'draft') {
            $wpdb->update(
                "{$wpdb->prefix}gymlite_campaigns",
                ['status' => 'sent', 'sent_date' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%s', '%s'],
                ['%d']
            );
            // Placeholder for email/SMS sending logic
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email && $campaign->type === 'email') {
                    wp_mail($email, $campaign->name, $campaign->content);
                }
                $wpdb->insert(
                    "{$wpdb->prefix}gymlite_comms_logs",
                    ['type' => $campaign->type, 'member_id' => $member->post_author, 'campaign_id' => $campaign_id, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                    ['%s', '%d', '%d', '%s', '%s']
                );
            }
            gymlite_log("Campaign ID $campaign_id sent at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign sent successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign sending failed.', 'gymlite')]);
    }

    public function handle_create_campaign() {
        check_ajax_referer('gymlite_create_campaign', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target, 'status' => 'draft'],
            ['%s', '%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Campaign created with ID " . $wpdb->insert_id . " at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign created successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign creation failed.', 'gymlite')]);
    }

    public function handle_update_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target],
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        if ($result !== false) {
            gymlite_log("Campaign ID $campaign_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign update failed.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = intval($_POST['member_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'waiver_id' => $waiver_id, 'signed_date' => current_time('mysql'), 'signature' => $signature],
            ['%d', '%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_update_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $content = sanitize_textarea_field($_POST['content']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->posts}",
            ['post_content' => $content],
            ['ID' => $waiver_id, 'post_type' => 'gymlite_waiver'],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver update failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_update_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_access_logs",
            ['status' => $status],
            ['member_id' => $member_id, 'access_time' => current_time('mysql')],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access status updated for member ID $member_id to $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access update failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->update(
            $table_name,
            ['level' => $level, 'promoted_date' => current_time('mysql')],
            ['member_id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result !== false || $result === 0) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Progression updated for member ID $member_id to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Progression updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Progression update failed.', 'gymlite')]);
    }

    public function export_comms_log() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $logs = $wpdb->get_results("SELECT c.type, m.post_title AS member_name, c.sent_date, c.status 
            FROM {$wpdb->prefix}gymlite_comms_logs c 
            LEFT JOIN {$wpdb->posts} m ON c.member_id = m.post_author 
            ORDER BY c.sent_date DESC");
        if ($logs) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="comms_log.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Member', 'Sent Date', 'Status']);
            foreach ($logs as $row) {
                fputcsv($output, [$row->type, $row->member_name ?: 'Unknown', $row->sent_date, $row->status]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No comms data to export.', 'gymlite')]);
    }

    public function billing() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $recurrings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_recurring WHERE next_billing_date <= CURDATE() AND status = 'active'");
        if ($recurrings) {
            $stripe_key = get_option('gymlite_stripe_key');
            if ($stripe_key) {
                // Placeholder Stripe integration
                foreach ($recurrings as $recurring) {
                    // Simulate payment
                    $payment_amount = $recurring->amount;
                    $description = "Recurring payment for " . $recurring->plan_type;
                    // $charge = \Stripe\Charge::create([
                    //     'amount' => $payment_amount * 100,
                    //     'currency' => 'usd',
                    //     'description' => $description,
                    //     'customer' => $recurring->member_id, // Replace with actual Stripe customer ID
                    //     'source' => $source, // Replace with token
                    // ]);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_payments",
                        ['member_id' => $recurring->member_id, 'amount' => $payment_amount, 'payment_date' => current_time('mysql'), 'payment_type' => 'recurring'],
                        ['%d', '%f', '%s', '%s']
                    );
                    $new_date = date('Y-m-d', strtotime($recurring->next_billing_date . ' +1 month'));
                    $wpdb->update(
                        "{$wpdb->prefix}gymlite_recurring",
                        ['next_billing_date' => $new_date, 'last_payment_date' => current_time('mysql')],
                        ['id' => $recurring->id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }
                gymlite_log("Billing processed for " . count($recurrings) . " members at " . current_time('Y-m-d H:i:s'));
            }
        }
    }

    public function send_daily_notifications() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $tomorrow_classes = $wpdb->get_results("SELECT post_title, meta_value AS class_date 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE post_type = 'gymlite_class' AND post_status = 'publish' 
            AND meta_key = '_gymlite_class_date' 
            AND DATE(meta_value) = CURDATE() + INTERVAL 1 DAY");
        if ($tomorrow_classes) {
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email) {
                    $message = sprintf(__('Reminder: Tomorrow\'s class %s is scheduled at %s.', 'gymlite'), $tomorrow_classes[0]->post_title, date('H:i', strtotime($tomorrow_classes[0]->class_date)));
                    wp_mail($email, __('Class Reminder', 'gymlite'), $message);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_comms_logs",
                        ['type' => 'email', 'member_id' => $member->post_author, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                        ['%s', '%d', '%s', '%s']
                    );
                }
            }
            gymlite_log("Sent daily notifications for " . count($members) . " members at " . current_time('Y-m-d H:i:s'));
        }
    }


// Continuation of class-gymlite-admin.php (Part 2 of 5)

// [No additional class definition or constructor here - this is a continuation]

    public function export_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC");
        if ($results) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="attendance_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Class', 'Date']);
            foreach ($results as $row) {
                fputcsv($output, [$row->member_name, $row->class_name, $row->attendance_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No data to export.', 'gymlite')]);
    }

    public function handle_manual_checkin() {
        check_ajax_referer('gymlite_manual_checkin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result === false) {
            wp_send_json_error(['message' => __('Check-in failed: ' . $wpdb->last_error, 'gymlite')]);
        }
        gymlite_log("Manual check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
    }

    public function handle_bulk_checkin() {
        check_ajax_referer('gymlite_bulk_checkin', 'bulk_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_ids = array_map('intval', explode("\n", trim($_POST['member_ids'])));
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $success_count = 0;
        foreach ($member_ids as $member_id) {
            if ($member_id) {
                $result = $wpdb->insert(
                    $table_name,
                    ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
                    ['%d', '%d', '%s']
                );
                if ($result !== false) {
                    $success_count++;
                }
            }
        }
        gymlite_log("Bulk check-in processed $success_count members into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => sprintf(__('Successfully checked in %d members.', 'gymlite'), $success_count)]);
    }

    public function export_growth_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $growth = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.next_billing_date 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            WHERE r.next_billing_date > CURDATE() - INTERVAL 30 DAY AND r.status = 'active'");
        if ($growth) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="growth_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Plan Type', 'Next Billing Date']);
            foreach ($growth as $row) {
                fputcsv($output, [$row->member_name, $row->plan_type, $row->next_billing_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No growth data to export.', 'gymlite')]);
    }

    public function export_pos_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $sales = $wpdb->get_results("SELECT s.sale_date, p.name AS product_name, s.quantity, s.amount, m.post_title AS member_name 
            FROM {$wpdb->prefix}gymlite_sales s 
            LEFT JOIN {$wpdb->posts} m ON s.member_id = m.post_author 
            JOIN {$wpdb->prefix}gymlite_products p ON s.product_id = p.id 
            ORDER BY s.sale_date DESC");
        if ($sales) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="pos_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Product', 'Quantity', 'Amount', 'Member']);
            foreach ($sales as $row) {
                fputcsv($output, [$row->sale_date, $row->product_name, $row->quantity, $row->amount, $row->member_name ?: 'Non-Member']);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No POS data to export.', 'gymlite')]);
    }

    public function handle_overdue() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        if ($updated !== false) {
            gymlite_log("Processed $updated overdue payments at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => sprintf(__('Processed %d overdue payments.', 'gymlite'), $updated)]);
        }
        wp_send_json_error(['message' => __('No overdue payments processed.', 'gymlite')]);
    }

    public function handle_sale() {
        check_ajax_referer('gymlite_create_sale', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $product_id = intval($_POST['product_id']);
        $member_id = intval($_POST['member_id']);
        $quantity = intval($_POST['quantity']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_sales';
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM {$wpdb->prefix}gymlite_products WHERE id = %d", $product_id));
        if ($product) {
            $amount = $product->price * $quantity;
            $result = $wpdb->insert(
                $table_name,
                ['member_id' => $member_id, 'product_id' => $product_id, 'quantity' => $quantity, 'amount' => $amount, 'sale_date' => current_time('mysql')],
                ['%d', '%d', '%d', '%f', '%s']
            );
            if ($result !== false) {
                gymlite_log("Sale processed for product ID $product_id, member ID $member_id at " . current_time('Y-m-d H:i:s'));
                wp_send_json_success(['message' => __('Sale processed successfully.', 'gymlite')]);
            }
        }
        wp_send_json_error(['message' => __('Sale processing failed.', 'gymlite')]);
    }

    public function handle_send_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_campaigns WHERE id = %d", $campaign_id));
        if ($campaign && $campaign->status === 'draft') {
            $wpdb->update(
                "{$wpdb->prefix}gymlite_campaigns",
                ['status' => 'sent', 'sent_date' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%s', '%s'],
                ['%d']
            );
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email && $campaign->type === 'email' && (in_array($campaign->target, ['all', 'active']) || ($campaign->target === 'trial' && get_post_meta($member->post_author, '_gymlite_membership_type', true) === 'trial'))) {
                    wp_mail($email, $campaign->name, $campaign->content);
                }
                $wpdb->insert(
                    "{$wpdb->prefix}gymlite_comms_logs",
                    ['type' => $campaign->type, 'member_id' => $member->post_author, 'campaign_id' => $campaign_id, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                    ['%s', '%d', '%d', '%s', '%s']
                );
            }
            gymlite_log("Campaign ID $campaign_id sent at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign sent successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign sending failed.', 'gymlite')]);
    }

    public function handle_create_campaign() {
        check_ajax_referer('gymlite_create_campaign', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target, 'status' => 'draft'],
            ['%s', '%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Campaign created with ID " . $wpdb->insert_id . " at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign created successfully.', 'gymlite'), 'id' => $wpdb->insert_id]);
        }
        wp_send_json_error(['message' => __('Campaign creation failed.', 'gymlite')]);
    }

    public function handle_update_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target],
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        if ($result !== false) {
            gymlite_log("Campaign ID $campaign_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign update failed.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = intval($_POST['member_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'waiver_id' => $waiver_id, 'signed_date' => current_time('mysql'), 'signature' => $signature],
            ['%d', '%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_update_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $content = sanitize_textarea_field($_POST['content']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->posts}",
            ['post_content' => $content],
            ['ID' => $waiver_id, 'post_type' => 'gymlite_waiver'],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver update failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_update_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_access_logs",
            ['status' => $status],
            ['member_id' => $member_id, 'access_time' => current_time('mysql')],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access status updated for member ID $member_id to $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access update failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->update(
            $table_name,
            ['level' => $level, 'promoted_date' => current_time('mysql')],
            ['member_id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result !== false || $result === 0) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Progression updated for member ID $member_id to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Progression updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Progression update failed.', 'gymlite')]);
    }

    public function export_comms_log() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $logs = $wpdb->get_results("SELECT c.type, m.post_title AS member_name, c.sent_date, c.status 
            FROM {$wpdb->prefix}gymlite_comms_logs c 
            LEFT JOIN {$wpdb->posts} m ON c.member_id = m.post_author 
            ORDER BY c.sent_date DESC");
        if ($logs) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="comms_log.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Member', 'Sent Date', 'Status']);
            foreach ($logs as $row) {
                fputcsv($output, [$row->type, $row->member_name ?: 'Unknown', $row->sent_date, $row->status]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No comms data to export.', 'gymlite')]);
    }

    public function billing() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $recurrings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_recurring WHERE next_billing_date <= CURDATE() AND status = 'active'");
        if ($recurrings) {
            $stripe_key = get_option('gymlite_stripe_key');
            if ($stripe_key) {
                // Placeholder Stripe integration
                foreach ($recurrings as $recurring) {
                    $payment_amount = $recurring->amount;
                    $description = "Recurring payment for " . $recurring->plan_type;
                    // $charge = \Stripe\Charge::create([
                    //     'amount' => $payment_amount * 100,
                    //     'currency' => 'usd',
                    //     'description' => $description,
                    //     'customer' => $recurring->member_id, // Replace with actual Stripe customer ID
                    //     'source' => $source, // Replace with token
                    // ]);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_payments",
                        ['member_id' => $recurring->member_id, 'amount' => $payment_amount, 'payment_date' => current_time('mysql'), 'payment_type' => 'recurring'],
                        ['%d', '%f', '%s', '%s']
                    );
                    $new_date = date('Y-m-d', strtotime($recurring->next_billing_date . ' +1 month'));
                    $wpdb->update(
                        "{$wpdb->prefix}gymlite_recurring",
                        ['next_billing_date' => $new_date, 'last_payment_date' => current_time('mysql')],
                        ['id' => $recurring->id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }
                gymlite_log("Billing processed for " . count($recurrings) . " members at " . current_time('Y-m-d H:i:s'));
            }
        }
    }

    public function send_daily_notifications() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $tomorrow_classes = $wpdb->get_results("SELECT post_title, meta_value AS class_date 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE post_type = 'gymlite_class' AND post_status = 'publish' 
            AND meta_key = '_gymlite_class_date' 
            AND DATE(meta_value) = CURDATE() + INTERVAL 1 DAY");
        if ($tomorrow_classes) {
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email) {
                    $message = sprintf(__('Reminder: Tomorrow\'s class %s is scheduled at %s.', 'gymlite'), $tomorrow_classes[0]->post_title, date('H:i', strtotime($tomorrow_classes[0]->class_date)));
                    wp_mail($email, __('Class Reminder', 'gymlite'), $message);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_comms_logs",
                        ['type' => 'email', 'member_id' => $member->post_author, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                        ['%s', '%d', '%s', '%s']
                    );
                }
            }
            gymlite_log("Sent daily notifications for " . count($members) . " members at " . current_time('Y-m-d H:i:s'));
        }
    }

// Continuation of class-gymlite-admin.php (Part 3 of 5)

// [No additional class definition or constructor here - this is a continuation]

    public function export_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC");
        if ($results) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="attendance_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Class', 'Date']);
            foreach ($results as $row) {
                fputcsv($output, [$row->member_name, $row->class_name, $row->attendance_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No data to export.', 'gymlite')]);
    }

    public function handle_manual_checkin() {
        check_ajax_referer('gymlite_manual_checkin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result === false) {
            wp_send_json_error(['message' => __('Check-in failed: ' . $wpdb->last_error, 'gymlite')]);
        }
        gymlite_log("Manual check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
    }

    public function handle_bulk_checkin() {
        check_ajax_referer('gymlite_bulk_checkin', 'bulk_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_ids = array_map('intval', explode("\n", trim($_POST['member_ids'])));
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $success_count = 0;
        foreach ($member_ids as $member_id) {
            if ($member_id) {
                $result = $wpdb->insert(
                    $table_name,
                    ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
                    ['%d', '%d', '%s']
                );
                if ($result !== false) {
                    $success_count++;
                }
            }
        }
        gymlite_log("Bulk check-in processed $success_count members into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => sprintf(__('Successfully checked in %d members.', 'gymlite'), $success_count)]);
    }

    public function export_growth_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $growth = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.next_billing_date 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            WHERE r.next_billing_date > CURDATE() - INTERVAL 30 DAY AND r.status = 'active'");
        if ($growth) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="growth_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Plan Type', 'Next Billing Date']);
            foreach ($growth as $row) {
                fputcsv($output, [$row->member_name, $row->plan_type, $row->next_billing_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No growth data to export.', 'gymlite')]);
    }

    public function export_pos_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $sales = $wpdb->get_results("SELECT s.sale_date, p.name AS product_name, s.quantity, s.amount, m.post_title AS member_name 
            FROM {$wpdb->prefix}gymlite_sales s 
            LEFT JOIN {$wpdb->posts} m ON s.member_id = m.post_author 
            JOIN {$wpdb->prefix}gymlite_products p ON s.product_id = p.id 
            ORDER BY s.sale_date DESC");
        if ($sales) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="pos_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Product', 'Quantity', 'Amount', 'Member']);
            foreach ($sales as $row) {
                fputcsv($output, [$row->sale_date, $row->product_name, $row->quantity, $row->amount, $row->member_name ?: 'Non-Member']);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No POS data to export.', 'gymlite')]);
    }

    public function handle_overdue() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        if ($updated !== false) {
            gymlite_log("Processed $updated overdue payments at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => sprintf(__('Processed %d overdue payments.', 'gymlite'), $updated)]);
        }
        wp_send_json_error(['message' => __('No overdue payments processed.', 'gymlite')]);
    }

    public function handle_sale() {
        check_ajax_referer('gymlite_create_sale', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $product_id = intval($_POST['product_id']);
        $member_id = intval($_POST['member_id']);
        $quantity = intval($_POST['quantity']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_sales';
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM {$wpdb->prefix}gymlite_products WHERE id = %d", $product_id));
        if ($product) {
            $amount = $product->price * $quantity;
            $result = $wpdb->insert(
                $table_name,
                ['member_id' => $member_id, 'product_id' => $product_id, 'quantity' => $quantity, 'amount' => $amount, 'sale_date' => current_time('mysql')],
                ['%d', '%d', '%d', '%f', '%s']
            );
            if ($result !== false) {
                gymlite_log("Sale processed for product ID $product_id, member ID $member_id at " . current_time('Y-m-d H:i:s'));
                wp_send_json_success(['message' => __('Sale processed successfully.', 'gymlite')]);
            }
        }
        wp_send_json_error(['message' => __('Sale processing failed.', 'gymlite')]);
    }

    public function handle_send_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_campaigns WHERE id = %d", $campaign_id));
        if ($campaign && $campaign->status === 'draft') {
            $wpdb->update(
                "{$wpdb->prefix}gymlite_campaigns",
                ['status' => 'sent', 'sent_date' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%s', '%s'],
                ['%d']
            );
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email && $campaign->type === 'email' && (in_array($campaign->target, ['all', 'active']) || ($campaign->target === 'trial' && get_post_meta($member->post_author, '_gymlite_membership_type', true) === 'trial'))) {
                    wp_mail($email, $campaign->name, $campaign->content);
                }
                $wpdb->insert(
                    "{$wpdb->prefix}gymlite_comms_logs",
                    ['type' => $campaign->type, 'member_id' => $member->post_author, 'campaign_id' => $campaign_id, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                    ['%s', '%d', '%d', '%s', '%s']
                );
            }
            gymlite_log("Campaign ID $campaign_id sent at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign sent successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign sending failed.', 'gymlite')]);
    }

    public function handle_create_campaign() {
        check_ajax_referer('gymlite_create_campaign', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target, 'status' => 'draft'],
            ['%s', '%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Campaign created with ID " . $wpdb->insert_id . " at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign created successfully.', 'gymlite'), 'id' => $wpdb->insert_id]);
        }
        wp_send_json_error(['message' => __('Campaign creation failed.', 'gymlite')]);
    }

    public function handle_update_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target],
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        if ($result !== false) {
            gymlite_log("Campaign ID $campaign_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign update failed.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = intval($_POST['member_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'waiver_id' => $waiver_id, 'signed_date' => current_time('mysql'), 'signature' => $signature],
            ['%d', '%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_update_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $content = sanitize_textarea_field($_POST['content']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->posts}",
            ['post_content' => $content],
            ['ID' => $waiver_id, 'post_type' => 'gymlite_waiver'],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver update failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_update_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_access_logs",
            ['status' => $status],
            ['member_id' => $member_id, 'access_time' => current_time('mysql')],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access status updated for member ID $member_id to $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access update failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->update(
            $table_name,
            ['level' => $level, 'promoted_date' => current_time('mysql')],
            ['member_id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result !== false || $result === 0) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Progression updated for member ID $member_id to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Progression updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Progression update failed.', 'gymlite')]);
    }

    public function export_comms_log() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $logs = $wpdb->get_results("SELECT c.type, m.post_title AS member_name, c.sent_date, c.status 
            FROM {$wpdb->prefix}gymlite_comms_logs c 
            LEFT JOIN {$wpdb->posts} m ON c.member_id = m.post_author 
            ORDER BY c.sent_date DESC");
        if ($logs) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="comms_log.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Member', 'Sent Date', 'Status']);
            foreach ($logs as $row) {
                fputcsv($output, [$row->type, $row->member_name ?: 'Unknown', $row->sent_date, $row->status]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No comms data to export.', 'gymlite')]);
    }

    public function billing() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $recurrings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_recurring WHERE next_billing_date <= CURDATE() AND status = 'active'");
        if ($recurrings) {
            $stripe_key = get_option('gymlite_stripe_key');
            if ($stripe_key) {
                require_once GYMLITE_DIR . 'includes/stripe-php/init.php';
                \Stripe\Stripe::setApiKey($stripe_key);
                foreach ($recurrings as $recurring) {
                    $member = get_post($recurring->member_id);
                    if ($member) {
                        $email = get_post_meta($recurring->member_id, '_gymlite_member_email', true);
                        $customer = \Stripe\Customer::create([
                            'email' => $email,
                            'name' => $member->post_title,
                        ]);
                        $payment_intent = \Stripe\PaymentIntent::create([
                            'amount' => $recurring->amount * 100,
                            'currency' => 'usd',
                            'customer' => $customer->id,
                            'description' => "Recurring payment for " . $recurring->plan_type,
                        ]);
                        $wpdb->insert(
                            "{$wpdb->prefix}gymlite_payments",
                            ['member_id' => $recurring->member_id, 'amount' => $recurring->amount, 'payment_date' => current_time('mysql'), 'stripe_payment_id' => $payment_intent->id, 'payment_type' => 'recurring'],
                            ['%d', '%f', '%s', '%s', '%s']
                        );
                        $new_date = date('Y-m-d', strtotime($recurring->next_billing_date . ' +1 month'));
                        $wpdb->update(
                            "{$wpdb->prefix}gymlite_recurring",
                            ['next_billing_date' => $new_date, 'last_payment_date' => current_time('mysql')],
                            ['id' => $recurring->id],
                            ['%s', '%s'],
                            ['%d']
                        );
                    }
                }
                gymlite_log("Billing processed for " . count($recurrings) . " members at " . current_time('Y-m-d H:i:s'));
            }
        }
    }

    public function send_daily_notifications() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $tomorrow_classes = $wpdb->get_results("SELECT post_title, meta_value AS class_date 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE post_type = 'gymlite_class' AND post_status = 'publish' 
            AND meta_key = '_gymlite_class_date' 
            AND DATE(meta_value) = CURDATE() + INTERVAL 1 DAY");
        if ($tomorrow_classes) {
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email) {
                    $message = sprintf(__('Reminder: Tomorrow\'s class %s is scheduled at %s.', 'gymlite'), $tomorrow_classes[0]->post_title, date('H:i', strtotime($tomorrow_classes[0]->class_date)));
                    wp_mail($email, __('Class Reminder', 'gymlite'), $message);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_comms_logs",
                        ['type' => 'email', 'member_id' => $member->post_author, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                        ['%s', '%d', '%s', '%s']
                    );
                }
            }
            gymlite_log("Sent daily notifications for " . count($members) . " members at " . current_time('Y-m-d H:i:s'));
        }
    }

// Continuation of class-gymlite-admin.php (Part 4 of 5)

// [No additional class definition or constructor here - this is a continuation]

    public function export_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC");
        if ($results) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="attendance_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Class', 'Date']);
            foreach ($results as $row) {
                fputcsv($output, [$row->member_name, $row->class_name, $row->attendance_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No data to export.', 'gymlite')]);
    }

    public function handle_manual_checkin() {
        check_ajax_referer('gymlite_manual_checkin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result === false) {
            wp_send_json_error(['message' => __('Check-in failed: ' . $wpdb->last_error, 'gymlite')]);
        }
        gymlite_log("Manual check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
    }

    public function handle_bulk_checkin() {
        check_ajax_referer('gymlite_bulk_checkin', 'bulk_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_ids = array_map('intval', explode("\n", trim($_POST['member_ids'])));
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $success_count = 0;
        foreach ($member_ids as $member_id) {
            if ($member_id) {
                $result = $wpdb->insert(
                    $table_name,
                    ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
                    ['%d', '%d', '%s']
                );
                if ($result !== false) {
                    $success_count++;
                }
            }
        }
        gymlite_log("Bulk check-in processed $success_count members into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => sprintf(__('Successfully checked in %d members.', 'gymlite'), $success_count)]);
    }

    public function export_growth_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $growth = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.next_billing_date 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            WHERE r.next_billing_date > CURDATE() - INTERVAL 30 DAY AND r.status = 'active'");
        if ($growth) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="growth_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Plan Type', 'Next Billing Date']);
            foreach ($growth as $row) {
                fputcsv($output, [$row->member_name, $row->plan_type, $row->next_billing_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No growth data to export.', 'gymlite')]);
    }

    public function export_pos_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $sales = $wpdb->get_results("SELECT s.sale_date, p.name AS product_name, s.quantity, s.amount, m.post_title AS member_name 
            FROM {$wpdb->prefix}gymlite_sales s 
            LEFT JOIN {$wpdb->posts} m ON s.member_id = m.post_author 
            JOIN {$wpdb->prefix}gymlite_products p ON s.product_id = p.id 
            ORDER BY s.sale_date DESC");
        if ($sales) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="pos_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Product', 'Quantity', 'Amount', 'Member']);
            foreach ($sales as $row) {
                fputcsv($output, [$row->sale_date, $row->product_name, $row->quantity, $row->amount, $row->member_name ?: 'Non-Member']);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No POS data to export.', 'gymlite')]);
    }

    public function handle_overdue() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        if ($updated !== false) {
            gymlite_log("Processed $updated overdue payments at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => sprintf(__('Processed %d overdue payments.', 'gymlite'), $updated)]);
        }
        wp_send_json_error(['message' => __('No overdue payments processed.', 'gymlite')]);
    }

    public function handle_sale() {
        check_ajax_referer('gymlite_create_sale', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $product_id = intval($_POST['product_id']);
        $member_id = intval($_POST['member_id']);
        $quantity = intval($_POST['quantity']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_sales';
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM {$wpdb->prefix}gymlite_products WHERE id = %d", $product_id));
        if ($product) {
            $amount = $product->price * $quantity;
            $result = $wpdb->insert(
                $table_name,
                ['member_id' => $member_id, 'product_id' => $product_id, 'quantity' => $quantity, 'amount' => $amount, 'sale_date' => current_time('mysql')],
                ['%d', '%d', '%d', '%f', '%s']
            );
            if ($result !== false) {
                gymlite_log("Sale processed for product ID $product_id, member ID $member_id at " . current_time('Y-m-d H:i:s'));
                wp_send_json_success(['message' => __('Sale processed successfully.', 'gymlite')]);
            }
        }
        wp_send_json_error(['message' => __('Sale processing failed.', 'gymlite')]);
    }

    public function handle_send_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_campaigns WHERE id = %d", $campaign_id));
        if ($campaign && $campaign->status === 'draft') {
            $wpdb->update(
                "{$wpdb->prefix}gymlite_campaigns",
                ['status' => 'sent', 'sent_date' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%s', '%s'],
                ['%d']
            );
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email && $campaign->type === 'email' && (in_array($campaign->target, ['all', 'active']) || ($campaign->target === 'trial' && get_post_meta($member->post_author, '_gymlite_membership_type', true) === 'trial'))) {
                    wp_mail($email, $campaign->name, $campaign->content);
                }
                $wpdb->insert(
                    "{$wpdb->prefix}gymlite_comms_logs",
                    ['type' => $campaign->type, 'member_id' => $member->post_author, 'campaign_id' => $campaign_id, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                    ['%s', '%d', '%d', '%s', '%s']
                );
            }
            gymlite_log("Campaign ID $campaign_id sent at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign sent successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign sending failed.', 'gymlite')]);
    }

    public function handle_create_campaign() {
        check_ajax_referer('gymlite_create_campaign', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target, 'status' => 'draft'],
            ['%s', '%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Campaign created with ID " . $wpdb->insert_id . " at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign created successfully.', 'gymlite'), 'id' => $wpdb->insert_id]);
        }
        wp_send_json_error(['message' => __('Campaign creation failed.', 'gymlite')]);
    }

    public function handle_update_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target],
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        if ($result !== false) {
            gymlite_log("Campaign ID $campaign_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign update failed.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = intval($_POST['member_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'waiver_id' => $waiver_id, 'signed_date' => current_time('mysql'), 'signature' => $signature],
            ['%d', '%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_update_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $content = sanitize_textarea_field($_POST['content']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->posts}",
            ['post_content' => $content],
            ['ID' => $waiver_id, 'post_type' => 'gymlite_waiver'],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver update failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_update_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_access_logs",
            ['status' => $status],
            ['member_id' => $member_id, 'access_time' => current_time('mysql')],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access status updated for member ID $member_id to $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access update failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->update(
            $table_name,
            ['level' => $level, 'promoted_date' => current_time('mysql')],
            ['member_id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result !== false || $result === 0) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Progression updated for member ID $member_id to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Progression updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Progression update failed.', 'gymlite')]);
    }

    public function export_comms_log() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $logs = $wpdb->get_results("SELECT c.type, m.post_title AS member_name, c.sent_date, c.status 
            FROM {$wpdb->prefix}gymlite_comms_logs c 
            LEFT JOIN {$wpdb->posts} m ON c.member_id = m.post_author 
            ORDER BY c.sent_date DESC");
        if ($logs) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="comms_log.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Member', 'Sent Date', 'Status']);
            foreach ($logs as $row) {
                fputcsv($output, [$row->type, $row->member_name ?: 'Unknown', $row->sent_date, $row->status]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No comms data to export.', 'gymlite')]);
    }

    public function billing() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $recurrings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_recurring WHERE next_billing_date <= CURDATE() AND status = 'active'");
        if ($recurrings) {
            $stripe_key = get_option('gymlite_stripe_key');
            if ($stripe_key) {
                require_once GYMLITE_DIR . 'includes/stripe-php/init.php';
                \Stripe\Stripe::setApiKey($stripe_key);
                foreach ($recurrings as $recurring) {
                    $member = get_post($recurring->member_id);
                    if ($member) {
                        $email = get_post_meta($recurring->member_id, '_gymlite_member_email', true);
                        $customer = \Stripe\Customer::create([
                            'email' => $email,
                            'name' => $member->post_title,
                        ]);
                        $payment_intent = \Stripe\PaymentIntent::create([
                            'amount' => $recurring->amount * 100,
                            'currency' => 'usd',
                            'customer' => $customer->id,
                            'description' => "Recurring payment for " . $recurring->plan_type,
                        ]);
                        $wpdb->insert(
                            "{$wpdb->prefix}gymlite_payments",
                            ['member_id' => $recurring->member_id, 'amount' => $recurring->amount, 'payment_date' => current_time('mysql'), 'stripe_payment_id' => $payment_intent->id, 'payment_type' => 'recurring'],
                            ['%d', '%f', '%s', '%s', '%s']
                        );
                        $new_date = date('Y-m-d', strtotime($recurring->next_billing_date . ' +1 month'));
                        $wpdb->update(
                            "{$wpdb->prefix}gymlite_recurring",
                            ['next_billing_date' => $new_date, 'last_payment_date' => current_time('mysql')],
                            ['id' => $recurring->id],
                            ['%s', '%s'],
                            ['%d']
                        );
                    }
                }
                gymlite_log("Billing processed for " . count($recurrings) . " members at " . current_time('Y-m-d H:i:s'));
            }
        }
    }

    public function send_daily_notifications() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $tomorrow_classes = $wpdb->get_results("SELECT post_title, meta_value AS class_date 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE post_type = 'gymlite_class' AND post_status = 'publish' 
            AND meta_key = '_gymlite_class_date' 
            AND DATE(meta_value) = CURDATE() + INTERVAL 1 DAY");
        if ($tomorrow_classes) {
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email) {
                    $message = sprintf(__('Reminder: Tomorrow\'s class %s is scheduled at %s.', 'gymlite'), $tomorrow_classes[0]->post_title, date('H:i', strtotime($tomorrow_classes[0]->class_date)));
                    wp_mail($email, __('Class Reminder', 'gymlite'), $message);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_comms_logs",
                        ['type' => 'email', 'member_id' => $member->post_author, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                        ['%s', '%d', '%s', '%s']
                    );
                }
            }
            gymlite_log("Sent daily notifications for " . count($members) . " members at " . current_time('Y-m-d H:i:s'));
        }
    }
// Continuation of class-gymlite-admin.php (Part 5 of 5)

// [No additional class definition or constructor here - this is a continuation]

    public function export_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT m.post_title AS member_name, c.post_title AS class_name, a.attendance_date 
            FROM {$wpdb->prefix}gymlite_attendance a 
            JOIN {$wpdb->posts} m ON a.member_id = m.post_author 
            JOIN {$wpdb->posts} c ON a.class_id = c.ID 
            ORDER BY a.attendance_date DESC");
        if ($results) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="attendance_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Class', 'Date']);
            foreach ($results as $row) {
                fputcsv($output, [$row->member_name, $row->class_name, $row->attendance_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No data to export.', 'gymlite')]);
    }

    public function handle_manual_checkin() {
        check_ajax_referer('gymlite_manual_checkin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result === false) {
            wp_send_json_error(['message' => __('Check-in failed: ' . $wpdb->last_error, 'gymlite')]);
        }
        gymlite_log("Manual check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
    }

    public function handle_bulk_checkin() {
        check_ajax_referer('gymlite_bulk_checkin', 'bulk_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_ids = array_map('intval', explode("\n", trim($_POST['member_ids'])));
        $class_id = intval($_POST['class_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $success_count = 0;
        foreach ($member_ids as $member_id) {
            if ($member_id) {
                $result = $wpdb->insert(
                    $table_name,
                    ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
                    ['%d', '%d', '%s']
                );
                if ($result !== false) {
                    $success_count++;
                }
            }
        }
        gymlite_log("Bulk check-in processed $success_count members into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => sprintf(__('Successfully checked in %d members.', 'gymlite'), $success_count)]);
    }

    public function export_growth_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $growth = $wpdb->get_results("SELECT m.post_title AS member_name, r.plan_type, r.next_billing_date 
            FROM {$wpdb->prefix}gymlite_recurring r 
            JOIN {$wpdb->posts} m ON r.member_id = m.post_author 
            WHERE r.next_billing_date > CURDATE() - INTERVAL 30 DAY AND r.status = 'active'");
        if ($growth) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="growth_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member', 'Plan Type', 'Next Billing Date']);
            foreach ($growth as $row) {
                fputcsv($output, [$row->member_name, $row->plan_type, $row->next_billing_date]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No growth data to export.', 'gymlite')]);
    }

    public function export_pos_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $sales = $wpdb->get_results("SELECT s.sale_date, p.name AS product_name, s.quantity, s.amount, m.post_title AS member_name 
            FROM {$wpdb->prefix}gymlite_sales s 
            LEFT JOIN {$wpdb->posts} m ON s.member_id = m.post_author 
            JOIN {$wpdb->prefix}gymlite_products p ON s.product_id = p.id 
            ORDER BY s.sale_date DESC");
        if ($sales) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="pos_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Product', 'Quantity', 'Amount', 'Member']);
            foreach ($sales as $row) {
                fputcsv($output, [$row->sale_date, $row->product_name, $row->quantity, $row->amount, $row->member_name ?: 'Non-Member']);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No POS data to export.', 'gymlite')]);
    }

    public function handle_overdue() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        if ($updated !== false) {
            gymlite_log("Processed $updated overdue payments at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => sprintf(__('Processed %d overdue payments.', 'gymlite'), $updated)]);
        }
        wp_send_json_error(['message' => __('No overdue payments processed.', 'gymlite')]);
    }

    public function handle_sale() {
        check_ajax_referer('gymlite_create_sale', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $product_id = intval($_POST['product_id']);
        $member_id = intval($_POST['member_id']);
        $quantity = intval($_POST['quantity']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_sales';
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM {$wpdb->prefix}gymlite_products WHERE id = %d", $product_id));
        if ($product) {
            $amount = $product->price * $quantity;
            $result = $wpdb->insert(
                $table_name,
                ['member_id' => $member_id, 'product_id' => $product_id, 'quantity' => $quantity, 'amount' => $amount, 'sale_date' => current_time('mysql')],
                ['%d', '%d', '%d', '%f', '%s']
            );
            if ($result !== false) {
                gymlite_log("Sale processed for product ID $product_id, member ID $member_id at " . current_time('Y-m-d H:i:s'));
                wp_send_json_success(['message' => __('Sale processed successfully.', 'gymlite')]);
            }
        }
        wp_send_json_error(['message' => __('Sale processing failed.', 'gymlite')]);
    }

    public function handle_send_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_campaigns WHERE id = %d", $campaign_id));
        if ($campaign && $campaign->status === 'draft') {
            $wpdb->update(
                "{$wpdb->prefix}gymlite_campaigns",
                ['status' => 'sent', 'sent_date' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%s', '%s'],
                ['%d']
            );
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email && $campaign->type === 'email' && (in_array($campaign->target, ['all', 'active']) || ($campaign->target === 'trial' && get_post_meta($member->post_author, '_gymlite_membership_type', true) === 'trial'))) {
                    wp_mail($email, $campaign->name, $campaign->content);
                }
                $wpdb->insert(
                    "{$wpdb->prefix}gymlite_comms_logs",
                    ['type' => $campaign->type, 'member_id' => $member->post_author, 'campaign_id' => $campaign_id, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                    ['%s', '%d', '%d', '%s', '%s']
                );
            }
            gymlite_log("Campaign ID $campaign_id sent at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign sent successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign sending failed.', 'gymlite')]);
    }

    public function handle_create_campaign() {
        check_ajax_referer('gymlite_create_campaign', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target, 'status' => 'draft'],
            ['%s', '%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Campaign created with ID " . $wpdb->insert_id . " at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign created successfully.', 'gymlite'), 'id' => $wpdb->insert_id]);
        }
        wp_send_json_error(['message' => __('Campaign creation failed.', 'gymlite')]);
    }

    public function handle_update_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $campaign_id = intval($_POST['campaign_id']);
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $content = sanitize_textarea_field($_POST['campaign_content']);
        $target = sanitize_text_field($_POST['campaign_target']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_campaigns",
            ['name' => $name, 'type' => $type, 'content' => $content, 'target' => $target],
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        if ($result !== false) {
            gymlite_log("Campaign ID $campaign_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Campaign updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Campaign update failed.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = intval($_POST['member_id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'waiver_id' => $waiver_id, 'signed_date' => current_time('mysql'), 'signature' => $signature],
            ['%d', '%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_update_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $content = sanitize_textarea_field($_POST['content']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->posts}",
            ['post_content' => $content],
            ['ID' => $waiver_id, 'post_type' => 'gymlite_waiver'],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id updated at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver update failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_update_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $status = sanitize_text_field($_POST['status']);
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}gymlite_access_logs",
            ['status' => $status],
            ['member_id' => $member_id, 'access_time' => current_time('mysql')],
            ['%s'],
            ['%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access status updated for member ID $member_id to $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access update failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

    public function handle_update_progression() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->update(
            $table_name,
            ['level' => $level, 'promoted_date' => current_time('mysql')],
            ['member_id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result !== false || $result === 0) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Progression updated for member ID $member_id to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Progression updated successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Progression update failed.', 'gymlite')]);
    }

    public function export_comms_log() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options') || !self::is_premium_active()) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        global $wpdb;
        $logs = $wpdb->get_results("SELECT c.type, m.post_title AS member_name, c.sent_date, c.status 
            FROM {$wpdb->prefix}gymlite_comms_logs c 
            LEFT JOIN {$wpdb->posts} m ON c.member_id = m.post_author 
            ORDER BY c.sent_date DESC");
        if ($logs) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="comms_log.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Member', 'Sent Date', 'Status']);
            foreach ($logs as $row) {
                fputcsv($output, [$row->type, $row->member_name ?: 'Unknown', $row->sent_date, $row->status]);
            }
            fclose($output);
            exit;
        }
        wp_send_json_error(['message' => __('No comms data to export.', 'gymlite')]);
    }

    public function billing() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $recurrings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_recurring WHERE next_billing_date <= CURDATE() AND status = 'active'");
        if ($recurrings) {
            $stripe_key = get_option('gymlite_stripe_key');
            if ($stripe_key) {
                require_once GYMLITE_DIR . 'includes/stripe-php/init.php';
                \Stripe\Stripe::setApiKey($stripe_key);
                foreach ($recurrings as $recurring) {
                    $member = get_post($recurring->member_id);
                    if ($member) {
                        $email = get_post_meta($recurring->member_id, '_gymlite_member_email', true);
                        $customer = \Stripe\Customer::create([
                            'email' => $email,
                            'name' => $member->post_title,
                        ]);
                        $payment_intent = \Stripe\PaymentIntent::create([
                            'amount' => $recurring->amount * 100,
                            'currency' => 'usd',
                            'customer' => $customer->id,
                            'description' => "Recurring payment for " . $recurring->plan_type,
                        ]);
                        $wpdb->insert(
                            "{$wpdb->prefix}gymlite_payments",
                            ['member_id' => $recurring->member_id, 'amount' => $recurring->amount, 'payment_date' => current_time('mysql'), 'stripe_payment_id' => $payment_intent->id, 'payment_type' => 'recurring'],
                            ['%d', '%f', '%s', '%s', '%s']
                        );
                        $new_date = date('Y-m-d', strtotime($recurring->next_billing_date . ' +1 month'));
                        $wpdb->update(
                            "{$wpdb->prefix}gymlite_recurring",
                            ['next_billing_date' => $new_date, 'last_payment_date' => current_time('mysql')],
                            ['id' => $recurring->id],
                            ['%s', '%s'],
                            ['%d']
                        );
                    }
                }
                gymlite_log("Billing processed for " . count($recurrings) . " members at " . current_time('Y-m-d H:i:s'));
            }
        }
    }

    public function send_daily_notifications() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $tomorrow_classes = $wpdb->get_results("SELECT post_title, meta_value AS class_date 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE post_type = 'gymlite_class' AND post_status = 'publish' 
            AND meta_key = '_gymlite_class_date' 
            AND DATE(meta_value) = CURDATE() + INTERVAL 1 DAY");
        if ($tomorrow_classes) {
            $members = get_posts(['post_type' => 'gymlite_member', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($members as $member) {
                $email = get_post_meta($member->post_author, '_gymlite_member_email', true);
                if ($email) {
                    $message = sprintf(__('Reminder: Tomorrow\'s class %s is scheduled at %s.', 'gymlite'), $tomorrow_classes[0]->post_title, date('H:i', strtotime($tomorrow_classes[0]->class_date)));
                    wp_mail($email, __('Class Reminder', 'gymlite'), $message);
                    $wpdb->insert(
                        "{$wpdb->prefix}gymlite_comms_logs",
                        ['type' => 'email', 'member_id' => $member->post_author, 'sent_date' => current_time('mysql'), 'status' => 'sent'],
                        ['%s', '%d', '%s', '%s']
                    );
                }
            }
            gymlite_log("Sent daily notifications for " . count($members) . " members at " . current_time('Y-m-d H:i:s'));
        }
    }
}