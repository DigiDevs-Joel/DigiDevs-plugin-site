<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Install {
    public static function activate() {
        try {
            gymlite_log("Starting activation at " . current_time('Y-m-d H:i:s'));
            self::create_tables();
            self::create_pages();
            update_option('gymlite_version', GYMLITE_VERSION);
            gymlite_log("Activation completed successfully at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Activation error: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $tables = [
            "CREATE TABLE {$prefix}gymlite_attendance (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                class_id BIGINT UNSIGNED NOT NULL,
                attendance_date DATETIME NOT NULL,
                UNIQUE KEY member_class (member_id, class_id)
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_date DATETIME NOT NULL,
                status ENUM('paid', 'pending', 'failed') DEFAULT 'pending',
                transaction_id VARCHAR(255)
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_recurring (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                plan VARCHAR(100) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                next_billing_date DATE NOT NULL,
                status ENUM('active', 'cancelled', 'overdue') DEFAULT 'active'
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_leads (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50),
                created_at DATETIME NOT NULL
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_campaigns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type ENUM('email', 'sms') NOT NULL,
                content TEXT NOT NULL,
                sent_at DATETIME
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_waivers_signed (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                waiver_id BIGINT UNSIGNED NOT NULL,
                signed_date DATETIME NOT NULL,
                signature TEXT
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_access_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                access_time DATETIME NOT NULL,
                status ENUM('granted', 'denied') NOT NULL
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_progression (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                level VARCHAR(50) NOT NULL,
                promoted_date DATE NOT NULL
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_bookings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                class_id BIGINT UNSIGNED NOT NULL,
                booking_date DATETIME NOT NULL,
                status ENUM('confirmed', 'cancelled') DEFAULT 'confirmed'
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_inventory (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_name VARCHAR(255) NOT NULL,
                quantity INT NOT NULL,
                price DECIMAL(10,2) NOT NULL
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                sent_at DATETIME NOT NULL,
                type ENUM('email', 'sms') NOT NULL
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_referrals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                referrer_id BIGINT UNSIGNED NOT NULL,
                referred_id BIGINT UNSIGNED NOT NULL,
                referred_date DATE NOT NULL,
                status ENUM('pending', 'rewarded') DEFAULT 'pending'
            ) $charset_collate;",

            "CREATE TABLE {$prefix}gymlite_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_type VARCHAR(100) NOT NULL,
                generated_at DATETIME NOT NULL,
                data LONGTEXT
            ) $charset_collate;"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
        gymlite_log("All database tables created or updated.");
    }

    private static function create_pages() {
        $pages = [
            [
                'title' => __('GymLite Login', 'gymlite'),
                'content' => '[gymlite_login]',
                'option' => 'gymlite_login_page_id'
            ],
            [
                'title' => __('GymLite Signup', 'gymlite'),
                'content' => '[gymlite_signup]',
                'option' => 'gymlite_signup_page_id'
            ],
            [
                'title' => __('GymLite Member Portal', 'gymlite'),
                'content' => '[gymlite_portal]',
                'option' => 'gymlite_portal_page_id'
            ],
            [
                'title' => __('GymLite Update Profile', 'gymlite'),
                'content' => '[gymlite_update_profile]',
                'option' => 'gymlite_update_profile_page_id'
            ]
        ];

        foreach ($pages as $page) {
            if (!get_page_by_title($page['title'])) {
                $page_id = wp_insert_post([
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                ]);
                if (!is_wp_error($page_id)) {
                    update_option($page['option'], $page_id);
                    gymlite_log("Created page: {$page['title']} (ID: $page_id)");
                }
            }
        }
    }
}
?>