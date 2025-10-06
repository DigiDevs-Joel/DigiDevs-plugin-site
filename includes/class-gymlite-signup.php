<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Signup {
    public function __construct() {
        try {
            gymlite_log("GymLite_Signup constructor started at " . current_time('Y-m-d H:i:s'));
            add_shortcode('gymlite_signup', [$this, 'signup_shortcode']);
            add_action('wp_ajax_nopriv_gymlite_signup', [$this, 'handle_signup']);
            add_action('wp_ajax_gymlite_signup', [$this, 'handle_signup']); // Allow for logged-in admins if needed
            gymlite_log("GymLite_Signup constructor completed at " . current_time('Y-m-d H:i:s'));
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
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Sign Up', 'gymlite'); ?></h2>
                <form id="gymlite-signup-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="name"><?php _e('Full Name', 'gymlite'); ?></label>
                        <input class="uk-input" type="text" name="name" id="name" required placeholder="<?php _e('Enter your full name', 'gymlite'); ?>">
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="email"><?php _e('Email Address', 'gymlite'); ?></label>
                        <input class="uk-input" type="email" name="email" id="email" required placeholder="<?php _e('Enter your email', 'gymlite'); ?>">
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="phone"><?php _e('Phone Number', 'gymlite'); ?></label>
                        <input class="uk-input" type="tel" name="phone" id="phone" placeholder="<?php _e('Enter your phone (optional)', 'gymlite'); ?>">
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="membership_type"><?php _e('Membership Type', 'gymlite'); ?></label>
                        <select class="uk-select" name="membership_type" id="membership_type" required>
                            <option value=""><?php _e('Select membership', 'gymlite'); ?></option>
                            <option value="trial"><?php _e('Trial', 'gymlite'); ?></option>
                            <option value="basic"><?php _e('Basic', 'gymlite'); ?></option>
                            <option value="premium"><?php _e('Premium', 'gymlite'); ?></option>
                        </select>
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary uk-width-1-1"><?php _e('Sign Up Now', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_signup', 'nonce'); ?>
                </form>
                <p class="uk-text-center uk-margin-top"><a href="<?php echo get_permalink(get_option('gymlite_login_page_id')); ?>"><?php _e('Already have an account? Login', 'gymlite'); ?></a></p>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-signup-form').on('submit', function(e) {
                    e.preventDefault();
                    var formData = $(this).serialize();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: 'action=gymlite_signup&' + formData,
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                window.location.href = '<?php echo get_permalink(get_option('gymlite_login_page_id')); ?>';
                            } else {
                                alert(response.data.message);
                            }
                        },
                        error: function() {
                            alert('<?php _e('An error occurred. Please try again.', 'gymlite'); ?>');
                        }
                    });
                });
            });
        </script>
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

        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already registered. Please login instead.', 'gymlite')]);
        }

        if (!in_array($membership_type, ['trial', 'basic', 'premium'])) {
            wp_send_json_error(['message' => __('Invalid membership type selected.', 'gymlite')]);
        }

        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => __('User creation failed: ' . $user_id->get_error_message(), 'gymlite')]);
        }

        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        update_user_meta($user_id, 'first_name', $name); // Assuming name is first name; adjust if full name

        $member_id = wp_insert_post([
            'post_title' => $name,
            'post_type' => 'gymlite_member',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);

        if (is_wp_error($member_id)) {
            wp_delete_user($user_id);
            wp_send_json_error(['message' => __('Member profile creation failed.', 'gymlite')]);
        }

        update_post_meta($member_id, '_gymlite_member_email', $email);
        update_post_meta($member_id, '_gymlite_member_phone', $phone);
        update_post_meta($member_id, '_gymlite_membership_type', $membership_type);

        // Send welcome email with password
        $message = sprintf(__('Welcome to GymLite! Your username is %s and password is %s. Please login and change your password.', 'gymlite'), $email, $password);
        wp_mail($email, __('Your GymLite Account', 'gymlite'), $message);

        gymlite_log("New user signed up: ID $user_id, Member ID $member_id, Type: $membership_type");

        wp_send_json_success(['message' => __('Signup successful! Check your email for login details.', 'gymlite')]);
    }
}
?>