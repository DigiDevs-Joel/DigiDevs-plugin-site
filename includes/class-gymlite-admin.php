<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Admin {
    public function __construct() {
        try {
            gymlite_log("GymLite_Admin constructor started at " . current_time('Y-m-d H:i:s'));
            // In modular setup, most admin menus, meta boxes, and AJAX are moved to feature classes
            // This base class handles shared admin logic, like global dashboard, settings, and common hooks
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_shared_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
            add_action('wp_ajax_gymlite_shared_admin_action', [$this, 'handle_shared_admin_action']);
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
        // Feature-specific submenus are added in their respective classes, e.g., Member Management adds 'Members'
    }

    public function register_shared_settings() {
        // Register settings that are not feature-specific, e.g., global plugin options
        register_setting('gymlite_general', 'gymlite_site_wide_option_example');
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_style('uikit-admin', GYMLITE_URL . 'assets/css/uikit.min.css', [], GYMLITE_VERSION);
        wp_enqueue_script('uikit-admin', GYMLITE_URL . 'assets/js/uikit.min.js', ['jquery'], GYMLITE_VERSION, true);
        wp_enqueue_script('uikit-icons-admin', GYMLITE_URL . 'assets/js/uikit-icons.min.js', ['uikit-admin'], GYMLITE_VERSION, true);
        wp_enqueue_style('gymlite-admin-style', GYMLITE_URL . 'assets/css/admin.css', [], GYMLITE_VERSION);
        wp_enqueue_script('gymlite-admin-script', GYMLITE_URL . 'assets/js/admin.js', ['jquery', 'uikit-admin'], GYMLITE_VERSION, true);
        wp_localize_script('gymlite-admin-script', 'gymlite_admin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gymlite_nonce'),
        ]);
        gymlite_log("Admin scripts and styles enqueued.");
    }

    public function dashboard_page() {
        global $wpdb;
        // Gather summary data from features
        $total_members = wp_count_posts('gymlite_member')->publish;
        $total_classes = wp_count_posts('gymlite_class')->publish;
        $recent_leads = $wpdb->get_results("SELECT COUNT(*) as count FROM {$wpdb->prefix}gymlite_leads WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $leads_count = $recent_leads ? $recent_leads[0]->count : 0;

        ob_start();
        ?>
        <div class="wrap gymlite-dashboard">
            <h1><?php _e('GymLite Dashboard', 'gymlite'); ?></h1>
            <div class="uk-grid-match uk-child-width-1-4@m" uk-grid>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3 class="uk-card-title"><?php _e('Total Members', 'gymlite'); ?></h3>
                        <p class="uk-text-large"><?php echo esc_html($total_members); ?></p>
                    </div>
                </div>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3 class="uk-card-title"><?php _e('Total Classes', 'gymlite'); ?></h3>
                        <p class="uk-text-large"><?php echo esc_html($total_classes); ?></p>
                    </div>
                </div>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3 class="uk-card-title"><?php _e('Recent Leads (7 days)', 'gymlite'); ?></h3>
                        <p class="uk-text-large"><?php echo esc_html($leads_count); ?></p>
                    </div>
                </div>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3 class="uk-card-title"><?php _e('Plugin Version', 'gymlite'); ?></h3>
                        <p class="uk-text-large"><?php echo esc_html(GYMLITE_VERSION); ?></p>
                    </div>
                </div>
            </div>
            <hr class="uk-divider-icon">
            <h2><?php _e('Quick Links', 'gymlite'); ?></h2>
            <ul class="uk-list uk-list-divider">
                <li><a href="<?php echo admin_url('edit.php?post_type=gymlite_member'); ?>"><?php _e('Manage Members', 'gymlite'); ?></a></li>
                <li><a href="<?php echo admin_url('edit.php?post_type=gymlite_class'); ?>"><?php _e('Manage Classes', 'gymlite'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=gymlite-settings'); ?>"><?php _e('Plugin Settings', 'gymlite'); ?></a></li>
                <!-- Add more as features are loaded -->
            </ul>
        </div>
        <?php
        echo ob_get_clean();
    }

    public function handle_shared_admin_action() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $action_type = sanitize_text_field($_POST['action_type']);
        if ($action_type === 'get_stats') {
            // Example: Return dashboard stats via AJAX for dynamic updates
            $stats = [
                'members' => wp_count_posts('gymlite_member')->publish,
                'classes' => wp_count_posts('gymlite_class')->publish,
            ];
            wp_send_json_success(['stats' => $stats]);
        } else {
            wp_send_json_error(['message' => __('Invalid action type.', 'gymlite')]);
        }
    }
}
?>