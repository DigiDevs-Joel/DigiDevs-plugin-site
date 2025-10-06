<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Pos {
    public function __construct() {
        try {
            gymlite_log("GymLite_Pos feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_shortcode('gymlite_pos', [$this, 'pos_shortcode']);
            add_action('wp_ajax_gymlite_process_pos_payment', [$this, 'handle_process_pos_payment']);
            add_action('wp_ajax_gymlite_get_pos_products', [$this, 'handle_get_pos_products']);
            add_action('wp_ajax_gymlite_add_pos_product', [$this, 'handle_add_pos_product']);
            add_action('wp_ajax_gymlite_update_pos_product', [$this, 'handle_update_pos_product']);
            add_action('wp_ajax_gymlite_delete_pos_product', [$this, 'handle_delete_pos_product']);
            gymlite_log("GymLite_Pos feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Pos: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Point of Sale', 'gymlite'),
            __('POS', 'gymlite'),
            'manage_options',
            'gymlite-pos',
            [$this, 'pos_admin_page']
        );
    }

    public function pos_admin_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-pos">
            <h1><?php _e('Point of Sale Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Manage Products', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-pos-products">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Product Name', 'gymlite'); ?></th>
                            <th><?php _e('Price', 'gymlite'); ?></th>
                            <th><?php _e('Quantity', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <h3><?php _e('Add/Update Product', 'gymlite'); ?></h3>
                <form id="gymlite-pos-product-form" class="uk-form-stacked">
                    <input type="hidden" id="product_id" name="product_id">
                    <div class="uk-margin">
                        <label for="product_name"><?php _e('Product Name', 'gymlite'); ?></label>
                        <input type="text" id="product_name" name="product_name" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="price"><?php _e('Price', 'gymlite'); ?></label>
                        <input type="number" step="0.01" id="price" name="price" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="quantity"><?php _e('Quantity', 'gymlite'); ?></label>
                        <input type="number" id="quantity" name="quantity" class="uk-input" required>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Save Product', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_pos_product', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function loadProducts() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_get_pos_products',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var tableBody = $('#gymlite-pos-products tbody');
                                tableBody.empty();
                                response.data.products.forEach(function(product) {
                                    tableBody.append('<tr><td>' + product.id + '</td><td>' + product.product_name + '</td><td>' + product.price + '</td><td>' + product.quantity + '</td><td><button class="uk-button uk-button-small uk-button-primary edit-product" data-id="' + product.id + '"><?php _e('Edit', 'gymlite'); ?></button> <button class="uk-button uk-button-small uk-button-danger delete-product" data-id="' + product.id + '"><?php _e('Delete', 'gymlite'); ?></button></td></tr>');
                                });
                            }
                        }
                    });
                }
                loadProducts();

                $('#gymlite-pos-product-form').on('submit', function(e) {
                    e.preventDefault();
                    var action = $('#product_id').val() ? 'gymlite_update_pos_product' : 'gymlite_add_pos_product';
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: action,
                            product_id: $('#product_id').val(),
                            product_name: $('#product_name').val(),
                            price: $('#price').val(),
                            quantity: $('#quantity').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadProducts();
                                $('#gymlite-pos-product-form')[0].reset();
                                $('#product_id').val('');
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                $(document).on('click', '.edit-product', function() {
                    var row = $(this).closest('tr');
                    $('#product_id').val(row.find('td:eq(0)').text());
                    $('#product_name').val(row.find('td:eq(1)').text());
                    $('#price').val(row.find('td:eq(2)').text());
                    $('#quantity').val(row.find('td:eq(3)').text());
                });

                $(document).on('click', '.delete-product', function() {
                    if (confirm('<?php _e('Are you sure?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_delete_pos_product',
                                product_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadProducts();
                                } else {
                                    alert(response.data.message);
                                }
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    public function pos_shortcode($atts) {
        if (!current_user_can('manage_options')) return '<p class="uk-text-danger">' . __('Access denied.', 'gymlite') . '</p>';
        ob_start();
        ?>
        <div class="gymlite-pos-frontend uk-section">
            <div class="uk-container">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Point of Sale', 'gymlite'); ?></h2>
                <!-- Frontend POS interface, e.g., product selection, cart, payment -->
                <div id="gymlite-pos-cart"></div>
                <button class="uk-button uk-button-primary" id="gymlite-pos-checkout"><?php _e('Checkout', 'gymlite'); ?></button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_process_pos_payment() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!GymLite_Premium::is_premium_active()) wp_send_json_error(['message' => __('Premium required.', 'gymlite')]);
        $amount = floatval($_POST['amount']);
        $member_id = intval($_POST['member_id']);
        $products = json_decode(stripslashes($_POST['products']), true); // Array of {id, qty}
        if ($amount <= 0 || empty($products)) wp_send_json_error(['message' => __('Invalid payment data.', 'gymlite')]);
        try {
            // Process with Stripe
            $charge = \Stripe\Charge::create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'description' => 'POS payment',
                'source' => 'tok_visa', // Frontend token
            ]);
            global $wpdb;
            $payment_id = $wpdb->insert_id = $wpdb->insert($wpdb->prefix . 'gymlite_payments', [
                'member_id' => $member_id,
                'amount' => $amount,
                'payment_date' => current_time('mysql'),
                'status' => 'paid',
                'transaction_id' => $charge->id
            ]);
            // Update inventory
            $inventory_table = $wpdb->prefix . 'gymlite_inventory';
            foreach ($products as $product) {
                $wpdb->query($wpdb->prepare("UPDATE $inventory_table SET quantity = quantity - %d WHERE id = %d", $product['qty'], $product['id']));
            }
            gymlite_log("POS payment processed: ID $payment_id, amount $amount");
            wp_send_json_success(['message' => __('Payment successful.', 'gymlite')]);
        } catch (Exception $e) {
            gymlite_log("POS payment error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_get_pos_products() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        global $wpdb;
        $products = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_inventory");
        gymlite_log("POS products retrieved");
        wp_send_json_success(['products' => $products]);
    }

    public function handle_add_pos_product() {
        check_ajax_referer('gymlite_pos_product', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $name = sanitize_text_field($_POST['product_name']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        if (empty($name) || $price <= 0 || $quantity < 0) wp_send_json_error(['message' => __('Invalid product data.', 'gymlite')]);
        global $wpdb;
        $result = $wpdb->insert($wpdb->prefix . 'gymlite_inventory', [
            'product_name' => $name,
            'price' => $price,
            'quantity' => $quantity
        ]);
        if ($result) {
            gymlite_log("POS product added: $name");
            wp_send_json_success(['message' => __('Product added.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to add product.', 'gymlite')]);
        }
    }

    public function handle_update_pos_product() {
        check_ajax_referer('gymlite_pos_product', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $id = intval($_POST['product_id']);
        $name = sanitize_text_field($_POST['product_name']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        if (empty($id) || empty($name) || $price <= 0 || $quantity < 0) wp_send_json_error(['message' => __('Invalid product data.', 'gymlite')]);
        global $wpdb;
        $result = $wpdb->update($wpdb->prefix . 'gymlite_inventory', [
            'product_name' => $name,
            'price' => $price,
            'quantity' => $quantity
        ], ['id' => $id]);
        if ($result !== false) {
            gymlite_log("POS product updated: ID $id");
            wp_send_json_success(['message' => __('Product updated.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update product.', 'gymlite')]);
        }
    }

    public function handle_delete_pos_product() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $id = intval($_POST['product_id']);
        global $wpdb;
        $result = $wpdb->delete($wpdb->prefix . 'gymlite_inventory', ['id' => $id]);
        if ($result) {
            gymlite_log("POS product deleted: ID $id");
            wp_send_json_success(['message' => __('Product deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete product.', 'gymlite')]);
        }
    }
}
?>