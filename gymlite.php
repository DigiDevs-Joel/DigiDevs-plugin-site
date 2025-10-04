<?php
/*
Plugin Name: GymLite - Gym Management
Description: A WordPress plugin with all functions and features of Gymdesk: member management, billing, POS, scheduling, attendance, marketing, waivers, access control, progression tracking, and more.
Version: 1.7.0
Author: Your Name
License: GPL-2.0+
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('GYMLITE_VERSION', '1.7.0');
define('GYMLITE_DIR', plugin_dir_path(__FILE__));
define('GYMLITE_URL', plugin_dir_url(__FILE__));
define('GYMLITE_DEBUG_LOG', WP_CONTENT_DIR . '/gymlite-debug.log');

// Debug logging function
function gymlite_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG && is_writable(WP_CONTENT_DIR)) {
        $log_file = WP_DEBUG_LOG;
        if (!file_exists($log_file)) {
            touch($log_file);
            chmod($log_file, 0664);
        }
        error_log(date('[Y-m-d H:i:s] ') . 'GymLite: ' . $message . "\n", 3, $log_file);
    }
}

// Include class files with existence checks
$required_files = [
    GYMLITE_DIR . 'includes/class-gymlite-install.php',
    GYMLITE_DIR . 'includes/class-gymlite-admin.php',
    GYMLITE_DIR . 'includes/class-gymlite-frontend.php',
    GYMLITE_DIR . 'includes/class-gymlite-premium.php',
    GYMLITE_DIR . 'includes/class-gymlite-login.php',
    GYMLITE_DIR . 'includes/class-gymlite-signup.php',
    GYMLITE_DIR . 'includes/class-gymlite-user-data.php',
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        gymlite_log("Missing required file: $file at " . current_time('Y-m-d H:i:s'));
    }
}

// Explicitly instantiate all classes
if (class_exists('GymLite_Frontend')) {
    new GymLite_Frontend();
    gymlite_log("GymLite_Frontend instantiated at " . current_time('Y-m-d H:i:s'));
}
if (class_exists('GymLite_Admin')) {
    new GymLite_Admin();
    gymlite_log("GymLite_Admin instantiated at " . current_time('Y-m-d H:i:s'));
}
if (class_exists('GymLite_Premium')) {
    new GymLite_Premium();
    gymlite_log("GymLite_Premium instantiated at " . current_time('Y-m-d H:i:s'));
}
if (class_exists('GymLite_Login')) {
    new GymLite_Login();
    gymlite_log("GymLite_Login instantiated at " . current_time('Y-m-d H:i:s'));
}
if (class_exists('GymLite_Signup')) {
    new GymLite_Signup();
    gymlite_log("GymLite_Signup instantiated at " . current_time('Y-m-d H:i:s'));
}
if (class_exists('GymLite_User_Data')) {
    new GymLite_User_Data();
    gymlite_log("GymLite_User_Data instantiated at " . current_time('Y-m-d H:i:s'));
}

// Activation hook with buffered output
register_activation_hook(__FILE__, function () {
    ob_start();
    try {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        if (class_exists('GymLite_Install')) {
            GymLite_Install::activate();
            update_option('gymlite_activated', time());
            gymlite_log("Plugin activated successfully at " . current_time('Y-m-d H:i:s'));
        } else {
            throw new Exception('GymLite_Install class not found.');
        }
    } catch (Exception $e) {
        $output = ob_get_clean();
        if (!empty($output)) {
            echo $output;
        }
        gymlite_log("Activation failed: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        echo '<div class="error"><p><strong>GymLite Activation Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
    }
    ob_end_clean();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('gymlite_daily_cron');
    delete_option('gymlite_activated');
    gymlite_log("Plugin deactivated at " . current_time('Y-m-d H:i:s'));
});

// Initialize plugin
class GymLite {
    private static $instance = null;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_types'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('gymlite_daily_cron', [$this, 'run_daily_tasks']);

        if (isset($_GET['gymlite_activate']) && current_user_can('manage_options')) {
            $this->manual_activate();
        }

        if (!wp_next_scheduled('gymlite_daily_cron')) {
            wp_schedule_event(time(), 'daily', 'gymlite_daily_cron');
            gymlite_log("Scheduled daily cron at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function register_post_types() {
        $post_types = [
            'gymlite_member' => [
                'labels' => ['name' => __('Members', 'gymlite'), 'singular_name' => __('Member', 'gymlite')],
                'public' => false,
                'show_ui' => true,
                'supports' => ['title', 'author', 'custom-fields'],
                'menu_icon' => 'dashicons-groups',
            ],
            'gymlite_class' => [
                'labels' => ['name' => __('Classes', 'gymlite'), 'singular_name' => __('Class', 'gymlite')],
                'public' => true,
                'show_ui' => true,
                'supports' => ['title', 'editor', 'custom-fields'],
                'menu_icon' => 'dashicons-calendar-alt',
            ],
            'gymlite_staff' => [
                'labels' => ['name' => __('Staff', 'gymlite'), 'singular_name' => __('Staff Member', 'gymlite')],
                'public' => false,
                'show_ui' => true,
                'supports' => ['title', 'custom-fields'],
                'menu_icon' => 'dashicons-businessman',
            ],
            'gymlite_waiver' => [
                'labels' => ['name' => __('Waivers', 'gymlite'), 'singular_name' => __('Waiver', 'gymlite')],
                'public' => false,
                'show_ui' => true,
                'supports' => ['title', 'editor'],
                'menu_icon' => 'dashicons-forms',
            ],
        ];

        foreach ($post_types as $type => $args) {
            if (!post_type_exists($type)) {
                register_post_type($type, $args);
            }
        }
        flush_rewrite_rules();
        gymlite_log("Custom post types registered at " . current_time('Y-m-d H:i:s'));
    }

    public function enqueue_scripts() {
        if (!is_admin()) {
            if (!wp_style_is('uikit', 'enqueued') && !wp_style_is('uikit', 'registered')) {
                wp_enqueue_style('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/css/uikit.min.css', [], '3.21.5');
            }
            if (!wp_script_is('uikit', 'enqueued') && !wp_script_is('uikit', 'registered')) {
                wp_enqueue_script('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit.min.js', ['jquery'], '3.21.5', true);
                wp_enqueue_script('uikit-icons', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit-icons.min.js', ['uikit'], '3.21.5', true);
            }

            wp_enqueue_style('gymlite-style', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION);
            wp_enqueue_script('gymlite-script', GYMLITE_URL . 'assets/js/frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);

            $js = '
                jQuery(document).ready(function($) {
                    $(".gymlite-checkin").on("click", function(e) {
                        e.preventDefault();
                        var classId = $(this).data("class-id");
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_checkin", class_id: classId, nonce: gymlite_ajax.nonce},
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $(this).replaceWith(\'<span class="uk-label uk-label-success">Checked In</span>\'); 
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Check-in failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-sign-waiver").on("click", function() {
                        var waiverId = $(this).data("waiver-id");
                        var signature = prompt("' . __('Enter your signature (e.g., initials)', 'gymlite') . '");
                        if (signature) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_sign_waiver", waiver_id: waiverId, signature: signature, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "' . __('Waiver signing failed', 'gymlite') . '", status: "danger"}); }
                            });
                        }
                    });
                    $(".gymlite-log-access").on("click", function() {
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_log_access", nonce: gymlite_ajax.nonce},
                            success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "' . __('Access log failed', 'gymlite') . '", status: "danger"}); }
                        });
                    });
                    $(".gymlite-promote-member").on("click", function() {
                        var memberId = $(this).data("member-id");
                        var level = prompt("' . __('Enter new level (e.g., blue belt)', 'gymlite') . '");
                        if (level) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_promote_member", member_id: memberId, level: level, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "' . __('Promotion failed', 'gymlite') . '", status: "danger"}); }
                            });
                        }
                    });
                });
            ';
            wp_add_inline_script('gymlite-script', $js);
            wp_localize_script('gymlite-script', 'gymlite_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gymlite_nonce'),
            ]);
        }
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gymlite') === false) return;
        wp_enqueue_style('gymlite-admin-style', GYMLITE_URL . 'assets/css/gymlite-admin.css', [], GYMLITE_VERSION);
    }

    public function add_settings_menu() {
        add_submenu_page(
            'gymlite',
            __('GymLite Settings', 'gymlite'),
            __('Settings', 'gymlite'),
            'manage_options',
            'gymlite-settings',
            [$this, 'settings_page']
        );
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['gymlite_settings_nonce']) && wp_verify_nonce($_POST['gymlite_settings_nonce'], 'gymlite_settings_save')) {
            update_option('gymlite_stripe_key', sanitize_text_field($_POST['gymlite_stripe_key'] ?? ''));
            update_option('gymlite_license_key', sanitize_text_field($_POST['gymlite_license_key'] ?? ''));
            update_option('gymlite_enable_premium_mode', isset($_POST['gymlite_enable_premium_mode']) ? 'yes' : 'no');
            update_option('gymlite_zoom_api_key', sanitize_text_field($_POST['gymlite_zoom_api_key'] ?? ''));
            update_option('gymlite_zoom_api_secret', sanitize_text_field($_POST['gymlite_zoom_api_secret'] ?? ''));
            echo '<div class="updated"><p>' . __('Settings saved.', 'gymlite') . '</p></div>';
        }

        $stripe_key = get_option('gymlite_stripe_key', '');
        $license_key = get_option('gymlite_license_key', '');
        $enable_premium_mode = get_option('gymlite_enable_premium_mode', 'no');
        $zoom_api_key = get_option('gymlite_zoom_api_key', '');
        $zoom_api_secret = get_option('gymlite_zoom_api_secret', '');
        ?>
        <div class="wrap">
            <h1><?php _e('GymLite Settings', 'gymlite'); ?></h1>
            <form method="post" class="gymlite-settings-form">
                <?php wp_nonce_field('gymlite_settings_save', 'gymlite_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="gymlite_stripe_key"><?php _e('Stripe API Key (Premium)', 'gymlite'); ?></label></th>
                        <td><input type="text" name="gymlite_stripe_key" value="<?php echo esc_attr($stripe_key); ?>" class="regular-text" placeholder="sk_test_..."><p class="description"><?php _e('For automated billing and POS.', 'gymlite'); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="gymlite_license_key"><?php _e('License Key (Premium)', 'gymlite'); ?></label></th>
                        <td><input type="text" name="gymlite_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="Enter your license key"><p class="description"><?php _e('Unlock premium features.', 'gymlite'); ?> <a href="https://example.com/gymlite-pro" target="_blank"><?php _e('Get Pro', 'gymlite'); ?></a></p></td>
                    </tr>
                    <tr>
                        <th><label for="gymlite_zoom_api_key"><?php _e('Zoom API Key (Premium)', 'gymlite'); ?></label></th>
                        <td><input type="text" name="gymlite_zoom_api_key" value="<?php echo esc_attr($zoom_api_key); ?>" class="regular-text"><p class="description"><?php _e('For virtual class integration.', 'gymlite'); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="gymlite_zoom_api_secret"><?php _e('Zoom API Secret (Premium)', 'gymlite'); ?></label></th>
                        <td><input type="text" name="gymlite_zoom_api_secret" value="<?php echo esc_attr($zoom_api_secret); ?>" class="regular-text"><p class="description"><?php _e('Zoom API secret for integration.', 'gymlite'); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php _e('Enable Premium Mode (Testing)', 'gymlite'); ?></th>
                        <td><label><input type="checkbox" name="gymlite_enable_premium_mode" value="yes" <?php checked($enable_premium_mode, 'yes'); ?>> <?php _e('Unlock premium without license.', 'gymlite'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function run_daily_tasks() {
        gymlite_log("Starting daily tasks at " . current_time('Y-m-d H:i:s'));
        if (class_exists('GymLite_Admin') && method_exists('GymLite_Admin', 'billing')) {
            GymLite_Admin::billing();
        }
        if (class_exists('GymLite_Admin') && method_exists('GymLite_Admin', 'send_daily_notifications')) {
            GymLite_Admin::send_daily_notifications();
        }
        $this->check_overdues();
        if (class_exists('GymLite_Premium')) {
            if (method_exists('GymLite_Premium', 'google_calendar_sync')) {
                GymLite_Premium::google_calendar_sync();
            }
            if (method_exists('GymLite_Premium', 'zoom_integration')) {
                GymLite_Premium::zoom_integration();
            }
        }
        gymlite_log("Completed daily tasks at " . current_time('Y-m-d H:i:s'));
    }

    private function check_overdues() {
        global $wpdb;
        if (isset($wpdb->prefix)) {
            $table_name = $wpdb->prefix . 'gymlite_recurring';
            $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
            gymlite_log("Checked for overdue payments, updated $updated records at " . current_time('Y-m-d H:i:s'));
        }
    }

    private function manual_activate() {
        try {
            if (class_exists('GymLite_Install')) {
                GymLite_Install::activate();
                update_option('gymlite_activated', time());
                gymlite_log("Manual activation completed at " . current_time('Y-m-d H:i:s'));
                echo '<div class="notice notice-success"><p>' . __('GymLite activated manually. Tables and pages created.', 'gymlite') . '</p></div>';
            } else {
                throw new Exception('GymLite_Install class not found for manual activation.');
            }
        } catch (Exception $e) {
            gymlite_log("Manual activation failed: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            echo '<div class="notice notice-error"><p>' . __('Manual activation failed: ', 'gymlite') . esc_html($e->getMessage()) . '</p></div>';
        }
    }
}

// Init plugin
if (class_exists('GymLite')) {
    GymLite::init();
} else {
    gymlite_log("GymLite class not defined at " . current_time('Y-m-d H:i:s'));
}