<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Login {
    public function __construct() {
        try {
            gymlite_log("GymLite_Login constructor started at " . current_time('Y-m-d H:i:s'));
            add_shortcode('gymlite_login', [$this, 'login_shortcode']);
            add_action('wp_ajax_nopriv_gymlite_login', [$this, 'handle_login']);
            add_action('wp_ajax_gymlite_logout', [$this, 'handle_logout']);
            gymlite_log("GymLite_Login constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Login: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function login_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p class="uk-text-success">' . __('You are already logged in.', 'gymlite') . '</p><a href="' . wp_logout_url() . '" class="uk-button uk-button-secondary">' . __('Logout', 'gymlite') . '</a>';
        }
        ob_start();
        ?>
        <div class="gymlite-login uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Login', 'gymlite'); ?></h2>
                <form id="gymlite-login-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="username"><?php _e('Username or Email', 'gymlite'); ?></label>
                        <input class="uk-input" type="text" name="username" id="username" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="password"><?php _e('Password', 'gymlite'); ?></label>
                        <input class="uk-input" type="password" name="password" id="password" required>
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Login', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_login', 'nonce'); ?>
                </form>
                <p class="uk-text-center"><a href="<?php echo get_permalink(get_option('gymlite_signup_page_id')); ?>"><?php _e('Sign up', 'gymlite'); ?></a> | <a href="<?php echo wp_lostpassword_url(); ?>"><?php _e('Forgot password?', 'gymlite'); ?></a></p>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-login-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_login',
                            username: $('#username').val(),
                            password: $('#password').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_login() {
        check_ajax_referer('gymlite_login', 'nonce');
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password']; // Password is not sanitized
        if (empty($username) || empty($password)) {
            wp_send_json_error(['message' => __('Username and password are required.', 'gymlite')]);
        }
        $creds = ['user_login' => $username, 'user_password' => $password, 'remember' => true];
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) {
            wp_send_json_error(['message' => $user->get_error_message()]);
        }
        gymlite_log("User logged in: $username (ID: {$user->ID})");
        wp_send_json_success(['message' => __('Login successful! Redirecting...', 'gymlite')]);
    }

    public function handle_logout() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        wp_logout();
        gymlite_log("User logged out");
        wp_send_json_success(['message' => __('Logged out successfully.', 'gymlite')]);
    }
}
?>