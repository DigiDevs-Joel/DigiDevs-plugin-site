<?php
/*
Plugin Name: GymLite - Gym Management
Description: A WordPress plugin with all functions and features of Gymdesk: member management, billing, POS, scheduling, attendance, marketing, waivers, access control, progression tracking, and more.
Version: 1.7.0
Author: DigiDevs
License: GPL-2.0+
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('GYMLITE_VERSION', '1.7.0');
define('GYMLITE_DIR', plugin_dir_path(__FILE__));
define('GYMLITE_URL', plugin_dir_url(__FILE__));

// Debug logging function
function gymlite_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG && is_writable(WP_CONTENT_DIR)) {
        if (defined('WP_DEBUG_LOG')) {
            $log_file = is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
            if (!file_exists($log_file)) {
                touch($log_file);
                chmod($log_file, 0664);
            }
            error_log(date('[Y-m-d H:i:s] ') . 'GymLite: ' . $message . "\n", 3, $log_file);
        }
    }
}

// Include core class files with existence checks
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

// Explicitly instantiate core classes
if (class_exists('GymLite_Frontend')) new GymLite_Frontend();
if (class_exists('GymLite_Admin')) new GymLite_Admin();
if (class_exists('GymLite_Premium')) new GymLite_Premium();
if (class_exists('GymLite_Login')) new GymLite_Login();
if (class_exists('GymLite_Signup')) new GymLite_Signup();
if (class_exists('GymLite_User_Data')) new GymLite_User_Data();

// Activation hook with buffered output
register_activation_hook(__FILE__, function () {
    ob_start();
    try {
        if (class_exists('GymLite_Install')) {
            GymLite_Install::activate();
            update_option('gymlite_activated', time());
            gymlite_log("Plugin activated successfully at " . current_time('Y-m-d H:i:s'));
        } else {
            throw new Exception('GymLite_Install class not found.');
        }
    } catch (Exception $e) {
        gymlite_log("Activation failed: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
    }
    ob_end_clean();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('gymlite_daily_cron');
    delete_option('gymlite_activated');
    gymlite_log("Plugin deactivated at " . current_time('Y-m-d H:i:s'));
});

// Load modular features
function gymlite_load_features() {
    $features_dir = GYMLITE_DIR . 'includes/features/';
    $features = [
        'class-member-management.php',
        'class-billing.php',
        'class-pos.php',
        'class-scheduling.php',
        'class-attendance.php',
        'class-marketing.php',
        'class-waivers.php',
        'class-access-control.php',
        'class-progression.php',
        'class-reporting.php',
    ];

    foreach ($features as $feature) {
        $file = $features_dir . $feature;
        if (file_exists($file)) {
            require_once $file;
            // Dynamically instantiate the class (e.g., GymLite_Member_Management)
            $class_name = 'GymLite_' . str_replace(['class-', '.php'], ['', ''], ucwords($feature, '-'));
            $class_name = str_replace('-', '_', $class_name);
            if (class_exists($class_name)) {
                new $class_name();
                gymlite_log("$class_name feature loaded.");
            }
        } else {
            gymlite_log("Missing feature file: $file");
        }
    }
}
add_action('plugins_loaded', 'gymlite_load_features');

// Initialize core plugin logic
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
    }

    public function enqueue_scripts() {
        wp_enqueue_style('uikit', GYMLITE_URL . 'assets/css/uikit.min.css', [], GYMLITE_VERSION);
        wp_enqueue_script('uikit', GYMLITE_URL . 'assets/js/uikit.min.js', ['jquery'], GYMLITE_VERSION, true);
        wp_enqueue_script('uikit-icons', GYMLITE_URL . 'assets/js/uikit-icons.min.js', ['uikit'], GYMLITE_VERSION, true);
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_style('gymlite-admin', GYMLITE_URL . 'assets/css/admin.css', [], GYMLITE_VERSION);
        wp_enqueue_script('gymlite-admin', GYMLITE_URL . 'assets/js/admin.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
        wp_localize_script('gymlite-admin', 'gymlite_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gymlite_nonce'),
        ]);
    }

    public function add_settings_menu() {
        add_submenu_page(
            'gymlite',
            __('Settings', 'gymlite'),
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
        // Call feature-specific tasks if needed, e.g., GymLite_Billing::process_daily();
        $this->check_overdues();
        gymlite_log("Completed daily tasks at " . current_time('Y-m-d H:i:s'));
    }

    private function check_overdues() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        gymlite_log("Checked overdues, updated $updated records");
    }

    private function manual_activate() {
        try {
            if (class_exists('GymLite_Install')) {
                GymLite_Install::activate();
                update_option('gymlite_activated', time());
                gymlite_log("Manual activation successful");
            }
        } catch (Exception $e) {
            gymlite_log("Manual activation failed: " . $e->getMessage());
        }
    }
}

if (class_exists('GymLite')) {
    GymLite::init();
} else {
    gymlite_log("GymLite core class not found");
}
?>