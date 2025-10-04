<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Install {
    public static function activate() {
        global $wpdb;
        try {
            $charset_collate = $wpdb->get_charset_collate();

            $tables = [
                'attendance' => "CREATE TABLE {$wpdb->prefix}gymlite_attendance (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    class_id bigint(20) NOT NULL,
                    attendance_date datetime NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_attendance (member_id, class_id)
                ) $charset_collate;",
                'payments' => "CREATE TABLE {$wpdb->prefix}gymlite_payments (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    amount decimal(10,2) NOT NULL,
                    payment_date datetime NOT NULL,
                    stripe_payment_id varchar(255) DEFAULT NULL,
                    payment_type varchar(50) DEFAULT 'membership',
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'leads' => "CREATE TABLE {$wpdb->prefix}gymlite_leads (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    email varchar(255) NOT NULL,
                    phone varchar(50) DEFAULT NULL,
                    created_at datetime NOT NULL,
                    status varchar(20) DEFAULT 'new',
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'referrals' => "CREATE TABLE {$wpdb->prefix}gymlite_referrals (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    referrer_id bigint(20) NOT NULL,
                    referred_id bigint(20) NOT NULL,
                    referral_date datetime NOT NULL,
                    credit_amount decimal(5,2) DEFAULT 0,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'class_packs' => "CREATE TABLE {$wpdb->prefix}gymlite_class_packs (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    pack_type varchar(50) NOT NULL,
                    remaining_classes int(11) DEFAULT 10,
                    purchase_date datetime NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'products' => "CREATE TABLE {$wpdb->prefix}gymlite_products (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    description text DEFAULT NULL,
                    price decimal(10,2) NOT NULL,
                    cost decimal(10,2) DEFAULT 0.00,
                    inventory int(11) DEFAULT 0,
                    category varchar(100) DEFAULT NULL,
                    created_at datetime NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'sales' => "CREATE TABLE {$wpdb->prefix}gymlite_sales (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) DEFAULT NULL,
                    product_id bigint(20) NOT NULL,
                    quantity int(11) NOT NULL DEFAULT 1,
                    amount decimal(10,2) NOT NULL,
                    discount decimal(10,2) DEFAULT 0.00,
                    tax decimal(10,2) DEFAULT 0.00,
                    refunded decimal(10,2) DEFAULT 0.00,
                    sale_date datetime NOT NULL,
                    payment_method varchar(50) DEFAULT 'card',
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'recurring' => "CREATE TABLE {$wpdb->prefix}gymlite_recurring (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    plan_type varchar(50) NOT NULL,
                    amount decimal(10,2) NOT NULL,
                    status varchar(20) DEFAULT 'active',
                    next_billing_date date NOT NULL,
                    last_payment_date date DEFAULT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'campaigns' => "CREATE TABLE {$wpdb->prefix}gymlite_campaigns (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    type varchar(50) DEFAULT 'email',
                    content text NOT NULL,
                    target varchar(50) DEFAULT 'all',
                    sent_date datetime DEFAULT NULL,
                    status varchar(20) DEFAULT 'draft',
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'waivers_signed' => "CREATE TABLE {$wpdb->prefix}gymlite_waivers_signed (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    waiver_id bigint(20) NOT NULL,
                    signed_date datetime NOT NULL,
                    signature text DEFAULT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'access_logs' => "CREATE TABLE {$wpdb->prefix}gymlite_access_logs (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    access_time datetime NOT NULL,
                    status varchar(20) DEFAULT 'granted',
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'progression' => "CREATE TABLE {$wpdb->prefix}gymlite_progression (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    member_id bigint(20) NOT NULL,
                    level varchar(50) NOT NULL,
                    promoted_date date NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'comms_logs' => "CREATE TABLE {$wpdb->prefix}gymlite_comms_logs (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    type varchar(20) NOT NULL,
                    member_id bigint(20) DEFAULT NULL,
                    campaign_id bigint(20) DEFAULT NULL,
                    sent_date datetime NOT NULL,
                    status varchar(20) DEFAULT 'sent',
                    PRIMARY KEY (id)
                ) $charset_collate;",
            ];

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            foreach ($tables as $table_name => $sql) {
                dbDelta($sql);
                if (!empty($wpdb->last_error)) {
                    throw new Exception("Table $table_name creation failed: " . $wpdb->last_error);
                }
            }

            // Create pages if not exists
            $pages = [
                'gymlite_login_page_id' => ['title' => __('GymLite Login', 'gymlite'), 'content' => '[gymlite_login]'],
                'gymlite_signup_page_id' => ['title' => __('GymLite Signup', 'gymlite'), 'content' => '[gymlite_signup]'],
                'gymlite_portal_page_id' => ['title' => __('GymLite Member Portal', 'gymlite'), 'content' => '[gymlite_portal]'],
                'gymlite_user_data_page_id' => ['title' => __('GymLite Update Profile', 'gymlite'), 'content' => '[gymlite_user_data]'],
            ];
            foreach ($pages as $option => $page) {
                if (!get_option($option)) {
                    $page_id = wp_insert_post([
                        'post_title' => $page['title'],
                        'post_content' => $page['content'],
                        'post_status' => 'publish',
                        'post_type' => 'page',
                    ]);
                    if (!is_wp_error($page_id)) {
                        update_option($option, $page_id);
                    }
                }
            }

            flush_rewrite_rules();
            gymlite_log("Database tables and pages created or updated at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Activation error in GymLite_Install: " . $e->getMessage());
            throw $e;
        }
    }
}