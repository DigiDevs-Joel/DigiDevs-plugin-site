<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * File: includes/class-gymlite-user-data.php
 * Description: Handles a dedicated page for collecting and updating user data (member profile editing).
 */
class GymLite_User_Data {
    public function __construct() {
        try {
            add_shortcode('gymlite_user_data', [$this, 'user_data_shortcode']);
            add_action('wp_ajax_gymlite_update_user_data', [$this, 'handle_update_user_data']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_User_Data: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function user_data_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to update your data.', 'gymlite') . '</p>';
        }

        $user_id = get_current_user_id();
        $member_posts = get_posts([
            'post_type'   => 'gymlite_member',
            'author'      => $user_id,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (empty($member_posts) || is_wp_error($member_posts)) {
            return '<p class="uk-text-warning">' . __('No member profile found. Please contact support.', 'gymlite') . '</p>';
        }

        $member_id = $member_posts[0]->ID;
        $name = get_the_title($member_id);
        $email = get_post_meta($member_id, '_gymlite_member_email', true);
        $phone = get_post_meta($member_id, '_gymlite_member_phone', true) ?: '';
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';

        ob_start();
        ?>
        <div class="gymlite-user-data-section uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium"><?php _e('Update Your Profile', 'gymlite'); ?></h2>
                <form id="gymlite-user-data-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="name"><?php _e('Full Name', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="text" name="name" id="name" value="<?php echo esc_attr($name); ?>" required>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="email"><?php _e('Email', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="email" name="email" id="email" value="<?php echo esc_attr($email); ?>" required>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="phone"><?php _e('Phone', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="tel" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>">
                        </div>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="membership_type"><?php _e('Membership Type', 'gymlite'); ?></label>
                        <div class="uk-form-controls">
                            <select class="uk-select" name="membership_type" id="membership_type" required>
                                <option value="trial" <?php selected($membership_type, 'trial'); ?>><?php _e('Trial', 'gymlite'); ?></option>
                                <option value="basic" <?php selected($membership_type, 'basic'); ?>><?php _e('Basic', 'gymlite'); ?></option>
                                <option value="premium" <?php selected($membership_type, 'premium'); ?>><?php _e('Premium', 'gymlite'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_update_user_data', 'nonce'); ?>
                    <input type="hidden" name="member_id" value="<?php echo esc_attr($member_id); ?>">
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_update_user_data() {
        check_ajax_referer('gymlite_update_user_data', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to update data.', 'gymlite')]);
        }

        $member_id = intval($_POST['member_id']);
        $user_id = get_current_user_id();
        $member = get_post($member_id);

        if (!$member || $member->post_author != $user_id) {
            wp_send_json_error(['message' => __('Unauthorized to update this profile.', 'gymlite')]);
        }

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);

        $update_result = wp_update_post([
            'ID' => $member_id,
            'post_title' => $name,
        ]);

        if (is_wp_error($update_result)) {
            wp_send_json_error(['message' => __('Failed to update profile.', 'gymlite')]);
        }

        update_post_meta($member_id, '_gymlite_member_email', $email);
        update_post_meta($member_id, '_gymlite_member_phone', $phone);
        update_post_meta($member_id, '_gymlite_membership_type', $membership_type);

        if ($email !== get_userdata($user_id)->user_email) {
            wp_update_user(['ID' => $user_id, 'user_email' => $email]);
        }

        gymlite_log("User ID $user_id updated member profile ID $member_id at " . current_time('Y-m-d H:i:s'));

        wp_send_json_success(['message' => __('Profile updated successfully!', 'gymlite')]);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('gymlite-user-data-script', GYMLITE_URL . 'assets/js/user-data.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
        wp_localize_script('gymlite-user-data-script', 'gymlite_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gymlite_update_user_data'),
        ]);
    }
}