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
                    <label class="uk-form-label" for="gymlite-username"><?php _e('Username or Email', 'gymlite'); ?></label>
                    <input class="uk-input" type="text" name="username" id="gymlite-username" required>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label" for="gymlite-password"><?php _e('Password', 'gymlite'); ?></label>
                    <input class="uk-input" type="password" name="password" id="gymlite-password" required>
                </div>
                <div class="uk-margin">
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Login', 'gymlite'); ?></button>
                </div>
                <?php wp_nonce_field('gymlite_login', 'nonce'); // Fixed nonce action to match handler ?>
            </form>
            <p class="uk-text-center"><a href="<?php echo get_permalink(get_option('gymlite_signup_page_id')); ?>"><?php _e('Sign up', 'gymlite'); ?></a> | <a href="<?php echo wp_lostpassword_url(); ?>"><?php _e('Forgot password?', 'gymlite'); ?></a></p>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#gymlite-login-form').on('submit', function(e) {
                e.preventDefault();
                var username = $('#gymlite-username').val();
                var password = $('#gymlite-password').val();
                var nonce = $('input[name="nonce"]').val();
                console.log('Submitting login with nonce:', nonce); // Debug: Check console for nonce value
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gymlite_login',
                        username: username,
                        password: password,
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('AJAX Success Response:', response); // Debug: Full response in console
                        if (response.success) {
                            UIkit.notification({message: response.data.message, status: 'success'});
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                window.location.reload(); // Fallback reload to show logged-in state
                            }
                        } else {
                            UIkit.notification({message: response.data.message || '<?php _e('Login failed. Check your credentials.', 'gymlite'); ?>', status: 'danger'});
                        }
                    },
                    error: function(xhr) {
                        console.log('AJAX Error:', xhr.responseText); // Debug: Error details in console
                        UIkit.notification({message: xhr.responseJSON?.data?.message || '<?php _e('An error occurred. Please try again.', 'gymlite'); ?>', status: 'danger'});
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
    $password = $_POST['password']; // Do not sanitize password
    if (empty($username) || empty($password)) {
        wp_send_json_error(['message' => __('Username and password are required.', 'gymlite')]);
    }
    $creds = ['user_login' => $username, 'user_password' => $password, 'remember' => true];
    $user = wp_signon($creds, false);
    if (is_wp_error($user)) {
        wp_send_json_error(['message' => $user->get_error_message()]);
    }
    gymlite_log("User logged in: $username (ID: {$user->ID})");
    // Add redirect to member portal or home
    $redirect_url = get_permalink(get_option('gymlite_portal_page_id')) ?: home_url();
    wp_send_json_success(['message' => __('Login successful! Redirecting...', 'gymlite'), 'redirect' => $redirect_url]);
}

    public function handle_logout() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        wp_logout();
        gymlite_log("User logged out");
        wp_send_json_success(['message' => __('Logged out successfully.', 'gymlite')]);
    }
}
?>