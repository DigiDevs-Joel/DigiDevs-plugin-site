<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Scheduling {
    public function __construct() {
        try {
            gymlite_log("GymLite_Scheduling feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post_gymlite_class', [$this, 'save_meta']);
            add_shortcode('gymlite_schedule', [$this, 'schedule_shortcode']);
            add_shortcode('gymlite_calendar', [$this, 'calendar_shortcode']);
            add_action('wp_ajax_gymlite_create_class', [$this, 'handle_create_class']);
            add_action('wp_ajax_gymlite_update_class', [$this, 'handle_update_class']);
            add_action('wp_ajax_gymlite_delete_class', [$this, 'handle_delete_class']);
            add_action('wp_ajax_gymlite_get_classes', [$this, 'handle_get_classes']);
            gymlite_log("GymLite_Scheduling feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Scheduling: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Classes & Scheduling', 'gymlite'),
            __('Classes', 'gymlite'),
            'manage_options',
            'edit.php?post_type=gymlite_class'
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'gymlite_class_details',
            __('Class Details', 'gymlite'),
            [$this, 'class_meta_box'],
            'gymlite_class',
            'normal',
            'high'
        );
    }

    public function class_meta_box($post) {
        wp_nonce_field('gymlite_class_meta', 'gymlite_class_nonce');
        $date = get_post_meta($post->ID, '_gymlite_class_date', true);
        $time = get_post_meta($post->ID, '_gymlite_class_time', true);
        $duration = get_post_meta($post->ID, '_gymlite_class_duration', true);
        $instructor = get_post_meta($post->ID, '_gymlite_class_instructor', true);
        $capacity = get_post_meta($post->ID, '_gymlite_class_capacity', true);
        $zoom_link = get_post_meta($post->ID, '_gymlite_zoom_link', true);
        ?>
        <p>
            <label for="gymlite_class_date"><?php _e('Date', 'gymlite'); ?></label>
            <input type="date" id="gymlite_class_date" name="gymlite_class_date" value="<?php echo esc_attr($date); ?>" class="widefat" required>
        </p>
        <p>
            <label for="gymlite_class_time"><?php _e('Start Time', 'gymlite'); ?></label>
            <input type="time" id="gymlite_class_time" name="gymlite_class_time" value="<?php echo esc_attr($time); ?>" class="widefat" required>
        </p>
        <p>
            <label for="gymlite_class_duration"><?php _e('Duration (minutes)', 'gymlite'); ?></label>
            <input type="number" id="gymlite_class_duration" name="gymlite_class_duration" value="<?php echo esc_attr($duration); ?>" class="widefat" min="1" required>
        </p>
        <p>
            <label for="gymlite_class_instructor"><?php _e('Instructor', 'gymlite'); ?></label>
            <input type="text" id="gymlite_class_instructor" name="gymlite_class_instructor" value="<?php echo esc_attr($instructor); ?>" class="widefat">
        </p>
        <p>
            <label for="gymlite_class_capacity"><?php _e('Capacity', 'gymlite'); ?></label>
            <input type="number" id="gymlite_class_capacity" name="gymlite_class_capacity" value="<?php echo esc_attr($capacity); ?>" class="widefat" min="1">
        </p>
        <p>
            <label for="gymlite_zoom_link"><?php _e('Zoom Link (Premium)', 'gymlite'); ?></label>
            <input type="url" id="gymlite_zoom_link" name="gymlite_zoom_link" value="<?php echo esc_attr($zoom_link); ?>" class="widefat">
        </p>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['gymlite_class_nonce']) || !wp_verify_nonce($_POST['gymlite_class_nonce'], 'gymlite_class_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_gymlite_class_date', sanitize_text_field($_POST['gymlite_class_date']));
        update_post_meta($post_id, '_gymlite_class_time', sanitize_text_field($_POST['gymlite_class_time']));
        update_post_meta($post_id, '_gymlite_class_duration', intval($_POST['gymlite_class_duration']));
        update_post_meta($post_id, '_gymlite_class_instructor', sanitize_text_field($_POST['gymlite_class_instructor']));
        update_post_meta($post_id, '_gymlite_class_capacity', intval($_POST['gymlite_class_capacity']));
        update_post_meta($post_id, '_gymlite_zoom_link', esc_url_raw($_POST['gymlite_zoom_link']));
        gymlite_log("Class meta saved for post ID $post_id");
    }

    public function schedule_shortcode($atts) {
        $args = shortcode_atts([
            'limit' => -1,
            'order' => 'ASC',
        ], $atts);

        $query_args = [
            'post_type' => 'gymlite_class',
            'posts_per_page' => intval($args['limit']),
            'meta_key' => '_gymlite_class_date',
            'orderby' => 'meta_value',
            'order' => $args['order'],
            'post_status' => 'publish',
        ];

        $classes = new WP_Query($query_args);

        ob_start();
        ?>
        <div class="gymlite-schedule uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Schedule', 'gymlite'); ?></h2>
                <?php if ($classes->have_posts()) : ?>
                    <div class="uk-grid-match uk-child-width-1-3@m" uk-grid>
                        <?php while ($classes->have_posts()) : $classes->the_post(); ?>
                            <div>
                                <div class="uk-card uk-card-default uk-card-body">
                                    <h3 class="uk-card-title"><?php the_title(); ?></h3>
                                    <p><strong><?php _e('Date:', 'gymlite'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_gymlite_class_date', true)); ?></p>
                                    <p><strong><?php _e('Time:', 'gymlite'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_gymlite_class_time', true)); ?></p>
                                    <p><strong><?php _e('Duration:', 'gymlite'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_gymlite_class_duration', true)); ?> <?php _e('minutes', 'gymlite'); ?></p>
                                    <p><strong><?php _e('Instructor:', 'gymlite'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_gymlite_class_instructor', true)); ?></p>
                                    <a href="<?php the_permalink(); ?>" class="uk-button uk-button-primary"><?php _e('View Details', 'gymlite'); ?></a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <p class="uk-text-center"><?php _e('No classes scheduled.', 'gymlite'); ?></p>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function calendar_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-calendar uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Class Calendar', 'gymlite'); ?></h2>
                <div id="gymlite-calendar-container"></div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Fetch classes and render calendar (e.g., using FullCalendar library)
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gymlite_get_classes',
                        nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Assume FullCalendar is enqueued
                            var calendarEl = document.getElementById('gymlite-calendar-container');
                            var calendar = new FullCalendar.Calendar(calendarEl, {
                                initialView: 'dayGridMonth',
                                events: response.data.classes.map(function(cls) {
                                    return {
                                        title: cls.title,
                                        start: cls.date + 'T' + cls.time,
                                        url: cls.permalink
                                    };
                                })
                            });
                            calendar.render();
                        }
                    }
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_create_class() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $title = sanitize_text_field($_POST['title']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $duration = intval($_POST['duration']);
        $instructor = sanitize_text_field($_POST['instructor']);
        $capacity = intval($_POST['capacity']);
        $zoom_link = esc_url_raw($_POST['zoom_link']);

        if (empty($title) || empty($date) || empty($time) || $duration <= 0) wp_send_json_error(['message' => __('Invalid class data.', 'gymlite')]);

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'gymlite_class',
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) wp_send_json_error(['message' => __('Failed to create class.', 'gymlite')]);

        update_post_meta($post_id, '_gymlite_class_date', $date);
        update_post_meta($post_id, '_gymlite_class_time', $time);
        update_post_meta($post_id, '_gymlite_class_duration', $duration);
        update_post_meta($post_id, '_gymlite_class_instructor', $instructor);
        update_post_meta($post_id, '_gymlite_class_capacity', $capacity);
        update_post_meta($post_id, '_gymlite_zoom_link', $zoom_link);

        gymlite_log("New class created: ID $post_id");
        wp_send_json_success(['message' => __('Class created successfully.', 'gymlite')]);
    }

    public function handle_update_class() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $post_id = intval($_POST['post_id']);
        $title = sanitize_text_field($_POST['title']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $duration = intval($_POST['duration']);
        $instructor = sanitize_text_field($_POST['instructor']);
        $capacity = intval($_POST['capacity']);
        $zoom_link = esc_url_raw($_POST['zoom_link']);

        if (empty($post_id) || empty($title) || empty($date) || empty($time) || $duration <= 0) wp_send_json_error(['message' => __('Invalid class data.', 'gymlite')]);

        wp_update_post(['ID' => $post_id, 'post_title' => $title]);

        update_post_meta($post_id, '_gymlite_class_date', $date);
        update_post_meta($post_id, '_gymlite_class_time', $time);
        update_post_meta($post_id, '_gymlite_class_duration', $duration);
        update_post_meta($post_id, '_gymlite_class_instructor', $instructor);
        update_post_meta($post_id, '_gymlite_class_capacity', $capacity);
        update_post_meta($post_id, '_gymlite_zoom_link', $zoom_link);

        gymlite_log("Class updated: ID $post_id");
        wp_send_json_success(['message' => __('Class updated successfully.', 'gymlite')]);
    }

    public function handle_delete_class() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $post_id = intval($_POST['post_id']);
        if (wp_delete_post($post_id, true)) {
            gymlite_log("Class deleted: ID $post_id");
            wp_send_json_success(['message' => __('Class deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete class.', 'gymlite')]);
        }
    }

    public function handle_get_classes() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        $args = [
            'post_type' => 'gymlite_class',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $classes = get_posts($args);
        $data = [];
        foreach ($classes as $class) {
            $data[] = [
                'id' => $class->ID,
                'title' => $class->post_title,
                'date' => get_post_meta($class->ID, '_gymlite_class_date', true),
                'time' => get_post_meta($class->ID, '_gymlite_class_time', true),
                'duration' => get_post_meta($class->ID, '_gymlite_class_duration', true),
                'instructor' => get_post_meta($class->ID, '_gymlite_class_instructor', true),
                'capacity' => get_post_meta($class->ID, '_gymlite_class_capacity', true),
                'zoom_link' => get_post_meta($class->ID, '_gymlite_zoom_link', true),
                'permalink' => get_permalink($class->ID),
            ];
        }
        gymlite_log("Classes data retrieved via AJAX");
        wp_send_json_success(['classes' => $data]);
    }
}
?>