<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Premium {
    public static function is_premium_active(): bool {
        $premium_mode = get_option('gymlite_enable_premium_mode', 'no') === 'yes';
        if ($premium_mode) {
            return true;
        }
        $license_key = get_option('gymlite_license_key', '');
        return !empty($license_key) && $license_key === 'valid_license_key'; // Replace with real validation API call
    }

    public static function billing(): void {
        if (!self::is_premium_active()) {
            return;
        }
        $stripe_key = get_option('gymlite_stripe_key', '');
        if (empty($stripe_key)) {
            gymlite_log('Stripe key missing for billing at ' . current_time('Y-m-d H:i:s'));
            return;
        }
        try {
            if (!file_exists(GYMLITE_DIR . 'vendor/stripe/stripe-php/init.php')) {
                gymlite_log('Stripe library missing at ' . current_time('Y-m-d H:i:s'));
                return;
            }
            require_once GYMLITE_DIR . 'vendor/stripe/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($stripe_key);
            global $wpdb;
            $table_name = $wpdb->prefix . 'gymlite_recurring';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                gymlite_log('Recurring table missing during billing at ' . current_time('Y-m-d H:i:s'));
                return;
            }
            $recurrings = $wpdb->get_results("SELECT * FROM $table_name WHERE next_billing_date <= CURDATE() AND status = 'active'");
            $total_revenue = 0;
            foreach ($recurrings as $rec) {
                $member_email = get_post_meta($rec->member_id, '_gymlite_member_email', true);
                $stripe_token = get_post_meta($rec->member_id, '_gymlite_stripe_token', true);
                if (empty($stripe_token)) {
                    gymlite_log("No Stripe token for member ID {$rec->member_id} at " . current_time('Y-m-d H:i:s'));
                    continue;
                }
                $charge = \Stripe\Charge::create([
                    'amount' => $rec->amount * 100,
                    'currency' => 'usd',
                    'source' => $stripe_token,
                    'description' => "Recurring {$rec->plan_type} payment for member ID {$rec->member_id}",
                    'receipt_email' => $member_email,
                ]);
                $wpdb->insert(
                    $wpdb->prefix . 'gymlite_payments',
                    ['member_id' => $rec->member_id, 'amount' => $rec->amount, 'payment_date' => current_time('mysql'), 'stripe_payment_id' => $charge->id, 'payment_type' => $rec->plan_type],
                    ['%d', '%f', '%s', '%s', '%s']
                );
                $next_date = date('Y-m-d', strtotime($rec->next_billing_date . ' +1 month'));
                $wpdb->update(
                    $table_name,
                    ['next_billing_date' => $next_date, 'last_payment_date' => date('Y-m-d'), 'status' => 'active'],
                    ['id' => $rec->id]
                );
                $total_revenue += $rec->amount;
                gymlite_log("Processed recurring payment of {$rec->amount} for member ID {$rec->member_id} at " . current_time('Y-m-d H:i:s'));
            }
            if ($total_revenue > 0) {
                update_option('gymlite_last_billing_run', current_time('mysql'));
            }
        } catch (Exception $e) {
            gymlite_log("Billing error: " . $e->getMessage() . ' at ' . current_time('Y-m-d H:i:s'));
        }
    }

    // Other methods remain the same as in your original file, with logging fixed using current_time('Y-m-d H:i:s')
    public static function reporting(): void {
        if (!self::is_premium_active()) {
            wp_die(__('Premium feature required.', 'gymlite'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_payments';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY payment_date DESC LIMIT 50");
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM $table_name");
        ?>
        <div class="wrap">
            <h1><?php _e('Revenue Report (Pro)', 'gymlite'); ?></h1>
            <p><strong><?php _e('Total Revenue', 'gymlite'); ?>:</strong> $<?php echo esc_html($total_revenue ?: '0.00'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Member', 'gymlite'); ?></th>
                        <th><?php _e('Amount', 'gymlite'); ?></th>
                        <th><?php _e('Type', 'gymlite'); ?></th>
                        <th><?php _e('Date', 'gymlite'); ?></th>
                        <th><?php _e('Stripe ID', 'gymlite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($row->member_id)); ?></td>
                            <td><?php echo esc_html($row->amount); ?></td>
                            <td><?php echo esc_html($row->payment_type); ?></td>
                            <td><?php echo esc_html($row->payment_date); ?></td>
                            <td><?php echo esc_html($row->stripe_payment_id); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="<?php echo wp_nonce_url(admin_url('admin.php?page=gymlite-revenue-report&action=export'), 'gymlite_export_revenue'); ?>" class="button"><?php _e('Export as CSV', 'gymlite'); ?></a></p>
        </div>
        <?php
    }

    public static function export_revenue_report() {
        check_ajax_referer('gymlite_export_revenue', 'nonce');
        if (!self::is_premium_active()) {
            wp_die(__('Premium feature required.', 'gymlite'));
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_payments';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY payment_date DESC");
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="gymlite_revenue_report_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Member', 'Amount', 'Type', 'Date', 'Stripe ID']);
        foreach ($results as $row) {
            fputcsv($output, [
                get_the_title($row->member_id),
                $row->amount,
                $row->payment_type,
                $row->payment_date,
                $row->stripe_payment_id,
            ]);
        }
        fclose($output);
        exit;
    }

    public static function google_calendar_sync(): void {
        if (!self::is_premium_active()) {
            return;
        }
        try {
            $client = new Google_Client();
            $client->setApplicationName('GymLite');
            $client->setScopes(Google_Service_Calendar::CALENDAR);
            $client->setAuthConfig(GYMLITE_DIR . 'client_secret.json');
            $service = new Google_Service_Calendar($client);
            $classes = get_posts(['post_type' => 'gymlite_class', 'posts_per_page' => -1]);
            foreach ($classes as $class) {
                $date = get_post_meta($class->ID, '_gymlite_class_date', true);
                $duration = intval(get_post_meta($class->ID, '_gymlite_class_duration', true) ?: 60);
                $event = new Google_Service_Calendar_Event([
                    'summary' => $class->post_title,
                    'start' => ['dateTime' => $date, 'timeZone' => 'Australia/Sydney'],
                    'end' => ['dateTime' => (new DateTime($date))->modify("+$duration minutes")->format('Y-m-d\TH:i:s'), 'timeZone' => 'Australia/Sydney'],
                ]);
                $service->events->insert('primary', $event);
                gymlite_log("Synced class {$class->post_title} to Google Calendar at " . current_time('Y-m-d H:i:s'));
            }
        } catch (Exception $e) {
            gymlite_log("Google Calendar sync error: " . $e->getMessage() . ' at ' . current_time('Y-m-d H:i:s'));
        }
    }

    public static function zoom_integration(): void {
        if (!self::is_premium_active()) {
            return;
        }
        try {
            $zoom_api_key = get_option('gymlite_zoom_api_key', '');
            $zoom_api_secret = get_option('gymlite_zoom_api_secret', true);
            if (empty($zoom_api_key) || empty($zoom_api_secret)) {
                gymlite_log('Zoom API credentials missing at ' . current_time('Y-m-d H:i:s'));
                return;
            }
            $classes = get_posts(['post_type' => 'gymlite_class', 'posts_per_page' => -1]);
            foreach ($classes as $class) {
                $date = get_post_meta($class->ID, '_gymlite_class_date', true);
                $duration = intval(get_post_meta($class->ID, '_gymlite_class_duration', true) ?: 60);
                $ch = curl_init('https://api.zoom.us/v2/users/me/meetings');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'topic' => $class->post_title,
                    'type' => 2, // Scheduled meeting
                    'start_time' => (new DateTime($date, new DateTimeZone('Australia/Sydney')))->format('Y-m-d\TH:i:s'),
                    'duration' => $duration,
                    'timezone' => 'Australia/Sydney',
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . base64_encode("$zoom_api_key:$zoom_api_secret"),
                    'Content-Type: application/json',
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($response);
                update_post_meta($class->ID, '_gymlite_zoom_meeting_id', $data->id);
                gymlite_log("Created Zoom meeting for class {$class->post_title} at " . current_time('Y-m-d H:i:s'));
            }
        } catch (Exception $e) {
            gymlite_log("Zoom integration error: " . $e->getMessage() . ' at ' . current_time('Y-m-d H:i:s'));
        }
    }
}