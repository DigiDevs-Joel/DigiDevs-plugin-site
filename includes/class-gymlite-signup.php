<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * File: includes/class-gymlite-signup.php
 * Description: Handles frontend signup functionality, creating WP user and linked gymlite_member post.
 */
class GymLite_Signup {
    public function __construct() {
        try {
            add_shortcode('gymlite_signup', [$this, 'signup_shortcode']);
            add_action('wp_ajax_gymlite_signup', [$this, 'handle_signup']);
            add_action('wp_ajax_nopriv_gymlite_signup', [$this, 'handle_signup']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Signup: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function signup_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p class="uk-text-warning">' . __('You are already signed up and logged in.', 'gymlite') . '</p>';
        }

        ob_start();
        ?>
        <div class="gymlite-signup-section uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium"><?php _e('Sign Up for GymLite', 'gymlite'); ?></h2>
                <form id="gymlite-signup-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="name"><?php _e('Full Name', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="text" name="name" id="name" required>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="email"><?php _e('Email', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="email" name="email" id="email" required>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="phone"><?php _e('Phone', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="tel" name="phone" id="phone">
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="membership_type"><?php _e('Membership Type', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <select class="uk-select" name="membership_type" id="membership_type" required>
                                <option value="trial"><?php _e('Trial', 'gymlite'); ?></option>
                                <option value="basic"><?php _e('Basic', 'gymlite'); ?></option>
                                <option value="premium"><?php _e('Premium', 'gymlite'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Sign Up', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_signup', 'nonce'); ?>
                </form>
                <p class="uk-text-meta"><?php _e('Already have an account? ', 'gymlite'); ?><a href="<?php echo esc_url(get_permalink(get_option('gymlite_login_page_id'))); ?>"><?php _e('Login here', 'gymlite'); ?></a>.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);

        if (empty($name) || empty($email) || empty($membership_type)) {
            wp_send_json_error(['message' => __('All required fields must be filled.', 'gymlite')]);
        }

        if (email_exists($email) || username_exists($email)) {
            wp_send_json_error(['message' => __('Email or username already exists.', 'gymlite')]);
        }

        $username = sanitize_user(str_replace(' ', '_', strtolower($name)));
        $password = wp_generate_password(12, true);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        wp_update_user(['ID' => $user_id, 'display_name' => $name, 'role' => 'gymlite_member']);

        $member_args = [
            'post_type'   => 'gymlite_member',
            'post_title'  => $name,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ];
        $member_id = wp_insert_post($member_args);

        if (is_wp_error($member_id)) {
            wp_delete_user($user_id);
            wp_send_json_error(['message' => __('Failed to create member profile.', 'gymlite')]);
        }

        update_post_meta($member_id, '_gymlite_member_email', $email);
        update_post_meta($member_id, '_gymlite_member_phone', $phone);
        update_post_meta($member_id, '_gymlite_membership_type', $membership_type);

        $reset_key = get_password_reset_key(get_user_by('id', $user_id));
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($username), 'login');
        $message = sprintf(__('Welcome to GymLite! Your username is %s. Set your password here: %s', 'gymlite'), $username, $reset_url);
        wp_mail($email, __('GymLite Account Created', 'gymlite'), $message);

        gymlite_log("New member signed up: User ID $user_id, Member ID $member_id at " . current_time('Y-m-d H:i:s'));

        wp_send_json_success(['message' => __('Signed up successfully! Check your email to set your password.', 'gymlite')]);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('gymlite-signup-script', GYMLITE_URL . 'assets/js/signup.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
        wp_localize_script('gymlite-signup-script', 'gymlite_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gymlite_signup'),
        ]);
    }
}