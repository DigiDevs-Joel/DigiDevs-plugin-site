<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Frontend {
    public function __construct() {
        try {
            gymlite_log("GymLite_Frontend constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('init', [$this, 'register_shortcodes']);
            add_action('wp_ajax_gymlite_checkin', [$this, 'handle_checkin']);
            add_action('wp_ajax_nopriv_gymlite_checkin', [$this, 'handle_checkin']);
            add_action('wp_ajax_gymlite_submit_lead', [$this, 'handle_lead_submission']);
            add_action('wp_ajax_nopriv_gymlite_submit_lead', [$this, 'handle_lead_submission']);
            add_action('wp_ajax_gymlite_signup', [$this, 'handle_signup']);
            add_action('wp_ajax_nopriv_gymlite_signup', [$this, 'handle_signup']);
            add_action('wp_ajax_gymlite_book_class', [$this, 'handle_booking']);
            add_action('wp_ajax_nopriv_gymlite_book_class', [$this, 'handle_booking']);
            add_action('wp_ajax_gymlite_sign_waiver', [$this, 'handle_sign_waiver']);
            add_action('wp_ajax_gymlite_log_access', [$this, 'handle_log_access']);
            add_action('wp_ajax_gymlite_promote_member', [$this, 'handle_promote']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
            gymlite_log("GymLite_Frontend constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Frontend: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function register_shortcodes() {
        try {
            gymlite_log("Registering shortcodes at " . current_time('Y-m-d H:i:s'));
            add_shortcode('gymlite_schedule', [$this, 'schedule_shortcode']);
            add_shortcode('gymlite_calendar', [$this, 'calendar_shortcode']);
            add_shortcode('gymlite_attendance_log', [$this, 'attendance_log_shortcode']);
            add_shortcode('gymlite_member_profile', [$this, 'member_profile_shortcode']);
            add_shortcode('gymlite_lead_form', [$this, 'lead_form_shortcode']);
            add_shortcode('gymlite_signup', [$this, 'signup_shortcode']);
            add_shortcode('gymlite_booking', [$this, 'booking_shortcode']);
            add_shortcode('gymlite_referrals', [$this, 'referrals_shortcode']);
            add_shortcode('gymlite_portal', [$this, 'portal_shortcode']);
            add_shortcode('gymlite_waivers', [$this, 'waivers_shortcode']);
            add_shortcode('gymlite_access_status', [$this, 'access_status_shortcode']);
            add_shortcode('gymlite_progression', [$this, 'progression_shortcode']);
            add_shortcode('gymlite_notifications', [$this, 'notifications_shortcode']);
            gymlite_log("All shortcodes registered at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error registering shortcodes: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function schedule_shortcode($atts) {
        try {
            gymlite_log("schedule_shortcode called at " . current_time('Y-m-d H:i:s'));
            $args = [
                'post_type' => 'gymlite_class',
                'posts_per_page' => -1,
                'meta_key' => '_gymlite_class_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'post_status' => 'publish',
            ];
            $classes = new WP_Query($args);
            if (is_wp_error($classes)) {
                throw new Exception('Failed to query classes: ' . $classes->get_error_message());
            }
            $now = new DateTime('now', new DateTimeZone('Australia/Sydney'));
            $is_premium = class_exists('GymLite_Premium') && (GymLite_Premium::is_premium_active() || get_option('gymlite_enable_premium_mode', 'no') === 'yes');
            $member_id = is_user_logged_in() ? get_current_user_id() : 0;

            ob_start();
            ?>
            <div class="gymlite-schedule uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Schedule', 'gymlite'); ?></h2>
                    <?php if ($classes->have_posts()) : ?>
                        <div class="uk-child-width-1-2@m uk-grid-small uk-grid-match" uk-grid>
                            <?php while ($classes->have_posts()) : $classes->the_post();
                                $class_id = get_the_ID();
                                $class_date_str = get_post_meta($class_id, '_gymlite_class_date', true);
                                $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true) ?: 60);
                                $instructor = get_post_meta($class_id, '_gymlite_class_instructor', true) ?: __('N/A', 'gymlite');

                                if (!$class_date_str) continue;

                                $start = new DateTime($class_date_str, new DateTimeZone('Australia/Sydney'));
                                $end = clone $start;
                                $end->add(new DateInterval("PT{$duration}M"));
                                $show_checkin = $is_premium && $member_id && ($now >= $start && $now <= $end);
                                $already_checked = false;

                                if ($show_checkin) {
                                    global $wpdb;
                                    $table_name = $wpdb->prefix . 'gymlite_attendance';
                                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
                                    $already_checked = $exists ? true : false;
                                    $show_checkin = !$already_checked;
                                }
                            ?>
                                <div>
                                    <div class="uk-card uk-card-default uk-card-body uk-border-rounded">
                                        <h3 class="uk-card-title uk-text-bold"><?php the_title(); ?></h3>
                                        <ul class="uk-list uk-list-divider">
                                            <li><strong><?php _e('Date:', 'gymlite'); ?></strong> <?php echo esc_html($start->format('Y-m-d')); ?></li>
                                            <li><strong><?php _e('Time:', 'gymlite'); ?></strong> <?php echo esc_html($start->format('H:i')) . ' - ' . $end->format('H:i'); ?></li>
                                            <li><strong><?php _e('Instructor:', 'gymlite'); ?></strong> <?php echo esc_html($instructor); ?></li>
                                            <li><strong><?php _e('Duration:', 'gymlite'); ?></strong> <?php echo esc_html($duration); ?> <?php _e('minutes', 'gymlite'); ?></li>
                                        </ul>
                                        <?php if ($show_checkin) : ?>
                                            <button class="uk-button uk-button-primary gymlite-checkin" data-class-id="<?php echo esc_attr($class_id); ?>" data-nonce="<?php echo wp_create_nonce('gymlite_checkin'); ?>">
                                                <?php _e('Check In', 'gymlite'); ?>
                                            </button>
                                        <?php elseif ($already_checked) : ?>
                                            <p class="uk-text-success"><?php _e('Checked in!', 'gymlite'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p><?php _e('No classes scheduled.', 'gymlite'); ?></p>
                    <?php endif; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        } catch (Exception $e) {
            gymlite_log("Error in schedule_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<p class="uk-text-danger">' . __('Error loading schedule.', 'gymlite') . '</p>';
        }
    }

    public function calendar_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-calendar uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                <!-- Placeholder for calendar implementation, e.g., using FullCalendar or similar -->
                <div id="gymlite-calendar"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function attendance_log_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to view attendance log.', 'gymlite') . '</p>';
        }
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $attendances = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE member_id = %d ORDER BY attendance_date DESC LIMIT 20", $member_id));

        ob_start();
        ?>
        <div class="gymlite-attendance-log uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Attendance Log', 'gymlite'); ?></h2>
                <?php if ($attendances) : ?>
                    <table class="uk-table uk-table-striped">
                        <thead>
                            <tr>
                                <th><?php _e('Class', 'gymlite'); ?></th>
                                <th><?php _e('Date', 'gymlite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendances as $att) : ?>
                                <tr>
                                    <td><?php echo esc_html(get_the_title($att->class_id)); ?></td>
                                    <td><?php echo esc_html($att->attendance_date); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('No attendance records found.', 'gymlite'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function member_profile_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to view profile.', 'gymlite'); ?></p>';
        }
        $user_id = get_current_user_id();
        $member_posts = get_posts([
            'post_type' => 'gymlite_member',
            'author' => $user_id,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (empty($member_posts)) {
            return '<p class="uk-text-warning">' . __('No profile found.', 'gymlite') . '</p>';
        }

        $member = $member_posts[0];
        ob_start();
        ?>
        <div class="gymlite-member-profile uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Profile', 'gymlite'); ?></h2>
                <p><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($member->post_title); ?></p>
                <p><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html(get_post_meta($member->ID, '_gymlite_member_email', true)); ?></p>
                <p><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html(get_post_meta($member->ID, '_gymlite_member_phone', true)); ?></p>
                <p><strong><?php _e('Membership:', 'gymlite'); ?></strong> <?php echo esc_html(ucfirst(get_post_meta($member->ID, '_gymlite_membership_type', true))); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function lead_form_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-lead-form uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Lead Form', 'gymlite'); ?></h2>
                <form id="gymlite-lead-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="name"><?php _e('Name', 'gymlite'); ?></label>
                        <input class="uk-input" type="text" name="name" id="name" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="email"><?php _e('Email', 'gymlite'); ?></label>
                        <input class="uk-input" type="email" name="email" id="email" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="phone"><?php _e('Phone', 'gymlite'); ?></label>
                        <input class="uk-input" type="tel" name="phone" id="phone">
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit Lead', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_submit_lead', 'nonce'); ?>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function signup_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p class="uk-text-warning">' . __('You are already logged in.', 'gymlite') . '</p>';
        }
        ob_start();
        ?>
        <div class="gymlite-signup uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Signup', 'gymlite'); ?></h2>
                <form id="gymlite-signup-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="name"><?php _e('Name', 'gymlite'); ?></label>
                        <input class="uk-input" type="text" name="name" id="name" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="email"><?php _e('Email', 'gymlite'); ?></label>
                        <input class="uk-input" type="email" name="email" id="email" required>
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="phone"><?php _e('Phone', 'gymlite'); ?></label>
                        <input class="uk-input" type="tel" name="phone" id="phone">
                    </div>
                    <div class="uk-margin">
                        <label class="uk-form-label" for="membership_type"><?php _e('Membership Type', 'gymlite'); ?></label>
                        <select class="uk-select" name="membership_type" id="membership_type" required>
                            <option value="trial"><?php _e('Trial', 'gymlite'); ?></option>
                            <option value="basic"><?php _e('Basic', 'gymlite'); ?></option>
                            <option value="premium"><?php _e('Premium', 'gymlite'); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Sign Up', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_signup', 'nonce'); ?>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function booking_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to book.', 'gymlite') . '</p>';
        }
        ob_start();
        ?>
        <div class="gymlite-booking uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Book a Class', 'gymlite'); ?></h2>
                <!-- Placeholder for booking form -->
                <form id="gymlite-booking-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="class_id"><?php _e('Class ID', 'gymlite'); ?></label>
                        <input class="uk-input" type="number" name="class_id" id="class_id" required>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Book', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_book_class', 'nonce'); ?>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function referrals_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-referrals uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Referrals', 'gymlite'); ?></h2>
                <p><?php _e('Refer a friend and get rewards!', 'gymlite'); ?></p>
                <!-- Placeholder for referrals logic -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function portal_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="uk-text-danger">' . __('Please log in to access the portal.', 'gymlite') . '</p>';
        }
        ob_start();
        ?>
        <div class="gymlite-portal uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Portal', 'gymlite'); ?></h2>
                <ul class="uk-nav uk-nav-default">
                    <li><a href="#"><?php _e('Profile', 'gymlite'); ?></a></li>
                    <li><a href="#"><?php _e('Bookings', 'gymlite'); ?></a></li>
                    <li><a href="#"><?php _e('Waivers', 'gymlite'); ?></a></li>
                    <!-- Add more links -->
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function waivers_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-waivers uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Waivers', 'gymlite'); ?></h2>
                <!-- Placeholder for waivers list and signing -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function access_status_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-access-status uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Access Status', 'gymlite'); ?></h2>
                <!-- Placeholder for access logs -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function progression_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-progression uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Progression', 'gymlite'); ?></h2>
                <!-- Placeholder for progression tracking -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function notifications_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-notifications uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Notifications', 'gymlite'); ?></h2>
                <!-- Placeholder for notifications -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_checkin', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to check in.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) {
            wp_send_json_error(['message' => __('Already checked in.', 'gymlite')]);
        }
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result) {
            gymlite_log("Check-in successful for member $member_id to class $class_id");
            wp_send_json_success(['message' => __('Checked in successfully!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
    }

    public function handle_lead_submission() {
        check_ajax_referer('gymlite_submit_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert(
            $table_name,
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'created_at' => current_time('mysql')],
            ['%s', '%s', '%s', '%s']
        );
        if ($result) {
            gymlite_log("Lead submitted: $name ($email)");
            wp_send_json_success(['message' => __('Lead submitted successfully!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Submission failed.', 'gymlite')]);
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        if (empty($name) || empty($email) || empty($membership_type)) {
            wp_send_json_error(['message' => __('Required fields missing.', 'gymlite')]);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('Email already registered.', 'gymlite')]);
        }
        $password = wp_generate_password();
        $user_id = wp_create_user($email, $password, $email);
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }
        wp_update_user(['ID' => $user_id, 'display_name' => $name]);
        $member_id = wp_insert_post([
            'post_type' => 'gymlite_member',
            'post_title' => $name,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);
        if (is_wp_error($member_id)) {
            wp_delete_user($user_id);
            wp_send_json_error(['message' => __('Member creation failed.', 'gymlite')]);
        }
        update_post_meta($member_id, '_gymlite_member_email', $email);
        update_post_meta($member_id, '_gymlite_member_phone', $phone);
        update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
        wp_new_user_notification($user_id, null, 'user');
        gymlite_log("New signup: $name ($user_id)");
        wp_send_json_success(['message' => __('Signup successful! Check email for password.', 'gymlite')]);
    }

    public function handle_booking() {
        check_ajax_referer('gymlite_book_class', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Login required.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        // Add booking logic here (e.g., check capacity, insert into bookings table)
        gymlite_log("Booking: member $member_id to class $class_id");
        wp_send_json_success(['message' => __('Booked successfully!', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_sign_waiver', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Login required.', 'gymlite')]);
        }
        $waiver_id = intval($_POST['waiver_id']);
        $signature = sanitize_text_field($_POST['signature']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'waiver_id' => $waiver_id, 'signed_date' => current_time('mysql'), 'signature' => $signature],
            ['%d', '%d', '%s', '%s']
        );
        if ($result) {
            gymlite_log("Waiver signed: $waiver_id by $member_id");
            wp_send_json_success(['message' => __('Waiver signed!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Signing failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_log_access', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Login required.', 'gymlite')]);
        }
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
        $status = ($membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result) {
            gymlite_log("Access log: $member_id - $status");
            wp_send_json_success(['message' => __('Access logged!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Logging failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_promote_member', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result) {
            update_post_meta($member_id, '_gymlite_progress_level', $level);
            gymlite_log("Promotion: $member_id to $level");
            wp_send_json_success(['message' => __('Promoted to ' . $level, 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('gymlite-frontend-style', GYMLITE_URL . 'assets/css/frontend.css', [], GYMLITE_VERSION);
        wp_enqueue_script('gymlite-frontend-script', GYMLITE_URL . 'assets/js/frontend.js', ['jquery'], GYMLITE_VERSION, true);
        wp_localize_script('gymlite-frontend-script', 'gymlite_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'checkin_nonce' => wp_create_nonce('gymlite_checkin'),
            'lead_nonce' => wp_create_nonce('gymlite_submit_lead'),
            'signup_nonce' => wp_create_nonce('gymlite_signup'),
            'booking_nonce' => wp_create_nonce('gymlite_book_class'),
            'waiver_nonce' => wp_create_nonce('gymlite_sign_waiver'),
            'access_nonce' => wp_create_nonce('gymlite_log_access'),
            'promote_nonce' => wp_create_nonce('gymlite_promote_member'),
        ]);
    }
}