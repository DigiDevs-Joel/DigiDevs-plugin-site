<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * File: includes/class-gymlite-login.php
 * Description: Handles login functionality for GymLite plugin, integrated with WordPress users.
 */
class GymLite_Login {
    public function __construct() {
        try {
            add_shortcode('gymlite_login', [$this, 'login_shortcode']);
            add_action('wp_ajax_gymlite_login', [$this, 'handle_login']);
            add_action('wp_ajax_nopriv_gymlite_login', [$this, 'handle_login']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Login: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function login_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p class="uk-text-success">' . __('You are already logged in.', 'gymlite') . '</p>';
        }

        ob_start();
        ?>
        <div class="gymlite-login-section uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium"><?php _e('Member Login', 'gymlite'); ?></h2>
                <form id="gymlite-login-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="username"><?php _e('Username or Email', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="text" name="username" id="username" required>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="password"><?php _e('Password', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="password" name="password" id="password" required>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Login', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_login', 'nonce'); ?>
                </form>
                <p class="uk-text-meta"><?php _e('Don\'t have an account? ', 'gymlite'); ?><a href="<?php echo esc_url(get_permalink(get_option('gymlite_signup_page_id'))); ?>"><?php _e('Sign up here', 'gymlite'); ?></a>.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_login() {
        check_ajax_referer('gymlite_login', 'nonce');

        $creds = [
            'user_login'    => sanitize_text_field($_POST['username']),
            'user_password' => $_POST['password'],
            'remember'      => true,
        ];

        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => $user->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Logged in successfully! Redirecting...', 'gymlite'), 'redirect' => esc_url(get_permalink(get_option('gymlite_portal_page_id')))]);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('gymlite-login-script', GYMLITE_URL . 'assets/js/login.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
        wp_localize_script('gymlite-login-script', 'gymlite_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gymlite_login'),
        ]);
    }
}