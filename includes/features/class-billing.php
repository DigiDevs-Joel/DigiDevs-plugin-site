<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Billing {
    public function __construct() {
        try {
            gymlite_log("GymLite_Billing feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_action('gymlite_daily_cron', [$this, 'process_overdues']);
            add_action('wp_ajax_gymlite_create_invoice', [$this, 'handle_create_invoice']);
            add_action('wp_ajax_gymlite_process_payment', [$this, 'handle_process_payment']);
            add_action('wp_ajax_gymlite_get_billing_history', [$this, 'handle_get_billing_history']);
            add_action('wp_ajax_gymlite_cancel_subscription', [$this, 'handle_cancel_subscription']);
            gymlite_log("GymLite_Billing feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Billing: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Billing', 'gymlite'),
            __('Billing', 'gymlite'),
            'manage_options',
            'gymlite-billing',
            [$this, 'billing_page']
        );
    }

    public function billing_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-billing">
            <h1><?php _e('Billing Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Create Invoice', 'gymlite'); ?></h2>
                <form id="gymlite-create-invoice-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label for="member_id"><?php _e('Member ID', 'gymlite'); ?></label>
                        <input type="number" id="member_id" name="member_id" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="amount"><?php _e('Amount', 'gymlite'); ?></label>
                        <input type="number" step="0.01" id="amount" name="amount" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="description"><?php _e('Description', 'gymlite'); ?></label>
                        <input type="text" id="description" name="description" class="uk-input" required>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Create Invoice', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_create_invoice', 'nonce'); ?>
                </form>
            </div>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Billing History', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-billing-history">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Member ID', 'gymlite'); ?></th>
                            <th><?php _e('Amount', 'gymlite'); ?></th>
                            <th><?php _e('Date', 'gymlite'); ?></th>
                            <th><?php _e('Status', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-create-invoice-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_create_invoice',
                            member_id: $('#member_id').val(),
                            amount: $('#amount').val(),
                            description: $('#description').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                // Load billing history
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gymlite_get_billing_history',
                        nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var tableBody = $('#gymlite-billing-history tbody');
                            response.data.history.forEach(function(item) {
                                tableBody.append('<tr><td>' + item.id + '</td><td>' + item.member_id + '</td><td>' + item.amount + '</td><td>' + item.payment_date + '</td><td>' + item.status + '</td></tr>');
                            });
                        }
                    }
                });
            });
        </script>
        <?php
    }

    public function process_overdues() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_recurring';
        $updated = $wpdb->query("UPDATE $table_name SET status = 'overdue' WHERE next_billing_date < CURDATE() AND status = 'active'");
        if ($updated > 0) {
            gymlite_log("$updated overdue subscriptions marked.");
        }
    }

    public function handle_create_invoice() {
        check_ajax_referer('gymlite_create_invoice', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $member_id = intval($_POST['member_id']);
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description']);
        if (empty($member_id) || $amount <= 0 || empty($description)) {
            wp_send_json_error(['message' => __('Invalid invoice data.', 'gymlite')]);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'gymlite_payments';
        $result = $wpdb->insert($table_name, [
            'member_id' => $member_id,
            'amount' => $amount,
            'payment_date' => current_time('mysql'),
            'status' => 'pending',
            'description' => $description // Assuming added column; adjust if needed
        ]);
        if ($result) {
            gymlite_log("Invoice created for member $member_id, amount $amount");
            wp_send_json_success(['message' => __('Invoice created successfully.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to create invoice.', 'gymlite')]);
        }
    }

    public function handle_process_payment() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!GymLite_Premium::is_premium_active()) wp_send_json_error(['message' => __('Premium required for payments.', 'gymlite')]);
        $payment_id = intval($_POST['payment_id']);
        // Integrate with Stripe or other gateway
        // For example:
        try {
            $payment = \Stripe\PaymentIntent::create([
                'amount' => 1000, // Example
                'currency' => 'usd',
            ]);
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'gymlite_payments', ['status' => 'paid', 'transaction_id' => $payment->id], ['id' => $payment_id]);
            gymlite_log("Payment processed for ID $payment_id");
            wp_send_json_success(['message' => __('Payment processed.', 'gymlite')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_get_billing_history() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $history = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_payments ORDER BY payment_date DESC LIMIT 50");
        gymlite_log("Billing history retrieved");
        wp_send_json_success(['history' => $history]);
    }

    public function handle_cancel_subscription() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        $subscription_id = intval($_POST['subscription_id']);
        global $wpdb;
        $updated = $wpdb->update($wpdb->prefix . 'gymlite_recurring', ['status' => 'cancelled'], ['id' => $subscription_id]);
        if ($updated) {
            gymlite_log("Subscription cancelled: ID $subscription_id");
            wp_send_json_success(['message' => __('Subscription cancelled.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to cancel subscription.', 'gymlite')]);
        }
    }
}
?>