<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Frontend {
    public function __construct() {
        try {
            gymlite_log("GymLite_Frontend constructor started at " . current_time('Y-m-d H:i:s'));
            // In modular setup, most shortcodes and AJAX are moved to feature classes
            // This base class handles shared frontend logic, like global enqueues and common hooks
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
            add_action('init', [$this, 'register_shared_shortcodes']);
            add_action('wp_ajax_gymlite_shared_frontend_action', [$this, 'handle_shared_frontend_action']);
            add_action('wp_ajax_nopriv_gymlite_shared_frontend_action', [$this, 'handle_shared_frontend_action']);
            gymlite_log("GymLite_Frontend constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Frontend: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function enqueue_frontend_scripts() {
    wp_enqueue_style('uikit', GYMLITE_URL . 'assets/css/uikit.min.css', [], GYMLITE_VERSION);
    wp_enqueue_script('uikit', GYMLITE_URL . 'assets/js/uikit.min.js', ['jquery'], GYMLITE_VERSION, true);
    wp_enqueue_script('uikit-icons', GYMLITE_URL . 'assets/js/uikit-icons.min.js', ['uikit'], GYMLITE_VERSION, true);
    wp_enqueue_style('gymlite-frontend', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION); // Fixed path to match your file
    wp_enqueue_script('gymlite-frontend', GYMLITE_URL . 'assets/js/frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
    wp_localize_script('gymlite-frontend', 'gymlite_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gymlite_nonce'),
    ]);
    gymlite_log("Frontend scripts and styles enqueued.");
	wp_enqueue_script('gymlite-login', GYMLITE_URL . 'assets/js/login.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
}

    public function register_shared_shortcodes() {
        // Register any shortcodes that are not feature-specific, e.g., a global dashboard or utility
        add_shortcode('gymlite_dashboard', [$this, 'dashboard_shortcode']);
        gymlite_log("Shared shortcodes registered.");
    }

    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to access the dashboard.', 'gymlite') . '</p>';
        }
        ob_start();
        ?>
        <div class="gymlite-dashboard uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Your GymLite Dashboard', 'gymlite'); ?></h2>
                <div class="uk-grid-match uk-child-width-1-3@m" uk-grid>
                    <div>
                        <div class="uk-card uk-card-default uk-card-body">
                            <h3 class="uk-card-title"><?php _e('Profile', 'gymlite'); ?></h3>
                            <p><?php _e('View and update your profile.', 'gymlite'); ?></p>
                            <a href="<?php echo get_permalink(get_option('gymlite_update_profile_page_id')); ?>" class="uk-button uk-button-primary"><?php _e('Go to Profile', 'gymlite'); ?></a>
                        </div>
                    </div>
                    <div>
                        <div class="uk-card uk-card-default uk-card-body">
                            <h3 class="uk-card-title"><?php _e('Classes', 'gymlite'); ?></h3>
                            <p><?php _e('View schedule and book classes.', 'gymlite'); ?></p>
                            <a href="#" class="uk-button uk-button-primary"><?php _e('View Schedule', 'gymlite'); ?></a> <!-- Link to scheduling feature page -->
                        </div>
                    </div>
                    <div>
                        <div class="uk-card uk-card-default uk-card-body">
                            <h3 class="uk-card-title"><?php _e('Payments', 'gymlite'); ?></h3>
                            <p><?php _e('Manage billing and payments.', 'gymlite'); ?></p>
                            <a href="#" class="uk-button uk-button-primary"><?php _e('View Billing', 'gymlite'); ?></a> <!-- Link to billing feature page -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_shared_frontend_action() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        // Handle any shared AJAX requests not tied to specific features, e.g., global search or utility
        $action_type = sanitize_text_field($_POST['action_type']);
        if ($action_type === 'search') {
            $query = sanitize_text_field($_POST['query']);
            // Example: Search across features (members, classes, etc.)
            $results = []; // Query and populate
            wp_send_json_success(['results' => $results]);
        } else {
            wp_send_json_error(['message' => __('Invalid action type.', 'gymlite')]);
        }
    }
}
?>