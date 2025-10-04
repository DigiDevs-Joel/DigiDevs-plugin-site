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
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-checkin" data-class-id="<?php echo esc_attr($class_id); ?>"><?php _e('Check In', 'gymlite'); ?></button>
                                        <?php elseif ($already_checked) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Checked In', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <span class="uk-text-muted"><?php _e('Check-in unavailable', 'gymlite'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            wp_reset_postdata();
            $output = ob_get_clean();
            gymlite_log("schedule_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in schedule_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading schedule. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function calendar_shortcode($atts) {
        try {
            gymlite_log("calendar_shortcode called at " . current_time('Y-m-d H:i:s'));
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
                throw new Exception('Failed to query classes for calendar: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-calendar uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body">
                        <?php if ($classes->have_posts()) : ?>
                            <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                                <?php while ($classes->have_posts()) : $classes->the_post();
                                    $class_id = get_the_ID();
                                    $class_date_str = get_post_meta($class_id, '_gymlite_class_date', true);
                                    if ($class_date_str) {
                                        $date = new DateTime($class_date_str, new DateTimeZone('Australia/Sydney'));
                                        $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true) ?: 60);
                                ?>
                                    <div>
                                        <div class="uk-card uk-card-secondary uk-card-hover">
                                            <div class="uk-card-header">
                                                <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                                <p class="uk-text-meta"><?php echo esc_html($date->format('Y-m-d H:i')); ?></p>
                                            </div>
                                            <div class="uk-card-body">
                                                <p><?php echo esc_html($duration) . ' ' . __('minutes', 'gymlite'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } endwhile; ?>
                            </div>
                        <?php else : ?>
                            <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("calendar_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in calendar_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading calendar. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function attendance_log_shortcode($atts) {
        try {
            gymlite_log("attendance_log_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your attendance.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_attendance';
            $attendances = $wpdb->get_results($wpdb->prepare(
                "SELECT a.attendance_date, c.post_title AS class_name 
                 FROM $table_name a 
                 JOIN {$wpdb->posts} c ON a.class_id = c.ID 
                 WHERE a.member_id = %d 
                 ORDER BY a.attendance_date DESC 
                 LIMIT 10",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-attendance uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Attendance Log', 'gymlite'); ?></h2>
                    <?php if ($attendances && is_array($attendances)) : ?>
                        <table class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th><?php _e('Class', 'gymlite'); ?></th>
                                    <th><?php _e('Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendances as $attendance) : ?>
                                    <tr>
                                        <td><?php echo esc_html($attendance->class_name); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($attendance->attendance_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No attendance records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("attendance_log_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in attendance_log_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading attendance log.', 'gymlite') . '</p></div>';
        }
    }

    public function member_profile_shortcode($atts) {
        try {
            gymlite_log("member_profile_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your profile.', 'gymlite') . '</p>';
            }

            $user_id = get_current_user_id();
            $member_posts = get_posts([
                'post_type' => 'gymlite_member',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);

            if (empty($member_posts) || is_wp_error($member_posts)) {
                return '<p class="uk-text-warning">' . __('No member profile found. Please contact support.', 'gymlite') . '</p>';
            }

            $member_id = $member_posts[0]->ID;
            $name = get_the_title($member_id);
            $email = get_post_meta($member_id, '_gymlite_member_email', true);
            $phone = get_post_meta($member_id, '_gymlite_member_phone', true) ?: __('N/A', 'gymlite');
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: __('N/A', 'gymlite');
            $level = get_post_meta($member_id, '_gymlite_bjj_belt', true) ?: __('N/A', 'gymlite');

            ob_start();
            ?>
            <div class="gymlite-member-profile uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Profile', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body uk-text-center">
                        <p><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($name); ?></p>
                        <p><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html($email); ?></p>
                        <p><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html($phone); ?></p>
                        <p><strong><?php _e('Membership Type:', 'gymlite'); ?></strong> <?php echo esc_html($membership_type); ?></p>
                        <p><strong><?php _e('Progression Level:', 'gymlite'); ?></strong> <?php echo esc_html($level); ?></p>
                        <a href="<?php echo esc_url(get_permalink(get_option('gymlite_user_data_page_id'))); ?>" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></a>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("member_profile_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in member_profile_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading profile.', 'gymlite') . '</p></div>';
        }
    }

    public function lead_form_shortcode($atts) {
        try {
            gymlite_log("lead_form_shortcode called at " . current_time('Y-m-d H:i:s'));
            ob_start();
            ?>
            <div class="gymlite-lead-form uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Become a Lead', 'gymlite'); ?></h2>
                    <form id="gymlite-lead-form" class="uk-form-stacked">
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-name"><?php _e('Name', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="text" name="name" id="lead-name" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-email"><?php _e('Email', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="email" name="email" id="lead-email" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-phone"><?php _e('Phone', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="tel" name="phone" id="lead-phone">
                            </div>
                        </div>
                        <div class="uk-margin">
                            <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit', 'gymlite'); ?></button>
                        </div>
                        <?php wp_nonce_field('gymlite_lead', 'nonce'); ?>
                    </form>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("lead_form_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in lead_form_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading lead form.', 'gymlite') . '</p></div>';
        }
    }

    public function signup_shortcode($atts) {
        try {
            gymlite_log("signup_shortcode called at " . current_time('Y-m-d H:i:s'));
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
            $output = ob_get_clean();
            gymlite_log("signup_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in signup_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading signup form.', 'gymlite') . '</p></div>';
        }
    }

    public function booking_shortcode($atts) {
        try {
            gymlite_log("booking_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to book a class.', 'gymlite') . '</p>';
            }

            $args = [
                'post_type' => 'gymlite_class',
                'posts_per_page' => -1,
                'meta_key' => '_gymlite_class_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => '_gymlite_class_date',
                        'value' => current_time('mysql'),
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                ],
                'post_status' => 'publish',
            ];
            $classes = new WP_Query($args);
            if (is_wp_error($classes)) {
                throw new Exception('Failed to query classes for booking: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-booking uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Book a Class', 'gymlite'); ?></h2>
                    <?php if ($classes->have_posts()) : ?>
                        <form id="gymlite-booking-form" class="uk-form-stacked">
                            <div class="uk-margin">
                                <label class="uk-form-label" for="class_id"><?php _e('Select Class', 'gymlite'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="class_id" id="class_id" required>
                                        <?php while ($classes->have_posts()) : $classes->the_post();
                                            $class_id = get_the_ID();
                                            $class_date = get_post_meta($class_id, '_gymlite_class_date', true);
                                            if ($class_date) {
                                                $date = new DateTime($class_date, new DateTimeZone('Australia/Sydney'));
                                        ?>
                                            <option value="<?php echo esc_attr($class_id); ?>"><?php the_title(); ?> (<?php echo esc_html($date->format('Y-m-d H:i')); ?>)</option>
                                        <?php } endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="uk-margin">
                                <button type="submit" class="uk-button uk-button-primary"><?php _e('Book Now', 'gymlite'); ?></button>
                            </div>
                            <?php wp_nonce_field('gymlite_booking', 'nonce'); ?>
                        </form>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No upcoming classes available for booking.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("booking_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in booking_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading booking form.', 'gymlite') . '</p></div>';
        }
    }

    public function referrals_shortcode($atts) {
        try {
            gymlite_log("referrals_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your referrals.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $referral_link = home_url('?ref=' . $member_id);
            global $wpdb;
            $referred_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gymlite_referrals WHERE referrer_id = %d", $member_id));

            ob_start();
            ?>
            <div class="gymlite-referrals uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Referrals', 'gymlite'); ?></h2>
                    <p><?php _e('Share your referral link to earn credits! You\'ll get $10 for each successful referral.', 'gymlite'); ?></p>
                    <div class="uk-margin">
                        <input class="uk-input" type="text" value="<?php echo esc_url($referral_link); ?>" readonly>
                    </div>
                    <p class="uk-text-meta"><?php echo sprintf(__('You have referred %d members.', 'gymlite'), $referred_count); ?></p>
                    <p class="uk-text-meta"><?php _e('Click the field to copy the link.', 'gymlite'); ?></p>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("referrals_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in referrals_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading referrals.', 'gymlite') . '</p></div>';
        }
    }

    public function portal_shortcode($atts) {
        try {
            gymlite_log("portal_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to access the portal.', 'gymlite') . '</p>';
            }

            ob_start();
            ?>
            <div class="gymlite-portal uk-section">
                <div class="uk-container">
                    <h1 class="uk-heading-large uk-text-center"><?php _e('GymLite Member Portal', 'gymlite'); ?></h1>
                    <div class="uk-child-width-1-3@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_member_profile]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_schedule]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_attendance_log]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_booking]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_referrals]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_waivers]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_access_status]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_progression]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_notifications]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("portal_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in portal_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading portal.', 'gymlite') . '</p></div>';
        }
    }

    public function waivers_shortcode($atts) {
        try {
            gymlite_log("waivers_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view waivers.', 'gymlite') . '</p>';
            }

            $args = ['post_type' => 'gymlite_waiver', 'posts_per_page' => -1, 'post_status' => 'publish'];
            $waivers = new WP_Query($args);
            if (is_wp_error($waivers)) {
                throw new Exception('Failed to query waivers: ' . $waivers->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-waivers uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Waivers', 'gymlite'); ?></h2>
                    <?php if ($waivers->have_posts()) : ?>
                        <div class="uk-child-width-1-2@m uk-grid-small" uk-grid>
                            <?php while ($waivers->have_posts()) : $waivers->the_post();
                                $waiver_id = get_the_ID();
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
                                $signed = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $table_name WHERE member_id = %d AND waiver_id = %d",
                                    get_current_user_id(),
                                    $waiver_id
                                ));
                                if ($wpdb->last_error) {
                                    throw new Exception('Database query failed: ' . $wpdb->last_error);
                                }
                            ?>
                                <div>
                                    <div class="uk-card uk-card-default uk-card-body">
                                        <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                        <div><?php echo wp_kses_post(get_the_content()); ?></div>
                                        <?php if ($signed) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Signed', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-sign-waiver" data-waiver-id="<?php echo esc_attr($waiver_id); ?>"><?php _e('Sign Waiver', 'gymlite'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No waivers available.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("waivers_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in waivers_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading waivers.', 'gymlite') . '</p></div>';
        }
    }

    public function access_status_shortcode($atts) {
        try {
            gymlite_log("access_status_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to check access status.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
            $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';

            ob_start();
            ?>
            <div class="gymlite-access-status uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Access Status', 'gymlite'); ?></h2>
                    <p><?php echo esc_html(ucfirst($status)) . ' ' . __('access to facilities.', 'gymlite'); ?></p>
                    <button class="uk-button uk-button-primary gymlite-log-access"><?php _e('Log Access', 'gymlite'); ?></button>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("access_status_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in access_status_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading access status.', 'gymlite') . '</p></div>';
        }
    }

    public function progression_shortcode($atts) {
        try {
            gymlite_log("progression_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your progression.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_progression';
            $progressions = $wpdb->get_results($wpdb->prepare(
                "SELECT level, promoted_date FROM $table_name WHERE member_id = %d ORDER BY promoted_date DESC",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-progression uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Progression Tracking', 'gymlite'); ?></h2>
                    <?php if ($progressions && is_array($progressions)) : ?>
                        <table class="uk-table uk-table-striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Level', 'gymlite'); ?></th>
                                    <th><?php _e('Promoted Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressions as $progression) : ?>
                                    <tr>
                                        <td><?php echo esc_html($progression->level); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($progression->promoted_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No progression records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="uk-button uk-button-primary uk-button-small gymlite-promote-member" data-member-id="<?php echo esc_attr($member_id); ?>"><?php _e('Promote Member', 'gymlite'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("progression_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in progression_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading progression.', 'gymlite') . '</p></div>';
        }
    }

    public function notifications_shortcode($atts) {
        try {
            gymlite_log("notifications_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view notifications.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_comms_logs';
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT type, sent_date, status FROM $table_name WHERE member_id = %d ORDER BY sent_date DESC LIMIT 5",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-notifications uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Notifications', 'gymlite'); ?></h2>
                    <?php if ($notifications && is_array($notifications)) : ?>
                        <ul class="uk-list uk-list-divider">
                            <?php foreach ($notifications as $notification) : ?>
                                <li><?php echo esc_html(ucfirst($notification->type) . ' - ' . date('Y-m-d H:i', strtotime($notification->sent_date)) . ' (' . $notification->status . ')'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No notifications found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("notifications_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in notifications_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading notifications.', 'gymlite') . '</p></div>';
        }
    }

    public function enqueue_frontend_scripts() {
        try {
            gymlite_log("enqueue_frontend_scripts called at " . current_time('Y-m-d H:i:s'));
            if (!is_admin() && !wp_style_is('uikit', 'enqueued') && !wp_style_is('uikit', 'registered')) {
                wp_enqueue_style('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/css/uikit.min.css', [], '3.21.5');
                gymlite_log("UIkit CSS enqueued");
            }
            if (!is_admin() && !wp_script_is('uikit', 'enqueued') && !wp_script_is('uikit', 'registered')) {
                wp_enqueue_script('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit.min.js', ['jquery'], '3.21.5', true);
                wp_enqueue_script('uikit-icons', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit-icons.min.js', ['uikit'], '3.21.5', true);
                gymlite_log("UIkit JS enqueued");
            }

            wp_enqueue_style('gymlite-style', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION);
            gymlite_log("GymLite CSS enqueued");
            wp_enqueue_script('gymlite-frontend-script', GYMLITE_URL . 'assets/js/gymlite-frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
            gymlite_log("GymLite JS enqueued");

            $js = '
                jQuery(document).ready(function($) {
                    gymlite_log("Frontend JS loaded");
                    $(".gymlite-checkin").on("click", function(e) {
                        e.preventDefault();
                        var classId = $(this).data("class-id");
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_checkin", class_id: classId, nonce: gymlite_ajax.nonce},
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $(this).replaceWith(\'<span class="uk-label uk-label-success">Checked In</span>\'); 
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Check-in failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-lead-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_submit_lead&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-lead-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Lead submission failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-signup-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_signup&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                if (response.data.redirect) window.location.href = response.data.redirect;
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Signup failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-booking-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_book_class&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-booking-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Booking failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-sign-waiver").on("click", function(e) {
                        e.preventDefault();
                        var waiverId = $(this).data("waiver-id");
                        var signature = prompt("' . __('Enter your signature (e.g., initials)', 'gymlite') . '");
                        if (signature) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_sign_waiver", waiver_id: waiverId, signature: signature, nonce: gymlite_ajax.nonce},
                                success: function(response) { 
                                    UIkit.notification({message: response.data.message, status: "success"}); 
                                    $(e.target).replaceWith(\'<span class="uk-label uk-label-success">Signed</span>\');
                                },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Waiver signing failed", status: "danger"}); }
                            });
                        }
                    });
                    $(".gymlite-log-access").on("click", function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_log_access", nonce: gymlite_ajax.nonce},
                            success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Access log failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-promote-member").on("click", function(e) {
                        e.preventDefault();
                        var memberId = $(this).data("member-id");
                        var level = prompt("' . __('Enter new level (e.g., blue belt)', 'gymlite') . '");
                        if (level) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_promote_member", member_id: memberId, level: level, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Promotion failed", status: "danger"}); }
                            });
                        }
                    });
                });
            ';
            wp_add_inline_script('gymlite-frontend-script', $js);
            wp_localize_script('gymlite-frontend-script', 'gymlite_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gymlite_nonce'),
            ]);
            gymlite_log("GymLite frontend scripts localized");
        } catch (Exception $e) {
            gymlite_log("Error in enqueue_frontend_scripts: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to check in.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) {
            wp_send_json_error(['message' => __('You have already checked in for this class.', 'gymlite')]);
        }
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
    }

    public function handle_lead_submission() {
        check_ajax_referer('gymlite_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert(
            $table_name,
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'created_at' => current_time('mysql')],
            ['%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Lead submitted: $name, $email at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Thank you for your interest! We will contact you soon.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Lead submission failed.', 'gymlite')]);
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already registered.', 'gymlite')]);
        }
        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('subscriber');
            $member_id = wp_insert_post([
                'post_title' => $name,
                'post_type' => 'gymlite_member',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (!is_wp_error($member_id)) {
                update_post_meta($member_id, '_gymlite_member_email', $email);
                update_post_meta($member_id, '_gymlite_member_phone', $phone);
                update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
                wp_new_user_notification($user_id, null, 'both');
                gymlite_log("Signup for $name (ID $user_id) with membership $membership_type at " . current_time('Y-m-d H:i:s'));
                $login_url = get_permalink(get_option('gymlite_login_page_id'));
                wp_send_json_success(['message' => sprintf(__('Account created! Check your email for login details. <a href="%s">Log in</a>', 'gymlite'), esc_url($login_url)), 'redirect' => $login_url]);
            }
        }
        wp_send_json_error(['message' => __('Signup failed. Please try again.', 'gymlite')]);
    }

    public function handle_booking() {
        check_ajax_referer('gymlite_booking', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a class.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        // Placeholder for booking logic (e.g., check availability, send confirmation)
        gymlite_log("Booking for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Class booked successfully! Confirmation will be sent.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to sign a waiver.', 'gymlite')]);
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
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to log access.', 'gymlite')]);
        }
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

// Continuation of class-gymlite-frontend.php (Part 2 of 5)

// [No additional class definition or constructor here - this is a continuation]

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
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-checkin" data-class-id="<?php echo esc_attr($class_id); ?>"><?php _e('Check In', 'gymlite'); ?></button>
                                        <?php elseif ($already_checked) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Checked In', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <span class="uk-text-muted"><?php _e('Check-in unavailable', 'gymlite'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            wp_reset_postdata();
            $output = ob_get_clean();
            gymlite_log("schedule_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in schedule_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading schedule. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function calendar_shortcode($atts) {
        try {
            gymlite_log("calendar_shortcode called at " . current_time('Y-m-d H:i:s'));
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
                throw new Exception('Failed to query classes for calendar: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-calendar uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body">
                        <?php if ($classes->have_posts()) : ?>
                            <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                                <?php while ($classes->have_posts()) : $classes->the_post();
                                    $class_id = get_the_ID();
                                    $class_date_str = get_post_meta($class_id, '_gymlite_class_date', true);
                                    if ($class_date_str) {
                                        $date = new DateTime($class_date_str, new DateTimeZone('Australia/Sydney'));
                                        $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true) ?: 60);
                                ?>
                                    <div>
                                        <div class="uk-card uk-card-secondary uk-card-hover">
                                            <div class="uk-card-header">
                                                <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                                <p class="uk-text-meta"><?php echo esc_html($date->format('Y-m-d H:i')); ?></p>
                                            </div>
                                            <div class="uk-card-body">
                                                <p><?php echo esc_html($duration) . ' ' . __('minutes', 'gymlite'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } endwhile; ?>
                            </div>
                        <?php else : ?>
                            <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("calendar_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in calendar_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading calendar. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function attendance_log_shortcode($atts) {
        try {
            gymlite_log("attendance_log_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your attendance.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_attendance';
            $attendances = $wpdb->get_results($wpdb->prepare(
                "SELECT a.attendance_date, c.post_title AS class_name 
                 FROM $table_name a 
                 JOIN {$wpdb->posts} c ON a.class_id = c.ID 
                 WHERE a.member_id = %d 
                 ORDER BY a.attendance_date DESC 
                 LIMIT 10",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-attendance uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Attendance Log', 'gymlite'); ?></h2>
                    <?php if ($attendances && is_array($attendances)) : ?>
                        <table class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th><?php _e('Class', 'gymlite'); ?></th>
                                    <th><?php _e('Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendances as $attendance) : ?>
                                    <tr>
                                        <td><?php echo esc_html($attendance->class_name); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($attendance->attendance_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No attendance records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("attendance_log_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in attendance_log_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading attendance log.', 'gymlite') . '</p></div>';
        }
    }

    public function member_profile_shortcode($atts) {
        try {
            gymlite_log("member_profile_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your profile.', 'gymlite') . '</p>';
            }

            $user_id = get_current_user_id();
            $member_posts = get_posts([
                'post_type' => 'gymlite_member',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);

            if (empty($member_posts) || is_wp_error($member_posts)) {
                return '<p class="uk-text-warning">' . __('No member profile found. Please contact support.', 'gymlite') . '</p>';
            }

            $member_id = $member_posts[0]->ID;
            $name = get_the_title($member_id);
            $email = get_post_meta($member_id, '_gymlite_member_email', true);
            $phone = get_post_meta($member_id, '_gymlite_member_phone', true) ?: __('N/A', 'gymlite');
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: __('N/A', 'gymlite');
            $level = get_post_meta($member_id, '_gymlite_bjj_belt', true) ?: __('N/A', 'gymlite');

            ob_start();
            ?>
            <div class="gymlite-member-profile uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Profile', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body uk-text-center">
                        <p><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($name); ?></p>
                        <p><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html($email); ?></p>
                        <p><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html($phone); ?></p>
                        <p><strong><?php _e('Membership Type:', 'gymlite'); ?></strong> <?php echo esc_html($membership_type); ?></p>
                        <p><strong><?php _e('Progression Level:', 'gymlite'); ?></strong> <?php echo esc_html($level); ?></p>
                        <a href="<?php echo esc_url(get_permalink(get_option('gymlite_user_data_page_id'))); ?>" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></a>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("member_profile_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in member_profile_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading profile.', 'gymlite') . '</p></div>';
        }
    }

    public function lead_form_shortcode($atts) {
        try {
            gymlite_log("lead_form_shortcode called at " . current_time('Y-m-d H:i:s'));
            ob_start();
            ?>
            <div class="gymlite-lead-form uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Become a Lead', 'gymlite'); ?></h2>
                    <form id="gymlite-lead-form" class="uk-form-stacked">
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-name"><?php _e('Name', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="text" name="name" id="lead-name" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-email"><?php _e('Email', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="email" name="email" id="lead-email" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-phone"><?php _e('Phone', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="tel" name="phone" id="lead-phone">
                            </div>
                        </div>
                        <div class="uk-margin">
                            <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit', 'gymlite'); ?></button>
                        </div>
                        <?php wp_nonce_field('gymlite_lead', 'nonce'); ?>
                    </form>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("lead_form_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in lead_form_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading lead form.', 'gymlite') . '</p></div>';
        }
    }

    public function signup_shortcode($atts) {
        try {
            gymlite_log("signup_shortcode called at " . current_time('Y-m-d H:i:s'));
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
            $output = ob_get_clean();
            gymlite_log("signup_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in signup_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading signup form.', 'gymlite') . '</p></div>';
        }
    }

    public function booking_shortcode($atts) {
        try {
            gymlite_log("booking_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to book a class.', 'gymlite') . '</p>';
            }

            $args = [
                'post_type' => 'gymlite_class',
                'posts_per_page' => -1,
                'meta_key' => '_gymlite_class_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => '_gymlite_class_date',
                        'value' => current_time('mysql'),
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                ],
                'post_status' => 'publish',
            ];
            $classes = new WP_Query($args);
            if (is_wp_error($classes)) {
                throw new Exception('Failed to query classes for booking: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-booking uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Book a Class', 'gymlite'); ?></h2>
                    <?php if ($classes->have_posts()) : ?>
                        <form id="gymlite-booking-form" class="uk-form-stacked">
                            <div class="uk-margin">
                                <label class="uk-form-label" for="class_id"><?php _e('Select Class', 'gymlite'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="class_id" id="class_id" required>
                                        <?php while ($classes->have_posts()) : $classes->the_post();
                                            $class_id = get_the_ID();
                                            $class_date = get_post_meta($class_id, '_gymlite_class_date', true);
                                            if ($class_date) {
                                                $date = new DateTime($class_date, new DateTimeZone('Australia/Sydney'));
                                        ?>
                                            <option value="<?php echo esc_attr($class_id); ?>"><?php the_title(); ?> (<?php echo esc_html($date->format('Y-m-d H:i')); ?>)</option>
                                        <?php } endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="uk-margin">
                                <button type="submit" class="uk-button uk-button-primary"><?php _e('Book Now', 'gymlite'); ?></button>
                            </div>
                            <?php wp_nonce_field('gymlite_booking', 'nonce'); ?>
                        </form>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No upcoming classes available for booking.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("booking_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in booking_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading booking form.', 'gymlite') . '</p></div>';
        }
    }

    public function referrals_shortcode($atts) {
        try {
            gymlite_log("referrals_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your referrals.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $referral_link = home_url('?ref=' . $member_id);
            global $wpdb;
            $referred_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gymlite_referrals WHERE referrer_id = %d", $member_id));

            ob_start();
            ?>
            <div class="gymlite-referrals uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Referrals', 'gymlite'); ?></h2>
                    <p><?php _e('Share your referral link to earn credits! You\'ll get $10 for each successful referral.', 'gymlite'); ?></p>
                    <div class="uk-margin">
                        <input class="uk-input" type="text" value="<?php echo esc_url($referral_link); ?>" readonly>
                    </div>
                    <p class="uk-text-meta"><?php echo sprintf(__('You have referred %d members.', 'gymlite'), $referred_count); ?></p>
                    <p class="uk-text-meta"><?php _e('Click the field to copy the link.', 'gymlite'); ?></p>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("referrals_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in referrals_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading referrals.', 'gymlite') . '</p></div>';
        }
    }

    public function portal_shortcode($atts) {
        try {
            gymlite_log("portal_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to access the portal.', 'gymlite') . '</p>';
            }

            ob_start();
            ?>
            <div class="gymlite-portal uk-section">
                <div class="uk-container">
                    <h1 class="uk-heading-large uk-text-center"><?php _e('GymLite Member Portal', 'gymlite'); ?></h1>
                    <div class="uk-child-width-1-3@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_member_profile]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_schedule]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_attendance_log]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_booking]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_referrals]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_waivers]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_access_status]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_progression]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_notifications]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("portal_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in portal_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading portal.', 'gymlite') . '</p></div>';
        }
    }

    public function waivers_shortcode($atts) {
        try {
            gymlite_log("waivers_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view waivers.', 'gymlite') . '</p>';
            }

            $args = ['post_type' => 'gymlite_waiver', 'posts_per_page' => -1, 'post_status' => 'publish'];
            $waivers = new WP_Query($args);
            if (is_wp_error($waivers)) {
                throw new Exception('Failed to query waivers: ' . $waivers->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-waivers uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Waivers', 'gymlite'); ?></h2>
                    <?php if ($waivers->have_posts()) : ?>
                        <div class="uk-child-width-1-2@m uk-grid-small" uk-grid>
                            <?php while ($waivers->have_posts()) : $waivers->the_post();
                                $waiver_id = get_the_ID();
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
                                $signed = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $table_name WHERE member_id = %d AND waiver_id = %d",
                                    get_current_user_id(),
                                    $waiver_id
                                ));
                                if ($wpdb->last_error) {
                                    throw new Exception('Database query failed: ' . $wpdb->last_error);
                                }
                            ?>
                                <div>
                                    <div class="uk-card uk-card-default uk-card-body">
                                        <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                        <div><?php echo wp_kses_post(get_the_content()); ?></div>
                                        <?php if ($signed) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Signed', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-sign-waiver" data-waiver-id="<?php echo esc_attr($waiver_id); ?>"><?php _e('Sign Waiver', 'gymlite'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No waivers available.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("waivers_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in waivers_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading waivers.', 'gymlite') . '</p></div>';
        }
    }

    public function access_status_shortcode($atts) {
        try {
            gymlite_log("access_status_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to check access status.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
            $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';

            ob_start();
            ?>
            <div class="gymlite-access-status uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Access Status', 'gymlite'); ?></h2>
                    <p><?php echo esc_html(ucfirst($status)) . ' ' . __('access to facilities.', 'gymlite'); ?></p>
                    <button class="uk-button uk-button-primary gymlite-log-access"><?php _e('Log Access', 'gymlite'); ?></button>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("access_status_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in access_status_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading access status.', 'gymlite') . '</p></div>';
        }
    }

    public function progression_shortcode($atts) {
        try {
            gymlite_log("progression_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your progression.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_progression';
            $progressions = $wpdb->get_results($wpdb->prepare(
                "SELECT level, promoted_date FROM $table_name WHERE member_id = %d ORDER BY promoted_date DESC",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-progression uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Progression Tracking', 'gymlite'); ?></h2>
                    <?php if ($progressions && is_array($progressions)) : ?>
                        <table class="uk-table uk-table-striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Level', 'gymlite'); ?></th>
                                    <th><?php _e('Promoted Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressions as $progression) : ?>
                                    <tr>
                                        <td><?php echo esc_html($progression->level); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($progression->promoted_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No progression records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="uk-button uk-button-primary uk-button-small gymlite-promote-member" data-member-id="<?php echo esc_attr($member_id); ?>"><?php _e('Promote Member', 'gymlite'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("progression_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in progression_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading progression.', 'gymlite') . '</p></div>';
        }
    }

    public function notifications_shortcode($atts) {
        try {
            gymlite_log("notifications_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view notifications.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_comms_logs';
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT type, sent_date, status FROM $table_name WHERE member_id = %d ORDER BY sent_date DESC LIMIT 5",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-notifications uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Notifications', 'gymlite'); ?></h2>
                    <?php if ($notifications && is_array($notifications)) : ?>
                        <ul class="uk-list uk-list-divider">
                            <?php foreach ($notifications as $notification) : ?>
                                <li><?php echo esc_html(ucfirst($notification->type) . ' - ' . date('Y-m-d H:i', strtotime($notification->sent_date)) . ' (' . $notification->status . ')'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No notifications found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("notifications_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in notifications_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading notifications.', 'gymlite') . '</p></div>';
        }
    }

    public function enqueue_frontend_scripts() {
        try {
            gymlite_log("enqueue_frontend_scripts called at " . current_time('Y-m-d H:i:s'));
            if (!is_admin() && !wp_style_is('uikit', 'enqueued') && !wp_style_is('uikit', 'registered')) {
                wp_enqueue_style('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/css/uikit.min.css', [], '3.21.5');
                gymlite_log("UIkit CSS enqueued");
            }
            if (!is_admin() && !wp_script_is('uikit', 'enqueued') && !wp_script_is('uikit', 'registered')) {
                wp_enqueue_script('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit.min.js', ['jquery'], '3.21.5', true);
                wp_enqueue_script('uikit-icons', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit-icons.min.js', ['uikit'], '3.21.5', true);
                gymlite_log("UIkit JS enqueued");
            }

            wp_enqueue_style('gymlite-style', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION);
            gymlite_log("GymLite CSS enqueued");
            wp_enqueue_script('gymlite-frontend-script', GYMLITE_URL . 'assets/js/gymlite-frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
            gymlite_log("GymLite JS enqueued");

            $js = '
                jQuery(document).ready(function($) {
                    gymlite_log("Frontend JS loaded");
                    $(".gymlite-checkin").on("click", function(e) {
                        e.preventDefault();
                        var classId = $(this).data("class-id");
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_checkin", class_id: classId, nonce: gymlite_ajax.nonce},
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $(this).replaceWith(\'<span class="uk-label uk-label-success">Checked In</span>\'); 
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Check-in failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-lead-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_submit_lead&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-lead-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Lead submission failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-signup-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_signup&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                if (response.data.redirect) window.location.href = response.data.redirect;
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Signup failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-booking-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_book_class&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-booking-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Booking failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-sign-waiver").on("click", function(e) {
                        e.preventDefault();
                        var waiverId = $(this).data("waiver-id");
                        var signature = prompt("' . __('Enter your signature (e.g., initials)', 'gymlite') . '");
                        if (signature) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_sign_waiver", waiver_id: waiverId, signature: signature, nonce: gymlite_ajax.nonce},
                                success: function(response) { 
                                    UIkit.notification({message: response.data.message, status: "success"}); 
                                    $(e.target).replaceWith(\'<span class="uk-label uk-label-success">Signed</span>\');
                                },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Waiver signing failed", status: "danger"}); }
                            });
                        }
                    });
                    $(".gymlite-log-access").on("click", function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_log_access", nonce: gymlite_ajax.nonce},
                            success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Access log failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-promote-member").on("click", function(e) {
                        e.preventDefault();
                        var memberId = $(this).data("member-id");
                        var level = prompt("' . __('Enter new level (e.g., blue belt)', 'gymlite') . '");
                        if (level) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_promote_member", member_id: memberId, level: level, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Promotion failed", status: "danger"}); }
                            });
                        }
                    });
                });
            ';
            wp_add_inline_script('gymlite-frontend-script', $js);
            wp_localize_script('gymlite-frontend-script', 'gymlite_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gymlite_nonce'),
            ]);
            gymlite_log("GymLite frontend scripts localized");
        } catch (Exception $e) {
            gymlite_log("Error in enqueue_frontend_scripts: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to check in.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) {
            wp_send_json_error(['message' => __('You have already checked in for this class.', 'gymlite')]);
        }
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
    }

    public function handle_lead_submission() {
        check_ajax_referer('gymlite_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert(
            $table_name,
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'created_at' => current_time('mysql')],
            ['%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Lead submitted: $name, $email at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Thank you for your interest! We will contact you soon.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Lead submission failed.', 'gymlite')]);
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already registered.', 'gymlite')]);
        }
        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('subscriber');
            $member_id = wp_insert_post([
                'post_title' => $name,
                'post_type' => 'gymlite_member',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (!is_wp_error($member_id)) {
                update_post_meta($member_id, '_gymlite_member_email', $email);
                update_post_meta($member_id, '_gymlite_member_phone', $phone);
                update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
                wp_new_user_notification($user_id, null, 'both');
                gymlite_log("Signup for $name (ID $user_id) with membership $membership_type at " . current_time('Y-m-d H:i:s'));
                $login_url = get_permalink(get_option('gymlite_login_page_id'));
                wp_send_json_success(['message' => sprintf(__('Account created! Check your email for login details. <a href="%s">Log in</a>', 'gymlite'), esc_url($login_url)), 'redirect' => $login_url]);
            }
        }
        wp_send_json_error(['message' => __('Signup failed. Please try again.', 'gymlite')]);
    }

    public function handle_booking() {
        check_ajax_referer('gymlite_booking', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a class.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        // Placeholder for booking logic (e.g., check availability, send confirmation)
        // In a real implementation, check class capacity and update attendance or booking table
        gymlite_log("Booking for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Class booked successfully! Confirmation will be sent.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to sign a waiver.', 'gymlite')]);
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
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to log access.', 'gymlite')]);
        }
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

// Continuation of class-gymlite-frontend.php (Part 3 of 5)

// [No additional class definition or constructor here - this is a continuation]

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
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-checkin" data-class-id="<?php echo esc_attr($class_id); ?>"><?php _e('Check In', 'gymlite'); ?></button>
                                        <?php elseif ($already_checked) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Checked In', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <span class="uk-text-muted"><?php _e('Check-in unavailable', 'gymlite'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            wp_reset_postdata();
            $output = ob_get_clean();
            gymlite_log("schedule_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in schedule_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading schedule. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function calendar_shortcode($atts) {
        try {
            gymlite_log("calendar_shortcode called at " . current_time('Y-m-d H:i:s'));
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
                throw new Exception('Failed to query classes for calendar: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-calendar uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body">
                        <?php if ($classes->have_posts()) : ?>
                            <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                                <?php while ($classes->have_posts()) : $classes->the_post();
                                    $class_id = get_the_ID();
                                    $class_date_str = get_post_meta($class_id, '_gymlite_class_date', true);
                                    if ($class_date_str) {
                                        $date = new DateTime($class_date_str, new DateTimeZone('Australia/Sydney'));
                                        $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true) ?: 60);
                                ?>
                                    <div>
                                        <div class="uk-card uk-card-secondary uk-card-hover">
                                            <div class="uk-card-header">
                                                <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                                <p class="uk-text-meta"><?php echo esc_html($date->format('Y-m-d H:i')); ?></p>
                                            </div>
                                            <div class="uk-card-body">
                                                <p><?php echo esc_html($duration) . ' ' . __('minutes', 'gymlite'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } endwhile; ?>
                            </div>
                        <?php else : ?>
                            <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("calendar_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in calendar_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading calendar. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function attendance_log_shortcode($atts) {
        try {
            gymlite_log("attendance_log_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your attendance.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_attendance';
            $attendances = $wpdb->get_results($wpdb->prepare(
                "SELECT a.attendance_date, c.post_title AS class_name 
                 FROM $table_name a 
                 JOIN {$wpdb->posts} c ON a.class_id = c.ID 
                 WHERE a.member_id = %d 
                 ORDER BY a.attendance_date DESC 
                 LIMIT 10",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-attendance uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Attendance Log', 'gymlite'); ?></h2>
                    <?php if ($attendances && is_array($attendances)) : ?>
                        <table class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th><?php _e('Class', 'gymlite'); ?></th>
                                    <th><?php _e('Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendances as $attendance) : ?>
                                    <tr>
                                        <td><?php echo esc_html($attendance->class_name); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($attendance->attendance_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No attendance records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("attendance_log_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in attendance_log_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading attendance log.', 'gymlite') . '</p></div>';
        }
    }

    public function member_profile_shortcode($atts) {
        try {
            gymlite_log("member_profile_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your profile.', 'gymlite') . '</p>';
            }

            $user_id = get_current_user_id();
            $member_posts = get_posts([
                'post_type' => 'gymlite_member',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);

            if (empty($member_posts) || is_wp_error($member_posts)) {
                return '<p class="uk-text-warning">' . __('No member profile found. Please contact support.', 'gymlite') . '</p>';
            }

            $member_id = $member_posts[0]->ID;
            $name = get_the_title($member_id);
            $email = get_post_meta($member_id, '_gymlite_member_email', true);
            $phone = get_post_meta($member_id, '_gymlite_member_phone', true) ?: __('N/A', 'gymlite');
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: __('N/A', 'gymlite');
            $level = get_post_meta($member_id, '_gymlite_bjj_belt', true) ?: __('N/A', 'gymlite');

            ob_start();
            ?>
            <div class="gymlite-member-profile uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Profile', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body uk-text-center">
                        <p><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($name); ?></p>
                        <p><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html($email); ?></p>
                        <p><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html($phone); ?></p>
                        <p><strong><?php _e('Membership Type:', 'gymlite'); ?></strong> <?php echo esc_html($membership_type); ?></p>
                        <p><strong><?php _e('Progression Level:', 'gymlite'); ?></strong> <?php echo esc_html($level); ?></p>
                        <a href="<?php echo esc_url(get_permalink(get_option('gymlite_user_data_page_id'))); ?>" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></a>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("member_profile_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in member_profile_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading profile.', 'gymlite') . '</p></div>';
        }
    }

    public function lead_form_shortcode($atts) {
        try {
            gymlite_log("lead_form_shortcode called at " . current_time('Y-m-d H:i:s'));
            ob_start();
            ?>
            <div class="gymlite-lead-form uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Become a Lead', 'gymlite'); ?></h2>
                    <form id="gymlite-lead-form" class="uk-form-stacked">
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-name"><?php _e('Name', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="text" name="name" id="lead-name" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-email"><?php _e('Email', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="email" name="email" id="lead-email" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-phone"><?php _e('Phone', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="tel" name="phone" id="lead-phone">
                            </div>
                        </div>
                        <div class="uk-margin">
                            <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit', 'gymlite'); ?></button>
                        </div>
                        <?php wp_nonce_field('gymlite_lead', 'nonce'); ?>
                    </form>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("lead_form_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in lead_form_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading lead form.', 'gymlite') . '</p></div>';
        }
    }

    public function signup_shortcode($atts) {
        try {
            gymlite_log("signup_shortcode called at " . current_time('Y-m-d H:i:s'));
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
            $output = ob_get_clean();
            gymlite_log("signup_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in signup_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading signup form.', 'gymlite') . '</p></div>';
        }
    }

    public function booking_shortcode($atts) {
        try {
            gymlite_log("booking_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to book a class.', 'gymlite') . '</p>';
            }

            $args = [
                'post_type' => 'gymlite_class',
                'posts_per_page' => -1,
                'meta_key' => '_gymlite_class_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => '_gymlite_class_date',
                        'value' => current_time('mysql'),
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                ],
                'post_status' => 'publish',
            ];
            $classes = new WP_Query($args);
            if (is_wp_error($classes)) {
                throw new Exception('Failed to query classes for booking: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-booking uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Book a Class', 'gymlite'); ?></h2>
                    <?php if ($classes->have_posts()) : ?>
                        <form id="gymlite-booking-form" class="uk-form-stacked">
                            <div class="uk-margin">
                                <label class="uk-form-label" for="class_id"><?php _e('Select Class', 'gymlite'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="class_id" id="class_id" required>
                                        <?php while ($classes->have_posts()) : $classes->the_post();
                                            $class_id = get_the_ID();
                                            $class_date = get_post_meta($class_id, '_gymlite_class_date', true);
                                            if ($class_date) {
                                                $date = new DateTime($class_date, new DateTimeZone('Australia/Sydney'));
                                        ?>
                                            <option value="<?php echo esc_attr($class_id); ?>"><?php the_title(); ?> (<?php echo esc_html($date->format('Y-m-d H:i')); ?>)</option>
                                        <?php } endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="uk-margin">
                                <button type="submit" class="uk-button uk-button-primary"><?php _e('Book Now', 'gymlite'); ?></button>
                            </div>
                            <?php wp_nonce_field('gymlite_booking', 'nonce'); ?>
                        </form>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No upcoming classes available for booking.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("booking_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in booking_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading booking form.', 'gymlite') . '</p></div>';
        }
    }

    public function referrals_shortcode($atts) {
        try {
            gymlite_log("referrals_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your referrals.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $referral_link = home_url('?ref=' . $member_id);
            global $wpdb;
            $referred_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gymlite_referrals WHERE referrer_id = %d", $member_id));

            ob_start();
            ?>
            <div class="gymlite-referrals uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Referrals', 'gymlite'); ?></h2>
                    <p><?php _e('Share your referral link to earn credits! You\'ll get $10 for each successful referral.', 'gymlite'); ?></p>
                    <div class="uk-margin">
                        <input class="uk-input" type="text" value="<?php echo esc_url($referral_link); ?>" readonly>
                    </div>
                    <p class="uk-text-meta"><?php echo sprintf(__('You have referred %d members.', 'gymlite'), $referred_count); ?></p>
                    <p class="uk-text-meta"><?php _e('Click the field to copy the link.', 'gymlite'); ?></p>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("referrals_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in referrals_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading referrals.', 'gymlite') . '</p></div>';
        }
    }

    public function portal_shortcode($atts) {
        try {
            gymlite_log("portal_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to access the portal.', 'gymlite') . '</p>';
            }

            ob_start();
            ?>
            <div class="gymlite-portal uk-section">
                <div class="uk-container">
                    <h1 class="uk-heading-large uk-text-center"><?php _e('GymLite Member Portal', 'gymlite'); ?></h1>
                    <div class="uk-child-width-1-3@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_member_profile]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_schedule]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_attendance_log]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_booking]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_referrals]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_waivers]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_access_status]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_progression]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_notifications]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("portal_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in portal_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading portal.', 'gymlite') . '</p></div>';
        }
    }

    public function waivers_shortcode($atts) {
        try {
            gymlite_log("waivers_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view waivers.', 'gymlite') . '</p>';
            }

            $args = ['post_type' => 'gymlite_waiver', 'posts_per_page' => -1, 'post_status' => 'publish'];
            $waivers = new WP_Query($args);
            if (is_wp_error($waivers)) {
                throw new Exception('Failed to query waivers: ' . $waivers->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-waivers uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Waivers', 'gymlite'); ?></h2>
                    <?php if ($waivers->have_posts()) : ?>
                        <div class="uk-child-width-1-2@m uk-grid-small" uk-grid>
                            <?php while ($waivers->have_posts()) : $waivers->the_post();
                                $waiver_id = get_the_ID();
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
                                $signed = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $table_name WHERE member_id = %d AND waiver_id = %d",
                                    get_current_user_id(),
                                    $waiver_id
                                ));
                                if ($wpdb->last_error) {
                                    throw new Exception('Database query failed: ' . $wpdb->last_error);
                                }
                            ?>
                                <div>
                                    <div class="uk-card uk-card-default uk-card-body">
                                        <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                        <div><?php echo wp_kses_post(get_the_content()); ?></div>
                                        <?php if ($signed) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Signed', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-sign-waiver" data-waiver-id="<?php echo esc_attr($waiver_id); ?>"><?php _e('Sign Waiver', 'gymlite'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No waivers available.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("waivers_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in waivers_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading waivers.', 'gymlite') . '</p></div>';
        }
    }

    public function access_status_shortcode($atts) {
        try {
            gymlite_log("access_status_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to check access status.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
            $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';

            ob_start();
            ?>
            <div class="gymlite-access-status uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Access Status', 'gymlite'); ?></h2>
                    <p><?php echo esc_html(ucfirst($status)) . ' ' . __('access to facilities.', 'gymlite'); ?></p>
                    <button class="uk-button uk-button-primary gymlite-log-access"><?php _e('Log Access', 'gymlite'); ?></button>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("access_status_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in access_status_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading access status.', 'gymlite') . '</p></div>';
        }
    }

    public function progression_shortcode($atts) {
        try {
            gymlite_log("progression_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your progression.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_progression';
            $progressions = $wpdb->get_results($wpdb->prepare(
                "SELECT level, promoted_date FROM $table_name WHERE member_id = %d ORDER BY promoted_date DESC",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-progression uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Progression Tracking', 'gymlite'); ?></h2>
                    <?php if ($progressions && is_array($progressions)) : ?>
                        <table class="uk-table uk-table-striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Level', 'gymlite'); ?></th>
                                    <th><?php _e('Promoted Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressions as $progression) : ?>
                                    <tr>
                                        <td><?php echo esc_html($progression->level); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($progression->promoted_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No progression records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="uk-button uk-button-primary uk-button-small gymlite-promote-member" data-member-id="<?php echo esc_attr($member_id); ?>"><?php _e('Promote Member', 'gymlite'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("progression_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in progression_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading progression.', 'gymlite') . '</p></div>';
        }
    }

    public function notifications_shortcode($atts) {
        try {
            gymlite_log("notifications_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view notifications.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_comms_logs';
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT type, sent_date, status FROM $table_name WHERE member_id = %d ORDER BY sent_date DESC LIMIT 5",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-notifications uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Notifications', 'gymlite'); ?></h2>
                    <?php if ($notifications && is_array($notifications)) : ?>
                        <ul class="uk-list uk-list-divider">
                            <?php foreach ($notifications as $notification) : ?>
                                <li><?php echo esc_html(ucfirst($notification->type) . ' - ' . date('Y-m-d H:i', strtotime($notification->sent_date)) . ' (' . $notification->status . ')'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No notifications found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("notifications_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in notifications_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading notifications.', 'gymlite') . '</p></div>';
        }
    }

    public function enqueue_frontend_scripts() {
        try {
            gymlite_log("enqueue_frontend_scripts called at " . current_time('Y-m-d H:i:s'));
            if (!is_admin() && !wp_style_is('uikit', 'enqueued') && !wp_style_is('uikit', 'registered')) {
                wp_enqueue_style('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/css/uikit.min.css', [], '3.21.5');
                gymlite_log("UIkit CSS enqueued");
            }
            if (!is_admin() && !wp_script_is('uikit', 'enqueued') && !wp_script_is('uikit', 'registered')) {
                wp_enqueue_script('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit.min.js', ['jquery'], '3.21.5', true);
                wp_enqueue_script('uikit-icons', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit-icons.min.js', ['uikit'], '3.21.5', true);
                gymlite_log("UIkit JS enqueued");
            }

            wp_enqueue_style('gymlite-style', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION);
            gymlite_log("GymLite CSS enqueued");
            wp_enqueue_script('gymlite-frontend-script', GYMLITE_URL . 'assets/js/gymlite-frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
            gymlite_log("GymLite JS enqueued");

            $js = '
                jQuery(document).ready(function($) {
                    gymlite_log("Frontend JS loaded");
                    $(".gymlite-checkin").on("click", function(e) {
                        e.preventDefault();
                        var classId = $(this).data("class-id");
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_checkin", class_id: classId, nonce: gymlite_ajax.nonce},
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $(this).replaceWith(\'<span class="uk-label uk-label-success">Checked In</span>\'); 
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Check-in failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-lead-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_submit_lead&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-lead-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Lead submission failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-signup-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_signup&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                if (response.data.redirect) window.location.href = response.data.redirect;
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Signup failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-booking-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_book_class&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-booking-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Booking failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-sign-waiver").on("click", function(e) {
                        e.preventDefault();
                        var waiverId = $(this).data("waiver-id");
                        var signature = prompt("' . __('Enter your signature (e.g., initials)', 'gymlite') . '");
                        if (signature) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_sign_waiver", waiver_id: waiverId, signature: signature, nonce: gymlite_ajax.nonce},
                                success: function(response) { 
                                    UIkit.notification({message: response.data.message, status: "success"}); 
                                    $(e.target).replaceWith(\'<span class="uk-label uk-label-success">Signed</span>\');
                                },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Waiver signing failed", status: "danger"}); }
                            });
                        }
                    });
                    $(".gymlite-log-access").on("click", function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_log_access", nonce: gymlite_ajax.nonce},
                            success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Access log failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-promote-member").on("click", function(e) {
                        e.preventDefault();
                        var memberId = $(this).data("member-id");
                        var level = prompt("' . __('Enter new level (e.g., blue belt)', 'gymlite') . '");
                        if (level) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_promote_member", member_id: memberId, level: level, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Promotion failed", status: "danger"}); }
                            });
                        }
                    });
                });
            ';
            wp_add_inline_script('gymlite-frontend-script', $js);
            wp_localize_script('gymlite-frontend-script', 'gymlite_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gymlite_nonce'),
            ]);
            gymlite_log("GymLite frontend scripts localized");
        } catch (Exception $e) {
            gymlite_log("Error in enqueue_frontend_scripts: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to check in.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) {
            wp_send_json_error(['message' => __('You have already checked in for this class.', 'gymlite')]);
        }
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
    }

    public function handle_lead_submission() {
        check_ajax_referer('gymlite_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert(
            $table_name,
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'created_at' => current_time('mysql')],
            ['%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Lead submitted: $name, $email at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Thank you for your interest! We will contact you soon.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Lead submission failed.', 'gymlite')]);
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already registered.', 'gymlite')]);
        }
        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('subscriber');
            $member_id = wp_insert_post([
                'post_title' => $name,
                'post_type' => 'gymlite_member',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (!is_wp_error($member_id)) {
                update_post_meta($member_id, '_gymlite_member_email', $email);
                update_post_meta($member_id, '_gymlite_member_phone', $phone);
                update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
                wp_new_user_notification($user_id, null, 'both');
                gymlite_log("Signup for $name (ID $user_id) with membership $membership_type at " . current_time('Y-m-d H:i:s'));
                $login_url = get_permalink(get_option('gymlite_login_page_id'));
                wp_send_json_success(['message' => sprintf(__('Account created! Check your email for login details. <a href="%s">Log in</a>', 'gymlite'), esc_url($login_url)), 'redirect' => $login_url]);
            }
        }
        wp_send_json_error(['message' => __('Signup failed. Please try again.', 'gymlite')]);
    }

    public function handle_booking() {
        check_ajax_referer('gymlite_booking', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a class.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        // Placeholder for booking logic (e.g., check availability, send confirmation)
        // In a real implementation, check class capacity and update attendance or booking table
        gymlite_log("Booking for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Class booked successfully! Confirmation will be sent.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to sign a waiver.', 'gymlite')]);
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
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to log access.', 'gymlite')]);
        }
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }
    
// Continuation of class-gymlite-frontend.php (Part 4 of 5)

// [No additional class definition or constructor here - this is a continuation]

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
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-checkin" data-class-id="<?php echo esc_attr($class_id); ?>"><?php _e('Check In', 'gymlite'); ?></button>
                                        <?php elseif ($already_checked) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Checked In', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <span class="uk-text-muted"><?php _e('Check-in unavailable', 'gymlite'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            wp_reset_postdata();
            $output = ob_get_clean();
            gymlite_log("schedule_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in schedule_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading schedule. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function calendar_shortcode($atts) {
        try {
            gymlite_log("calendar_shortcode called at " . current_time('Y-m-d H:i:s'));
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
                throw new Exception('Failed to query classes for calendar: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-calendar uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body">
                        <?php if ($classes->have_posts()) : ?>
                            <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                                <?php while ($classes->have_posts()) : $classes->the_post();
                                    $class_id = get_the_ID();
                                    $class_date_str = get_post_meta($class_id, '_gymlite_class_date', true);
                                    if ($class_date_str) {
                                        $date = new DateTime($class_date_str, new DateTimeZone('Australia/Sydney'));
                                        $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true) ?: 60);
                                ?>
                                    <div>
                                        <div class="uk-card uk-card-secondary uk-card-hover">
                                            <div class="uk-card-header">
                                                <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                                <p class="uk-text-meta"><?php echo esc_html($date->format('Y-m-d H:i')); ?></p>
                                            </div>
                                            <div class="uk-card-body">
                                                <p><?php echo esc_html($duration) . ' ' . __('minutes', 'gymlite'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } endwhile; ?>
                            </div>
                        <?php else : ?>
                            <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("calendar_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in calendar_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading calendar. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function attendance_log_shortcode($atts) {
        try {
            gymlite_log("attendance_log_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your attendance.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_attendance';
            $attendances = $wpdb->get_results($wpdb->prepare(
                "SELECT a.attendance_date, c.post_title AS class_name 
                 FROM $table_name a 
                 JOIN {$wpdb->posts} c ON a.class_id = c.ID 
                 WHERE a.member_id = %d 
                 ORDER BY a.attendance_date DESC 
                 LIMIT 10",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-attendance uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Attendance Log', 'gymlite'); ?></h2>
                    <?php if ($attendances && is_array($attendances)) : ?>
                        <table class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th><?php _e('Class', 'gymlite'); ?></th>
                                    <th><?php _e('Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendances as $attendance) : ?>
                                    <tr>
                                        <td><?php echo esc_html($attendance->class_name); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($attendance->attendance_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No attendance records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("attendance_log_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in attendance_log_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading attendance log.', 'gymlite') . '</p></div>';
        }
    }

    public function member_profile_shortcode($atts) {
        try {
            gymlite_log("member_profile_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your profile.', 'gymlite') . '</p>';
            }

            $user_id = get_current_user_id();
            $member_posts = get_posts([
                'post_type' => 'gymlite_member',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);

            if (empty($member_posts) || is_wp_error($member_posts)) {
                return '<p class="uk-text-warning">' . __('No member profile found. Please contact support.', 'gymlite') . '</p>';
            }

            $member_id = $member_posts[0]->ID;
            $name = get_the_title($member_id);
            $email = get_post_meta($member_id, '_gymlite_member_email', true);
            $phone = get_post_meta($member_id, '_gymlite_member_phone', true) ?: __('N/A', 'gymlite');
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: __('N/A', 'gymlite');
            $level = get_post_meta($member_id, '_gymlite_bjj_belt', true) ?: __('N/A', 'gymlite');

            ob_start();
            ?>
            <div class="gymlite-member-profile uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Profile', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body uk-text-center">
                        <p><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($name); ?></p>
                        <p><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html($email); ?></p>
                        <p><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html($phone); ?></p>
                        <p><strong><?php _e('Membership Type:', 'gymlite'); ?></strong> <?php echo esc_html($membership_type); ?></p>
                        <p><strong><?php _e('Progression Level:', 'gymlite'); ?></strong> <?php echo esc_html($level); ?></p>
                        <a href="<?php echo esc_url(get_permalink(get_option('gymlite_user_data_page_id'))); ?>" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></a>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("member_profile_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in member_profile_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading profile.', 'gymlite') . '</p></div>';
        }
    }

    public function lead_form_shortcode($atts) {
        try {
            gymlite_log("lead_form_shortcode called at " . current_time('Y-m-d H:i:s'));
            ob_start();
            ?>
            <div class="gymlite-lead-form uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Become a Lead', 'gymlite'); ?></h2>
                    <form id="gymlite-lead-form" class="uk-form-stacked">
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-name"><?php _e('Name', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="text" name="name" id="lead-name" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-email"><?php _e('Email', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="email" name="email" id="lead-email" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-phone"><?php _e('Phone', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="tel" name="phone" id="lead-phone">
                            </div>
                        </div>
                        <div class="uk-margin">
                            <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit', 'gymlite'); ?></button>
                        </div>
                        <?php wp_nonce_field('gymlite_lead', 'nonce'); ?>
                    </form>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("lead_form_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in lead_form_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading lead form.', 'gymlite') . '</p></div>';
        }
    }

    public function signup_shortcode($atts) {
        try {
            gymlite_log("signup_shortcode called at " . current_time('Y-m-d H:i:s'));
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
            $output = ob_get_clean();
            gymlite_log("signup_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in signup_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading signup form.', 'gymlite') . '</p></div>';
        }
    }

    public function booking_shortcode($atts) {
        try {
            gymlite_log("booking_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to book a class.', 'gymlite') . '</p>';
            }

            $args = [
                'post_type' => 'gymlite_class',
                'posts_per_page' => -1,
                'meta_key' => '_gymlite_class_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => '_gymlite_class_date',
                        'value' => current_time('mysql'),
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                ],
                'post_status' => 'publish',
            ];
            $classes = new WP_Query($args);
            if (is_wp_error($classes)) {
                throw new Exception('Failed to query classes for booking: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-booking uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Book a Class', 'gymlite'); ?></h2>
                    <?php if ($classes->have_posts()) : ?>
                        <form id="gymlite-booking-form" class="uk-form-stacked">
                            <div class="uk-margin">
                                <label class="uk-form-label" for="class_id"><?php _e('Select Class', 'gymlite'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="class_id" id="class_id" required>
                                        <?php while ($classes->have_posts()) : $classes->the_post();
                                            $class_id = get_the_ID();
                                            $class_date = get_post_meta($class_id, '_gymlite_class_date', true);
                                            if ($class_date) {
                                                $date = new DateTime($class_date, new DateTimeZone('Australia/Sydney'));
                                        ?>
                                            <option value="<?php echo esc_attr($class_id); ?>"><?php the_title(); ?> (<?php echo esc_html($date->format('Y-m-d H:i')); ?>)</option>
                                        <?php } endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="uk-margin">
                                <button type="submit" class="uk-button uk-button-primary"><?php _e('Book Now', 'gymlite'); ?></button>
                            </div>
                            <?php wp_nonce_field('gymlite_booking', 'nonce'); ?>
                        </form>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No upcoming classes available for booking.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("booking_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in booking_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading booking form.', 'gymlite') . '</p></div>';
        }
    }

    public function referrals_shortcode($atts) {
        try {
            gymlite_log("referrals_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your referrals.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $referral_link = home_url('?ref=' . $member_id);
            global $wpdb;
            $referred_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gymlite_referrals WHERE referrer_id = %d", $member_id));

            ob_start();
            ?>
            <div class="gymlite-referrals uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Referrals', 'gymlite'); ?></h2>
                    <p><?php _e('Share your referral link to earn credits! You\'ll get $10 for each successful referral.', 'gymlite'); ?></p>
                    <div class="uk-margin">
                        <input class="uk-input" type="text" value="<?php echo esc_url($referral_link); ?>" readonly>
                    </div>
                    <p class="uk-text-meta"><?php echo sprintf(__('You have referred %d members.', 'gymlite'), $referred_count); ?></p>
                    <p class="uk-text-meta"><?php _e('Click the field to copy the link.', 'gymlite'); ?></p>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("referrals_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in referrals_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading referrals.', 'gymlite') . '</p></div>';
        }
    }

    public function portal_shortcode($atts) {
        try {
            gymlite_log("portal_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to access the portal.', 'gymlite') . '</p>';
            }

            ob_start();
            ?>
            <div class="gymlite-portal uk-section">
                <div class="uk-container">
                    <h1 class="uk-heading-large uk-text-center"><?php _e('GymLite Member Portal', 'gymlite'); ?></h1>
                    <div class="uk-child-width-1-3@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_member_profile]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_schedule]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_attendance_log]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_booking]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_referrals]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_waivers]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_access_status]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_progression]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_notifications]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("portal_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in portal_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading portal.', 'gymlite') . '</p></div>';
        }
    }

    public function waivers_shortcode($atts) {
        try {
            gymlite_log("waivers_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view waivers.', 'gymlite') . '</p>';
            }

            $args = ['post_type' => 'gymlite_waiver', 'posts_per_page' => -1, 'post_status' => 'publish'];
            $waivers = new WP_Query($args);
            if (is_wp_error($waivers)) {
                throw new Exception('Failed to query waivers: ' . $waivers->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-waivers uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Waivers', 'gymlite'); ?></h2>
                    <?php if ($waivers->have_posts()) : ?>
                        <div class="uk-child-width-1-2@m uk-grid-small" uk-grid>
                            <?php while ($waivers->have_posts()) : $waivers->the_post();
                                $waiver_id = get_the_ID();
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
                                $signed = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $table_name WHERE member_id = %d AND waiver_id = %d",
                                    get_current_user_id(),
                                    $waiver_id
                                ));
                                if ($wpdb->last_error) {
                                    throw new Exception('Database query failed: ' . $wpdb->last_error);
                                }
                            ?>
                                <div>
                                    <div class="uk-card uk-card-default uk-card-body">
                                        <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                        <div><?php echo wp_kses_post(get_the_content()); ?></div>
                                        <?php if ($signed) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Signed', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-sign-waiver" data-waiver-id="<?php echo esc_attr($waiver_id); ?>"><?php _e('Sign Waiver', 'gymlite'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No waivers available.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("waivers_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in waivers_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading waivers.', 'gymlite') . '</p></div>';
        }
    }

    public function access_status_shortcode($atts) {
        try {
            gymlite_log("access_status_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to check access status.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
            $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';

            ob_start();
            ?>
            <div class="gymlite-access-status uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Access Status', 'gymlite'); ?></h2>
                    <p><?php echo esc_html(ucfirst($status)) . ' ' . __('access to facilities.', 'gymlite'); ?></p>
                    <button class="uk-button uk-button-primary gymlite-log-access"><?php _e('Log Access', 'gymlite'); ?></button>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("access_status_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in access_status_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading access status.', 'gymlite') . '</p></div>';
        }
    }

    public function progression_shortcode($atts) {
        try {
            gymlite_log("progression_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your progression.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_progression';
            $progressions = $wpdb->get_results($wpdb->prepare(
                "SELECT level, promoted_date FROM $table_name WHERE member_id = %d ORDER BY promoted_date DESC",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-progression uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Progression Tracking', 'gymlite'); ?></h2>
                    <?php if ($progressions && is_array($progressions)) : ?>
                        <table class="uk-table uk-table-striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Level', 'gymlite'); ?></th>
                                    <th><?php _e('Promoted Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressions as $progression) : ?>
                                    <tr>
                                        <td><?php echo esc_html($progression->level); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($progression->promoted_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No progression records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="uk-button uk-button-primary uk-button-small gymlite-promote-member" data-member-id="<?php echo esc_attr($member_id); ?>"><?php _e('Promote Member', 'gymlite'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("progression_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in progression_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading progression.', 'gymlite') . '</p></div>';
        }
    }

    public function notifications_shortcode($atts) {
        try {
            gymlite_log("notifications_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view notifications.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_comms_logs';
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT type, sent_date, status FROM $table_name WHERE member_id = %d ORDER BY sent_date DESC LIMIT 5",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-notifications uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Notifications', 'gymlite'); ?></h2>
                    <?php if ($notifications && is_array($notifications)) : ?>
                        <ul class="uk-list uk-list-divider">
                            <?php foreach ($notifications as $notification) : ?>
                                <li><?php echo esc_html(ucfirst($notification->type) . ' - ' . date('Y-m-d H:i', strtotime($notification->sent_date)) . ' (' . $notification->status . ')'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No notifications found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("notifications_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in notifications_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading notifications.', 'gymlite') . '</p></div>';
        }
    }

    public function enqueue_frontend_scripts() {
        try {
            gymlite_log("enqueue_frontend_scripts called at " . current_time('Y-m-d H:i:s'));
            if (!is_admin() && !wp_style_is('uikit', 'enqueued') && !wp_style_is('uikit', 'registered')) {
                wp_enqueue_style('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/css/uikit.min.css', [], '3.21.5');
                gymlite_log("UIkit CSS enqueued");
            }
            if (!is_admin() && !wp_script_is('uikit', 'enqueued') && !wp_script_is('uikit', 'registered')) {
                wp_enqueue_script('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit.min.js', ['jquery'], '3.21.5', true);
                wp_enqueue_script('uikit-icons', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit-icons.min.js', ['uikit'], '3.21.5', true);
                gymlite_log("UIkit JS enqueued");
            }

            wp_enqueue_style('gymlite-style', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION);
            gymlite_log("GymLite CSS enqueued");
            wp_enqueue_script('gymlite-frontend-script', GYMLITE_URL . 'assets/js/gymlite-frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
            gymlite_log("GymLite JS enqueued");

            $js = '
                jQuery(document).ready(function($) {
                    gymlite_log("Frontend JS loaded");
                    $(".gymlite-checkin").on("click", function(e) {
                        e.preventDefault();
                        var classId = $(this).data("class-id");
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_checkin", class_id: classId, nonce: gymlite_ajax.nonce},
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $(this).replaceWith(\'<span class="uk-label uk-label-success">Checked In</span>\'); 
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Check-in failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-lead-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_submit_lead&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-lead-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Lead submission failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-signup-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_signup&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                if (response.data.redirect) window.location.href = response.data.redirect;
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Signup failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-booking-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_book_class&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-booking-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Booking failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-sign-waiver").on("click", function(e) {
                        e.preventDefault();
                        var waiverId = $(this).data("waiver-id");
                        var signature = prompt("' . __('Enter your signature (e.g., initials)', 'gymlite') . '");
                        if (signature) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_sign_waiver", waiver_id: waiverId, signature: signature, nonce: gymlite_ajax.nonce},
                                success: function(response) { 
                                    UIkit.notification({message: response.data.message, status: "success"}); 
                                    $(e.target).replaceWith(\'<span class="uk-label uk-label-success">Signed</span>\');
                                },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Waiver signing failed", status: "danger"}); }
                            });
                        }
                    });
                    $(".gymlite-log-access").on("click", function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_log_access", nonce: gymlite_ajax.nonce},
                            success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Access log failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-promote-member").on("click", function(e) {
                        e.preventDefault();
                        var memberId = $(this).data("member-id");
                        var level = prompt("' . __('Enter new level (e.g., blue belt)', 'gymlite') . '");
                        if (level) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_promote_member", member_id: memberId, level: level, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Promotion failed", status: "danger"}); }
                            });
                        }
                    });
                });
            ';
            wp_add_inline_script('gymlite-frontend-script', $js);
            wp_localize_script('gymlite-frontend-script', 'gymlite_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gymlite_nonce'),
            ]);
            gymlite_log("GymLite frontend scripts localized");
        } catch (Exception $e) {
            gymlite_log("Error in enqueue_frontend_scripts: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to check in.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) {
            wp_send_json_error(['message' => __('You have already checked in for this class.', 'gymlite')]);
        }
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
    }

    public function handle_lead_submission() {
        check_ajax_referer('gymlite_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert(
            $table_name,
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'created_at' => current_time('mysql')],
            ['%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Lead submitted: $name, $email at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Thank you for your interest! We will contact you soon.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Lead submission failed.', 'gymlite')]);
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already registered.', 'gymlite')]);
        }
        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('subscriber');
            $member_id = wp_insert_post([
                'post_title' => $name,
                'post_type' => 'gymlite_member',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (!is_wp_error($member_id)) {
                update_post_meta($member_id, '_gymlite_member_email', $email);
                update_post_meta($member_id, '_gymlite_member_phone', $phone);
                update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
                wp_new_user_notification($user_id, null, 'both');
                gymlite_log("Signup for $name (ID $user_id) with membership $membership_type at " . current_time('Y-m-d H:i:s'));
                $login_url = get_permalink(get_option('gymlite_login_page_id'));
                wp_send_json_success(['message' => sprintf(__('Account created! Check your email for login details. <a href="%s">Log in</a>', 'gymlite'), esc_url($login_url)), 'redirect' => $login_url]);
            }
        }
        wp_send_json_error(['message' => __('Signup failed. Please try again.', 'gymlite')]);
    }

    public function handle_booking() {
        check_ajax_referer('gymlite_booking', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a class.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        // Placeholder for booking logic (e.g., check availability, send confirmation)
        // In a real implementation, check class capacity and update attendance or booking table
        gymlite_log("Booking for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Class booked successfully! Confirmation will be sent.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to sign a waiver.', 'gymlite')]);
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
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to log access.', 'gymlite')]);
        }
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }

// Continuation of class-gymlite-frontend.php (Part 5 of 5)

// [No additional class definition or constructor here - this is a continuation]

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
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-checkin" data-class-id="<?php echo esc_attr($class_id); ?>"><?php _e('Check In', 'gymlite'); ?></button>
                                        <?php elseif ($already_checked) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Checked In', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <span class="uk-text-muted"><?php _e('Check-in unavailable', 'gymlite'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            wp_reset_postdata();
            $output = ob_get_clean();
            gymlite_log("schedule_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in schedule_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading schedule. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function calendar_shortcode($atts) {
        try {
            gymlite_log("calendar_shortcode called at " . current_time('Y-m-d H:i:s'));
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
                throw new Exception('Failed to query classes for calendar: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-calendar uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body">
                        <?php if ($classes->have_posts()) : ?>
                            <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                                <?php while ($classes->have_posts()) : $classes->the_post();
                                    $class_id = get_the_ID();
                                    $class_date_str = get_post_meta($class_id, '_gymlite_class_date', true);
                                    if ($class_date_str) {
                                        $date = new DateTime($class_date_str, new DateTimeZone('Australia/Sydney'));
                                        $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true) ?: 60);
                                ?>
                                    <div>
                                        <div class="uk-card uk-card-secondary uk-card-hover">
                                            <div class="uk-card-header">
                                                <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                                <p class="uk-text-meta"><?php echo esc_html($date->format('Y-m-d H:i')); ?></p>
                                            </div>
                                            <div class="uk-card-body">
                                                <p><?php echo esc_html($duration) . ' ' . __('minutes', 'gymlite'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } endwhile; ?>
                            </div>
                        <?php else : ?>
                            <p class="uk-text-center uk-text-muted"><?php _e('No classes scheduled at this time.', 'gymlite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("calendar_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in calendar_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading calendar. Please try again later.', 'gymlite') . '</p></div>';
        }
    }

    public function attendance_log_shortcode($atts) {
        try {
            gymlite_log("attendance_log_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your attendance.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_attendance';
            $attendances = $wpdb->get_results($wpdb->prepare(
                "SELECT a.attendance_date, c.post_title AS class_name 
                 FROM $table_name a 
                 JOIN {$wpdb->posts} c ON a.class_id = c.ID 
                 WHERE a.member_id = %d 
                 ORDER BY a.attendance_date DESC 
                 LIMIT 10",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-attendance uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Attendance Log', 'gymlite'); ?></h2>
                    <?php if ($attendances && is_array($attendances)) : ?>
                        <table class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th><?php _e('Class', 'gymlite'); ?></th>
                                    <th><?php _e('Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendances as $attendance) : ?>
                                    <tr>
                                        <td><?php echo esc_html($attendance->class_name); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($attendance->attendance_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-center uk-text-muted"><?php _e('No attendance records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("attendance_log_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in attendance_log_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading attendance log.', 'gymlite') . '</p></div>';
        }
    }

    public function member_profile_shortcode($atts) {
        try {
            gymlite_log("member_profile_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your profile.', 'gymlite') . '</p>';
            }

            $user_id = get_current_user_id();
            $member_posts = get_posts([
                'post_type' => 'gymlite_member',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);

            if (empty($member_posts) || is_wp_error($member_posts)) {
                return '<p class="uk-text-warning">' . __('No member profile found. Please contact support.', 'gymlite') . '</p>';
            }

            $member_id = $member_posts[0]->ID;
            $name = get_the_title($member_id);
            $email = get_post_meta($member_id, '_gymlite_member_email', true);
            $phone = get_post_meta($member_id, '_gymlite_member_phone', true) ?: __('N/A', 'gymlite');
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: __('N/A', 'gymlite');
            $level = get_post_meta($member_id, '_gymlite_bjj_belt', true) ?: __('N/A', 'gymlite');

            ob_start();
            ?>
            <div class="gymlite-member-profile uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Member Profile', 'gymlite'); ?></h2>
                    <div class="uk-card uk-card-default uk-card-body uk-text-center">
                        <p><strong><?php _e('Name:', 'gymlite'); ?></strong> <?php echo esc_html($name); ?></p>
                        <p><strong><?php _e('Email:', 'gymlite'); ?></strong> <?php echo esc_html($email); ?></p>
                        <p><strong><?php _e('Phone:', 'gymlite'); ?></strong> <?php echo esc_html($phone); ?></p>
                        <p><strong><?php _e('Membership Type:', 'gymlite'); ?></strong> <?php echo esc_html($membership_type); ?></p>
                        <p><strong><?php _e('Progression Level:', 'gymlite'); ?></strong> <?php echo esc_html($level); ?></p>
                        <a href="<?php echo esc_url(get_permalink(get_option('gymlite_user_data_page_id'))); ?>" class="uk-button uk-button-primary"><?php _e('Update Profile', 'gymlite'); ?></a>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("member_profile_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in member_profile_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading profile.', 'gymlite') . '</p></div>';
        }
    }

    public function lead_form_shortcode($atts) {
        try {
            gymlite_log("lead_form_shortcode called at " . current_time('Y-m-d H:i:s'));
            ob_start();
            ?>
            <div class="gymlite-lead-form uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium uk-text-center"><?php _e('Become a Lead', 'gymlite'); ?></h2>
                    <form id="gymlite-lead-form" class="uk-form-stacked">
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-name"><?php _e('Name', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="text" name="name" id="lead-name" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-email"><?php _e('Email', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="email" name="email" id="lead-email" required>
                            </div>
                        </div>
                        <div class="uk-margin">
                            <label class="uk-form-label" for="lead-phone"><?php _e('Phone', 'gymlite'); ?></label>
                            <div class="uk-form-controls">
                                <input class="uk-input" type="tel" name="phone" id="lead-phone">
                            </div>
                        </div>
                        <div class="uk-margin">
                            <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit', 'gymlite'); ?></button>
                        </div>
                        <?php wp_nonce_field('gymlite_lead', 'nonce'); ?>
                    </form>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("lead_form_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in lead_form_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading lead form.', 'gymlite') . '</p></div>';
        }
    }

    public function signup_shortcode($atts) {
        try {
            gymlite_log("signup_shortcode called at " . current_time('Y-m-d H:i:s'));
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
            $output = ob_get_clean();
            gymlite_log("signup_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in signup_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading signup form.', 'gymlite') . '</p></div>';
        }
    }

    public function booking_shortcode($atts) {
        try {
            gymlite_log("booking_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to book a class.', 'gymlite') . '</p>';
            }

            $args = [
                'post_type' => 'gymlite_class',
                'posts_per_page' => -1,
                'meta_key' => '_gymlite_class_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => '_gymlite_class_date',
                        'value' => current_time('mysql'),
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                ],
                'post_status' => 'publish',
            ];
            $classes = new WP_Query($args);
            if (is_wp_error($classes)) {
                throw new Exception('Failed to query classes for booking: ' . $classes->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-booking uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Book a Class', 'gymlite'); ?></h2>
                    <?php if ($classes->have_posts()) : ?>
                        <form id="gymlite-booking-form" class="uk-form-stacked">
                            <div class="uk-margin">
                                <label class="uk-form-label" for="class_id"><?php _e('Select Class', 'gymlite'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="class_id" id="class_id" required>
                                        <?php while ($classes->have_posts()) : $classes->the_post();
                                            $class_id = get_the_ID();
                                            $class_date = get_post_meta($class_id, '_gymlite_class_date', true);
                                            if ($class_date) {
                                                $date = new DateTime($class_date, new DateTimeZone('Australia/Sydney'));
                                        ?>
                                            <option value="<?php echo esc_attr($class_id); ?>"><?php the_title(); ?> (<?php echo esc_html($date->format('Y-m-d H:i')); ?>)</option>
                                        <?php } endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="uk-margin">
                                <button type="submit" class="uk-button uk-button-primary"><?php _e('Book Now', 'gymlite'); ?></button>
                            </div>
                            <?php wp_nonce_field('gymlite_booking', 'nonce'); ?>
                        </form>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No upcoming classes available for booking.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("booking_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in booking_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading booking form.', 'gymlite') . '</p></div>';
        }
    }

    public function referrals_shortcode($atts) {
        try {
            gymlite_log("referrals_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your referrals.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $referral_link = home_url('?ref=' . $member_id);
            global $wpdb;
            $referred_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gymlite_referrals WHERE referrer_id = %d", $member_id));

            ob_start();
            ?>
            <div class="gymlite-referrals uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Referrals', 'gymlite'); ?></h2>
                    <p><?php _e('Share your referral link to earn credits! You\'ll get $10 for each successful referral.', 'gymlite'); ?></p>
                    <div class="uk-margin">
                        <input class="uk-input" type="text" value="<?php echo esc_url($referral_link); ?>" readonly>
                    </div>
                    <p class="uk-text-meta"><?php echo sprintf(__('You have referred %d members.', 'gymlite'), $referred_count); ?></p>
                    <p class="uk-text-meta"><?php _e('Click the field to copy the link.', 'gymlite'); ?></p>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("referrals_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in referrals_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading referrals.', 'gymlite') . '</p></div>';
        }
    }

    public function portal_shortcode($atts) {
        try {
            gymlite_log("portal_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to access the portal.', 'gymlite') . '</p>';
            }

            ob_start();
            ?>
            <div class="gymlite-portal uk-section">
                <div class="uk-container">
                    <h1 class="uk-heading-large uk-text-center"><?php _e('GymLite Member Portal', 'gymlite'); ?></h1>
                    <div class="uk-child-width-1-3@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_member_profile]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_schedule]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_attendance_log]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_booking]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_referrals]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_waivers]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_access_status]'); ?>
                        </div>
                    </div>
                    <div class="uk-child-width-1-2@m uk-grid-match uk-margin-large-top" uk-grid>
                        <div>
                            <?php echo do_shortcode('[gymlite_progression]'); ?>
                        </div>
                        <div>
                            <?php echo do_shortcode('[gymlite_notifications]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("portal_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in portal_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading portal.', 'gymlite') . '</p></div>';
        }
    }

    public function waivers_shortcode($atts) {
        try {
            gymlite_log("waivers_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view waivers.', 'gymlite') . '</p>';
            }

            $args = ['post_type' => 'gymlite_waiver', 'posts_per_page' => -1, 'post_status' => 'publish'];
            $waivers = new WP_Query($args);
            if (is_wp_error($waivers)) {
                throw new Exception('Failed to query waivers: ' . $waivers->get_error_message());
            }

            ob_start();
            ?>
            <div class="gymlite-waivers uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Waivers', 'gymlite'); ?></h2>
                    <?php if ($waivers->have_posts()) : ?>
                        <div class="uk-child-width-1-2@m uk-grid-small" uk-grid>
                            <?php while ($waivers->have_posts()) : $waivers->the_post();
                                $waiver_id = get_the_ID();
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'gymlite_waivers_signed';
                                $signed = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $table_name WHERE member_id = %d AND waiver_id = %d",
                                    get_current_user_id(),
                                    $waiver_id
                                ));
                                if ($wpdb->last_error) {
                                    throw new Exception('Database query failed: ' . $wpdb->last_error);
                                }
                            ?>
                                <div>
                                    <div class="uk-card uk-card-default uk-card-body">
                                        <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                        <div><?php echo wp_kses_post(get_the_content()); ?></div>
                                        <?php if ($signed) : ?>
                                            <span class="uk-label uk-label-success"><?php _e('Signed', 'gymlite'); ?></span>
                                        <?php else : ?>
                                            <button class="uk-button uk-button-primary uk-button-small gymlite-sign-waiver" data-waiver-id="<?php echo esc_attr($waiver_id); ?>"><?php _e('Sign Waiver', 'gymlite'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No waivers available.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("waivers_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in waivers_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading waivers.', 'gymlite') . '</p></div>';
        }
    }

    public function access_status_shortcode($atts) {
        try {
            gymlite_log("access_status_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to check access status.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
            $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';

            ob_start();
            ?>
            <div class="gymlite-access-status uk-section">
                <div class="uk-container uk-container-small">
                    <h2 class="uk-heading-medium"><?php _e('Access Status', 'gymlite'); ?></h2>
                    <p><?php echo esc_html(ucfirst($status)) . ' ' . __('access to facilities.', 'gymlite'); ?></p>
                    <button class="uk-button uk-button-primary gymlite-log-access"><?php _e('Log Access', 'gymlite'); ?></button>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("access_status_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in access_status_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading access status.', 'gymlite') . '</p></div>';
        }
    }

    public function progression_shortcode($atts) {
        try {
            gymlite_log("progression_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view your progression.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_progression';
            $progressions = $wpdb->get_results($wpdb->prepare(
                "SELECT level, promoted_date FROM $table_name WHERE member_id = %d ORDER BY promoted_date DESC",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-progression uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Progression Tracking', 'gymlite'); ?></h2>
                    <?php if ($progressions && is_array($progressions)) : ?>
                        <table class="uk-table uk-table-striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Level', 'gymlite'); ?></th>
                                    <th><?php _e('Promoted Date', 'gymlite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressions as $progression) : ?>
                                    <tr>
                                        <td><?php echo esc_html($progression->level); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($progression->promoted_date))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No progression records found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="uk-button uk-button-primary uk-button-small gymlite-promote-member" data-member-id="<?php echo esc_attr($member_id); ?>"><?php _e('Promote Member', 'gymlite'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("progression_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in progression_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading progression.', 'gymlite') . '</p></div>';
        }
    }

    public function notifications_shortcode($atts) {
        try {
            gymlite_log("notifications_shortcode called at " . current_time('Y-m-d H:i:s'));
            if (!is_user_logged_in()) {
                return '<p class="uk-text-danger">' . __('Please log in to view notifications.', 'gymlite') . '</p>';
            }

            $member_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_comms_logs';
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT type, sent_date, status FROM $table_name WHERE member_id = %d ORDER BY sent_date DESC LIMIT 5",
                $member_id
            ));
            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            ob_start();
            ?>
            <div class="gymlite-notifications uk-section">
                <div class="uk-container">
                    <h2 class="uk-heading-medium"><?php _e('Notifications', 'gymlite'); ?></h2>
                    <?php if ($notifications && is_array($notifications)) : ?>
                        <ul class="uk-list uk-list-divider">
                            <?php foreach ($notifications as $notification) : ?>
                                <li><?php echo esc_html(ucfirst($notification->type) . ' - ' . date('Y-m-d H:i', strtotime($notification->sent_date)) . ' (' . $notification->status . ')'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="uk-text-muted"><?php _e('No notifications found.', 'gymlite'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            gymlite_log("notifications_shortcode completed with output length: " . strlen($output));
            return $output;
        } catch (Exception $e) {
            gymlite_log("Error in notifications_shortcode: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
            return '<div class="uk-alert-danger uk-margin" uk-alert><p>' . __('Error loading notifications.', 'gymlite') . '</p></div>';
        }
    }

    public function enqueue_frontend_scripts() {
        try {
            gymlite_log("enqueue_frontend_scripts called at " . current_time('Y-m-d H:i:s'));
            if (!is_admin() && !wp_style_is('uikit', 'enqueued') && !wp_style_is('uikit', 'registered')) {
                wp_enqueue_style('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/css/uikit.min.css', [], '3.21.5');
                gymlite_log("UIkit CSS enqueued");
            }
            if (!is_admin() && !wp_script_is('uikit', 'enqueued') && !wp_script_is('uikit', 'registered')) {
                wp_enqueue_script('uikit', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit.min.js', ['jquery'], '3.21.5', true);
                wp_enqueue_script('uikit-icons', 'https://cdn.jsdelivr.net/npm/uikit@3.21.5/dist/js/uikit-icons.min.js', ['uikit'], '3.21.5', true);
                gymlite_log("UIkit JS enqueued");
            }

            wp_enqueue_style('gymlite-style', GYMLITE_URL . 'assets/css/gymlite.css', [], GYMLITE_VERSION);
            gymlite_log("GymLite CSS enqueued");
            wp_enqueue_script('gymlite-frontend-script', GYMLITE_URL . 'assets/js/gymlite-frontend.js', ['jquery', 'uikit'], GYMLITE_VERSION, true);
            gymlite_log("GymLite JS enqueued");

            $js = '
                jQuery(document).ready(function($) {
                    gymlite_log("Frontend JS loaded");
                    $(".gymlite-checkin").on("click", function(e) {
                        e.preventDefault();
                        var classId = $(this).data("class-id");
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_checkin", class_id: classId, nonce: gymlite_ajax.nonce},
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $(this).replaceWith(\'<span class="uk-label uk-label-success">Checked In</span>\'); 
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Check-in failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-lead-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_submit_lead&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-lead-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Lead submission failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-signup-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_signup&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                if (response.data.redirect) window.location.href = response.data.redirect;
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Signup failed", status: "danger"}); }
                        });
                    });
                    $("#gymlite-booking-form").on("submit", function(e) {
                        e.preventDefault();
                        var formData = $(this).serialize();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: formData + "&action=gymlite_book_class&nonce=" + gymlite_ajax.nonce,
                            success: function(response) { 
                                UIkit.notification({message: response.data.message, status: "success"}); 
                                $("#gymlite-booking-form")[0].reset();
                            },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Booking failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-sign-waiver").on("click", function(e) {
                        e.preventDefault();
                        var waiverId = $(this).data("waiver-id");
                        var signature = prompt("' . __('Enter your signature (e.g., initials)', 'gymlite') . '");
                        if (signature) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_sign_waiver", waiver_id: waiverId, signature: signature, nonce: gymlite_ajax.nonce},
                                success: function(response) { 
                                    UIkit.notification({message: response.data.message, status: "success"}); 
                                    $(e.target).replaceWith(\'<span class="uk-label uk-label-success">Signed</span>\');
                                },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Waiver signing failed", status: "danger"}); }
                            });
                        }
                    });
                    $(".gymlite-log-access").on("click", function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: gymlite_ajax.ajax_url,
                            type: "POST",
                            data: {action: "gymlite_log_access", nonce: gymlite_ajax.nonce},
                            success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                            error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Access log failed", status: "danger"}); }
                        });
                    });
                    $(".gymlite-promote-member").on("click", function(e) {
                        e.preventDefault();
                        var memberId = $(this).data("member-id");
                        var level = prompt("' . __('Enter new level (e.g., blue belt)', 'gymlite') . '");
                        if (level) {
                            $.ajax({
                                url: gymlite_ajax.ajax_url,
                                type: "POST",
                                data: {action: "gymlite_promote_member", member_id: memberId, level: level, nonce: gymlite_ajax.nonce},
                                success: function(response) { UIkit.notification({message: response.data.message, status: "success"}); },
                                error: function(xhr) { UIkit.notification({message: xhr.responseJSON?.data?.message || "Promotion failed", status: "danger"}); }
                            });
                        }
                    });
                });
            ';
            wp_add_inline_script('gymlite-frontend-script', $js);
            wp_localize_script('gymlite-frontend-script', 'gymlite_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gymlite_nonce'),
            ]);
            gymlite_log("GymLite frontend scripts localized");
        } catch (Exception $e) {
            gymlite_log("Error in enqueue_frontend_scripts: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function handle_checkin() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to check in.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE member_id = %d AND class_id = %d", $member_id, $class_id));
        if ($exists) {
            wp_send_json_error(['message' => __('You have already checked in for this class.', 'gymlite')]);
        }
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'class_id' => $class_id, 'attendance_date' => current_time('mysql')],
            ['%d', '%d', '%s']
        );
        if ($result !== false) {
            gymlite_log("Check-in for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Check-in successful.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Check-in failed.', 'gymlite')]);
    }

    public function handle_lead_submission() {
        check_ajax_referer('gymlite_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert(
            $table_name,
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'created_at' => current_time('mysql')],
            ['%s', '%s', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Lead submitted: $name, $email at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Thank you for your interest! We will contact you soon.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Lead submission failed.', 'gymlite')]);
    }

    public function handle_signup() {
        check_ajax_referer('gymlite_signup', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $membership_type = sanitize_text_field($_POST['membership_type']);
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gymlite')]);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email is already registered.', 'gymlite')]);
        }
        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('subscriber');
            $member_id = wp_insert_post([
                'post_title' => $name,
                'post_type' => 'gymlite_member',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (!is_wp_error($member_id)) {
                update_post_meta($member_id, '_gymlite_member_email', $email);
                update_post_meta($member_id, '_gymlite_member_phone', $phone);
                update_post_meta($member_id, '_gymlite_membership_type', $membership_type);
                wp_new_user_notification($user_id, null, 'both');
                gymlite_log("Signup for $name (ID $user_id) with membership $membership_type at " . current_time('Y-m-d H:i:s'));
                $login_url = get_permalink(get_option('gymlite_login_page_id'));
                wp_send_json_success(['message' => sprintf(__('Account created! Check your email for login details. <a href="%s">Log in</a>', 'gymlite'), esc_url($login_url)), 'redirect' => $login_url]);
            }
        }
        wp_send_json_error(['message' => __('Signup failed. Please try again.', 'gymlite')]);
    }

    public function handle_booking() {
        check_ajax_referer('gymlite_booking', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a class.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $member_id = get_current_user_id();
        // Placeholder for booking logic (e.g., check availability, send confirmation)
        // In a real implementation, check class capacity and update attendance or booking table
        gymlite_log("Booking for member ID $member_id into class ID $class_id at " . current_time('Y-m-d H:i:s'));
        wp_send_json_success(['message' => __('Class booked successfully! Confirmation will be sent.', 'gymlite')]);
    }

    public function handle_sign_waiver() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to sign a waiver.', 'gymlite')]);
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
        if ($result !== false) {
            gymlite_log("Waiver ID $waiver_id signed for member ID $member_id at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Waiver signed successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Waiver signing failed.', 'gymlite')]);
    }

    public function handle_log_access() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to log access.', 'gymlite')]);
        }
        $member_id = get_current_user_id();
        $membership_type = get_post_meta($member_id, '_gymlite_membership_type', true) ?: 'trial';
        $status = ($membership_type && $membership_type !== 'trial') ? 'granted' : 'denied';
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_access_logs';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'access_time' => current_time('mysql'), 'status' => $status],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            gymlite_log("Access logged for member ID $member_id with status $status at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Access logged successfully.', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Access log failed.', 'gymlite')]);
    }

    public function handle_promote() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        }
        $member_id = intval($_POST['member_id']);
        $level = sanitize_text_field($_POST['level']);
        if (empty($level)) {
            wp_send_json_error(['message' => __('Level is required.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_progression';
        $result = $wpdb->insert(
            $table_name,
            ['member_id' => $member_id, 'level' => $level, 'promoted_date' => current_time('mysql')],
            ['%d', '%s', '%s']
        );
        if ($result !== false) {
            update_post_meta($member_id, '_gymlite_bjj_belt', $level);
            gymlite_log("Member ID $member_id promoted to $level at " . current_time('Y-m-d H:i:s'));
            wp_send_json_success(['message' => __('Member promoted to ' . $level . '!', 'gymlite')]);
        }
        wp_send_json_error(['message' => __('Promotion failed.', 'gymlite')]);
    }
}