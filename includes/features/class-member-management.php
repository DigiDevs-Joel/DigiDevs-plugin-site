<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Member_Management {
    public function __construct() {
        try {
            gymlite_log("GymLite_Member_Management feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post_gymlite_member', [$this, 'save_meta']);
            add_shortcode('gymlite_member_profile', [$this, 'member_profile_shortcode']);
            add_shortcode('gymlite_portal', [$this, 'portal_shortcode']);
            add_action('wp_ajax_gymlite_update_member', [$this, 'handle_update_member']);
            add_action('wp_ajax_gymlite_get_members', [$this, 'handle_get_members']);
            gymlite_log("GymLite_Member_Management feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Member_Management: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Members', 'gymlite'),
            __('Members', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_member'
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'gymlite_member_details',
            __('Member Details', 'gymlite'),
            [$this, 'member_meta_box'],
            'gymlite_member',
            'normal',
            'high'
        );
    }

    public function member_meta_box($post) {
        wp_nonce_field('gymlite_member_meta', 'gymlite_member_nonce');
        $email = get_post_meta($post->ID, '_gymlite_member_email', true);
        $phone = get_post_meta($post->ID, '_gymlite_member_phone', true);
        $membership_type = get_post_meta($post->ID, '_gymlite_membership_type', true);
        $address = get_post_meta($post->ID, '_gymlite_member_address', true);
        $notes = get_post_meta($post->ID, '_gymlite_member_notes', true);
        ?>
        <p>
            <label for="gymlite_member_email"><?php _e('Email', 'gymlite'); ?></label>
            <input type="email" id="gymlite_member_email" name="gymlite_member_email" value="<?php echo esc_attr($email); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_member_phone"><?php _e('Phone', 'gymlite'); ?></label>
            <input type="tel" id="gymlite_member_phone" name="gymlite_member_phone" value="<?php echo esc_attr($phone); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_membership_type"><?php _e('Membership Type', 'gymlite'); ?></label>
            <select id="gymlite_membership_type" name="gymlite_membership_type" class="widefat">
                <option value="trial" <?php selected($membership_type, 'trial'); ?>><?php _e('Trial', 'gymlite'); ?></option>
                <option value="basic" <?php selected($membership_type, 'basic'); ?>><?php _e('Basic', 'gymlite'); ?></option>
                <option value="premium" <?php selected($membership_type, 'premium'); ?>><?php _e('Premium', 'gymlite'); ?></option>
            </select>
        </p>
        <p>
            <label for="gymlite_member_address"><?php _e('Address', 'gymlite'); ?></label>
            <textarea id="gymlite_member_address" name="gymlite_member_address" class="widefat"><?php echo esc_textarea($address); ?></textarea>
        </p>
        <p>
            <label for="gymlite_member_notes"><?php _e('Notes', 'gymlite'); ?></label>
            <textarea id="gymlite_member_notes" name="gymlite_member_notes" class="widefat"><?php echo esc_textarea($notes); ?></textarea>
        </p>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['gymlite_member_nonce']) || !wp_verify_nonce($_POST['gymlite_member_nonce'], 'gymlite_member_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_gymlite_member_email', sanitize_email($_POST['gymlite_member_email']));
        update_post_meta($post_id, '_gymlite_member_phone', sanitize_text_field($_POST['gymlite_member_phone']));
        update_post_meta($post_id, '_gymlite_membership_type', sanitize_text_field($_POST['gymlite_membership_type']));
        update_post_meta($post_id, '_gymlite_member_address', sanitize_textarea_field($_POST['gymlite_member_address']));
        update_post_meta($post_id, '_gymlite_member_notes', sanitize_textarea_field($_POST['gymlite_member_notes']));
        gymlite_log("Member meta saved for post ID $post_id");
    }

    public function member_profile_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Please log in to view your profile.', 'gymlite') . '</p>';
        $user_id = get_current_user_id();
        $member_posts = get_posts([
            'post_type' => 'gymlite_member',
            'author' => $user_id,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        if (empty($member_posts)) return '<p class="uk-text-warning">' . __('No profile found.', 'gymlite') . '</p>';
        $member = $member_posts[0];
        $name = $member->post_title;
        $email = get_post_meta($member->ID, '_gymlite_member_email', true);
        $phone = get_post_meta($member->ID, '_gymlite_member_phone', true);
        $membership_type = get_post_meta($member->ID, '_gymlite_membership_type', true);
        $address = get_post_meta($member->ID, '_gymlite_member_address', true);

        ob_start();
        ?>
        <div class="gymlite-member-profile uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Your Profile', 'gymlite'); ?></h2>
                <ul class="uk-list uk-list-divider">
                    <li><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($name); ?></li>
                    <li><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html($email); ?></li>
                    <li><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html($phone); ?></li>
                    <li><strong><?php _e('Membership Type:', 'gymlite'); ?></strong> <?php echo esc_html(ucfirst($membership_type)); ?></li>
                    <li><strong><?php _e('Address:', 'gymlite'); ?></strong> <?php echo esc_html($address); ?></li>
                </ul>
                <a href="<?php echo get_permalink(get_option('gymlite_update_profile_page_id')); ?>" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function portal_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Please log in to access the member portal.', 'gymlite') . '</p>';
        ob_start();
        ?>
        <div class="gymlite-member-portal uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Portal', 'gymlite'); ?></h2>
                <div class="uk-grid-match uk-child-width-1-3@m" uk-grid>
                    <div>
                        <div class="uk-card uk-card-default uk-card-body">
                            <h3 class="uk-card-title"><?php _e('Profile', 'gymlite'); ?></h3>
                            <a href="<?php echo get_permalink(get_option('gymlite_portal_page_id')); ?>" class="uk-button uk-button-secondary"><?php _e('View Profile', 'gymlite'); ?></a>
                        </div>
                    </div>
                    <div>
                        <div class="uk-card uk-card-default uk-card-body">
                            <h3 class="uk-card-title"><?php _e('Update Info', 'gymlite'); ?></h3>
                            <a href="<?php echo get_permalink(get_option('gymlite_update_profile_page_id')); ?>" class="uk-button uk-button-secondary"><?php _e('Update', 'gymlite'); ?></a>
                        </div>
                    </div>
                    <div>
                        <div class="uk-card uk-card-default uk-card-body">
                            <h3 class="uk-card-title"><?php _e('Membership', 'gymlite'); ?></h3>
                            <p><?php _e('Manage your membership details.', 'gymlite'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_update_member() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $member_id = intval($_POST['member_id']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        $address = sanitize_textarea_field($_POST['address']);
        $notes = sanitize_textarea_field($_POST['notes']);

        wp_update_post(['ID' => $member_id, 'post_title' => $name]);
        update_post_meta($member_id, '_gymlite_member_email', $email);
        update_post_meta($member_id, '_gymlite_member_phone', $phone);
        update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
        update_post_meta($member_id, '_gymlite_member_address', $address);
        update_post_meta($member_id, '_gymlite_member_notes', $notes);

        gymlite_log("Member updated via AJAX: ID $member_id");
        wp_send_json_success(['message' => __('Member updated successfully.', 'gymlite')]);
    }

    public function handle_get_members() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $args = [
            'post_type' => 'gymlite_member',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $members = get_posts($args);
        $data = [];
        foreach ($members as $member) {
            $data[] = [
                'id' => $member->ID,
                'name' => $member->post_title,
                'email' => get_post_meta($member->ID, '_gymlite_member_email', true),
                'phone' => get_post_meta($member->ID, '_gymlite_member_phone', true),
                'membership_type' => get_post_meta($member->ID, '_gymlite_membership_type', true),
            ];
        }
        gymlite_log("Members data retrieved via AJAX");
        wp_send_json_success(['members' => $data]);
    }
}
?>