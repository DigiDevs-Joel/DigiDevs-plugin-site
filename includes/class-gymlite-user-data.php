<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_User_Data {
    public function __construct() {
        try {
            gymlite_log("GymLite_User_Data constructor started at " . current_time('Y-m-d H:i:s'));
            add_shortcode('gymlite_update_profile', [$this, 'update_profile_shortcode']);
            add_action('wp_ajax_gymlite_update_profile', [$this, 'handle_update_profile']);
            add_action('wp_ajax_gymlite_get_user_data', [$this, 'handle_get_user_data']);
            add_action('wp_ajax_gymlite_delete_user_data', [$this, 'handle_delete_user_data']);
            gymlite_log("GymLite_User_Data constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_User_Data: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function update_profile_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to update your profile.', 'gymlite') . '</p>';
        }
        $user_id = get_current_user_id();
        $member_posts = get_posts([
            'post_type' => 'gymlite_member',
            'author' => $user_id,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        if (empty($member_posts)) {
            return '<p class="uk-text-warning">' . __('No profile found. Contact support.', 'gymlite') . '</p>';
        }
        $member = $member_posts[0];
        $name = $member->post_title;
        $email = get_post_meta($member->ID, '_gymlite_member_email', true);
        $phone = get_post_meta($member->ID, '_gymlite_member_phone', true);

        ob_start();
        ?>
        <div class="gymlite-update-profile uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Update Profile', 'gymlite'); ?></h2>
                <form id="gymlite-update-profile-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="name"><?php _e('Full Name', 'gymlite'); ?></label>
                        <input class="uk-input" type="text" name="name" id="name" value="<?php echo esc_attr($name); ?>" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="email"><?php _e('Email', 'gymlite'); ?></label>
                        <input class="uk-input" type="email" name="email" id="email" value="<?php echo esc_attr($email); ?>" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="phone"><?php _e('Phone', 'gymlite'); ?></label>
                        <input class="uk-input" type="tel" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>">
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_update_profile', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-update-profile-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_update_profile',
                            name: $('#name').val(),
                            email: $('#email').val(),
                            phone: $('#phone').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
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

    public function handle_update_profile() {
        check_ajax_referer('gymlite_update_profile', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to update profile.', 'gymlite')]);
        }
        $user_id = get_current_user_id();
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);

        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }

        if ($email !== wp_get_current_user()->user_email && email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already in use.', 'gymlite')]);
        }

        $member_posts = get_posts([
            'post_type' => 'gymlite_member',
            'author' => $user_id,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (empty($member_posts)) {
            wp_send_json_error(['message' => __('No member profile found.', 'gymlite')]);
        }

        $member_id = $member_posts[0]->ID;
        wp_update_post([
            'ID' => $member_id,
            'post_title' => $name,
        ]);

        update_post_meta($member_id, '_gymlite_member_email', $email);
        update_post_meta($member_id, '_gymlite_member_phone', $phone);

        // Update WP user email if changed
        if ($email !== wp_get_current_user()->user_email) {
            wp_update_user(['ID' => $user_id, 'user_email' => $email]);
        }

        gymlite_log("Profile updated for user ID $user_id");

        wp_send_json_success(['message' => __('Profile updated successfully!', 'gymlite')]);
    }

    public function handle_get_user_data() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gymlite')]);
        }
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(['message' => __('User not found.', 'gymlite')]);
        }
        $member_posts = get_posts([
            'post_type' => 'gymlite_member',
            'author' => $user_id,
            'posts_per_page' => 1,
        ]);
        $member_data = $member_posts ? [
            'name' => $member_posts[0]->post_title,
            'email' => get_post_meta($member_posts[0]->ID, '_gymlite_member_email', true),
            'phone' => get_post_meta($member_posts[0]->ID, '_gymlite_member_phone', true),
            'membership_type' => get_post_meta($member_posts[0]->ID, '_gymlite_membership_type', true),
        ] : [];

        $data = [
            'user_id' => $user_id,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'member_data' => $member_data,
        ];

        gymlite_log("User data retrieved for ID $user_id");

        wp_send_json_success(['data' => $data]);
    }

    public function handle_delete_user_data() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gymlite')]);
        }
        $user_id = intval($_POST['user_id']);
        if ($user_id === get_current_user_id()) {
            wp_send_json_error(['message' => __('Cannot delete your own account.', 'gymlite')]);
        }
        $member_posts = get_posts([
            'post_type' => 'gymlite_member',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        foreach ($member_posts as $post) {
            wp_delete_post($post->ID, true);
        }
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
        gymlite_log("User data deleted for ID $user_id");
        wp_send_json_success(['message' => __('User data deleted successfully.', 'gymlite')]);
    }
}
?>