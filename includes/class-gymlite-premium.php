<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Premium {
    public function __construct() {
        try {
            gymlite_log("GymLite_Premium constructor started at " . current_time('Y-m-d H:i:s'));
            if (self::is_premium_active()) {
                add_action('init', [$this, 'init_stripe']);
                add_action('gymlite_daily_cron', [$this, 'process_recurring_billings']);
                add_action('wp_ajax_gymlite_process_pos', [$this, 'handle_pos_payment']);
                add_action('wp_ajax_gymlite_zoom_create_meeting', [$this, 'create_zoom_meeting']);
                add_action('gymlite_daily_cron', [$this, 'google_calendar_sync']);
                add_action('wp_ajax_gymlite_track_referral', [$this, 'handle_referral_tracking']);
                add_action('wp_ajax_gymlite_manage_inventory', [$this, 'handle_inventory_management']);
                add_action('wp_ajax_gymlite_generate_report', [$this, 'generate_detailed_report']);
            }
            gymlite_log("GymLite_Premium constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Premium: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public static function is_premium_active() {
        $license_key = get_option('gymlite_license_key', '');
        $enable_premium_mode = get_option('gymlite_enable_premium_mode', 'no');
        // In production, validate license_key against a server; for now, check if set or test mode
        return ($license_key === 'valid_license_key' || $enable_premium_mode === 'yes');
    }

    public function init_stripe() {
        $stripe_key = get_option('gymlite_stripe_key', '');
        if (empty($stripe_key)) {
            gymlite_log("Stripe key missing; premium billing disabled.");
            return;
        }
        if (file_exists(GYMLITE_DIR . 'vendor/stripe/stripe-php/init.php')) {
            require_once GYMLITE_DIR . 'vendor/stripe/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($stripe_key);
            gymlite_log("Stripe initialized successfully.");
        } else {
            gymlite_log("Stripe library missing; download from https://github.com/stripe/stripe-php.");
        }
    }

    public function process_recurring_billings() {
        if (!self::is_premium_active()) return;
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $due_payments = $wpdb->get_results("SELECT * FROM $table_name WHERE next_billing_date <= CURDATE() AND status = 'active'");
        foreach ($due_payments as $payment) {
            try {
                $charge = \Stripe\Charge::create([
                    'amount' => $payment->amount * 100, // in cents
                    'currency' => 'usd',
                    'description' => "Recurring billing for member ID {$payment->member_id}",
                    'source' => 'tok_visa', // Replace with actual customer token in production
                ]);
                $wpdb->insert(
                    $wpdb->prefix . 'gymlite_payments',
                    [
                        'member_id' => $payment->member_id,
                        'amount' => $payment->amount,
                        'payment_date' => current_time('mysql'),
                        'status' => 'paid',
                        'transaction_id' => $charge->id
                    ]
                );
                $next_date = date('Y-m-d', strtotime($payment->next_billing_date . ' +1 month'));
                $wpdb->update($table_name, ['next_billing_date' => $next_date], ['id' => $payment->id]);
                gymlite_log("Processed recurring billing for member ID {$payment->member_id}, transaction {$charge->id}");
            } catch (\Stripe\Exception\CardException $e) {
                $wpdb->update($table_name, ['status' => 'overdue'], ['id' => $payment->id]);
                gymlite_log("Billing failed for member ID {$payment->member_id}: " . $e->getMessage());
            }
        }
    }

    public function handle_pos_payment() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!self::is_premium_active()) wp_send_json_error(['message' => __('Premium required.', 'gymlite')]);
        $amount = floatval($_POST['amount']);
        $member_id = intval($_POST['member_id']);
        $description = sanitize_text_field($_POST['description']);
        try {
            $charge = \Stripe\Charge::create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'description' => $description,
                'source' => 'tok_visa', // Replace with frontend token
            ]);
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'gymlite_payments',
                [
                    'member_id' => $member_id,
                    'amount' => $amount,
                    'payment_date' => current_time('mysql'),
                    'status' => 'paid',
                    'transaction_id' => $charge->id
                ]
            );
            gymlite_log("POS payment processed for member ID $member_id, transaction {$charge->id}");
            wp_send_json_success(['message' => __('Payment successful!', 'gymlite')]);
        } catch (Exception $e) {
            gymlite_log("POS payment failed: " . $e->getMessage());
            wp_send_json_error(['message' => __('Payment failed: ' . $e->getMessage(), 'gymlite')]);
        }
    }

    public function create_zoom_meeting() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!self::is_premium_active()) wp_send_json_error(['message' => __('Premium required.', 'gymlite')]);
        $api_key = get_option('gymlite_zoom_api_key', '');
        $api_secret = get_option('gymlite_zoom_api_secret', '');
        if (empty($api_key) || empty($api_secret)) {
            wp_send_json_error(['message' => __('Zoom credentials missing.', 'gymlite')]);
        }
        $class_id = intval($_POST['class_id']);
        $topic = get_the_title($class_id);
        $start_time = get_post_meta($class_id, '_gymlite_class_date', true) . 'T' . '00:00:00'; // Adjust time
        $duration = intval(get_post_meta($class_id, '_gymlite_class_duration', true));
        $payload = [
            'iss' => $api_key,
            'exp' => time() + 3600,
        ];
        $jwt = $this->generate_jwt($payload, $api_secret);
        $response = wp_remote_post('https://api.zoom.us/v2/users/me/meetings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $jwt,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'topic' => $topic,
                'type' => 2, // Scheduled meeting
                'start_time' => $start_time,
                'duration' => $duration,
                'timezone' => 'UTC',
            ]),
        ]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        update_post_meta($class_id, '_gymlite_zoom_link', $body['join_url']);
        gymlite_log("Zoom meeting created for class ID $class_id");
        wp_send_json_success(['join_url' => $body['join_url']]);
    }

    private function generate_jwt($payload, $secret) {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        return "$header.$payload.$signature";
    }

    public function google_calendar_sync() {
        if (!self::is_premium_active()) return;
        if (!file_exists(GYMLITE_DIR . 'client_secret.json')) {
            gymlite_log("Google client_secret.json missing; sync skipped.");
            return;
        }
        require_once GYMLITE_DIR . 'vendor/autoload.php'; // Assume Google API client in vendor
        $client = new Google_Client();
        $client->setAuthConfig(GYMLITE_DIR . 'client_secret.json');
        $client->addScope(Google_Service_Calendar::CALENDAR);
        $service = new Google_Service_Calendar($client);
        // Fetch classes and sync to calendar
        $classes = new WP_Query(['post_type' => 'gymlite_class', 'posts_per_page' => -1]);
        while ($classes->have_posts()) {
            $classes->the_post();
            $event = new Google_Service_Calendar_Event([
                'summary' => get_the_title(),
                'start' => ['dateTime' => get_post_meta(get_the_ID(), '_gymlite_class_date', true) . 'T00:00:00'],
                'end' => ['dateTime' => get_post_meta(get_the_ID(), '_gymlite_class_date', true) . 'T01:00:00'], // Adjust based on duration
            ]);
            $service->events->insert('primary', $event);
        }
        wp_reset_postdata();
        gymlite_log("Google Calendar sync completed.");
    }

    public function handle_referral_tracking() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!self::is_premium_active()) wp_send_json_error(['message' => __('Premium required.', 'gymlite')]);
        $referrer_id = get_current_user_id();
        $referred_email = sanitize_email($_POST['referred_email']);
        if (email_exists($referred_email)) {
            wp_send_json_error(['message' => __('User already exists.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_referrals';
        $wpdb->insert($table_name, [
            'referrer_id' => $referrer_id,
            'referred_email' => $referred_email,
            'referred_date' => current_time('mysql'),
            'status' => 'pending'
        ]);
        gymlite_log("Referral tracked from $referrer_id for $referred_email");
        wp_send_json_success(['message' => __('Referral submitted!', 'gymlite')]);
    }

    public function handle_inventory_management() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        if (!self::is_premium_active()) wp_send_json_error(['message' => __('Premium required.', 'gymlite')]);
        $action = sanitize_text_field($_POST['inventory_action']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_inventory';
        if ($action === 'update') {
            $wpdb->update($table_name, ['quantity' => $quantity], ['id' => $product_id]);
            gymlite_log("Inventory updated for product ID $product_id to $quantity");
            wp_send_json_success(['message' => __('Inventory updated.', 'gymlite')]);
        } elseif ($action === 'add') {
            $product_name = sanitize_text_field($_POST['product_name']);
            $price = floatval($_POST['price']);
            $wpdb->insert($table_name, ['product_name' => $product_name, 'quantity' => $quantity, 'price' => $price]);
            gymlite_log("New inventory item added: $product_name");
            wp_send_json_success(['message' => __('Item added.', 'gymlite')]);
        }
    }

    public function generate_detailed_report() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        if (!self::is_premium_active()) wp_send_json_error(['message' => __('Premium required.', 'gymlite')]);
        $report_type = sanitize_text_field($_POST['report_type']);
        global $wpdb;
        $data = []; // Query based on type, e.g., payments, attendance
        if ($report_type === 'payments') {
            $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_payments ORDER BY payment_date DESC");
        }
        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="gymlite-report-' . $report_type . '.csv"');
        $output = fopen('php://output', 'w');
        // Output headers and data
        fputcsv($output, ['ID', 'Member ID', 'Amount', 'Date', 'Status']); // Example for payments
        foreach ($data as $row) {
            fputcsv($output, [$row->id, $row->member_id, $row->amount, $row->payment_date, $row->status]);
        }
        gymlite_log("Detailed report generated for $report_type");
        exit;
    }
}
?>