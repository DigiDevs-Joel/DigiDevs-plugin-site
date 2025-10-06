<?php
if (!defined('ABSPATH')) {
    exit;
}

class GymLite_Marketing {
    public function __construct() {
        try {
            gymlite_log("GymLite_Marketing feature constructor started at " . current_time('Y-m-d H:i:s'));
            add_action('admin_menu', [$this, 'add_submenu']);
            add_shortcode('gymlite_lead_form', [$this, 'lead_form_shortcode']);
            add_shortcode('gymlite_referrals', [$this, 'referrals_shortcode']);
            add_action('wp_ajax_gymlite_submit_lead', [$this, 'handle_submit_lead']);
            add_action('wp_ajax_nopriv_gymlite_submit_lead', [$this, 'handle_submit_lead']);
            add_action('wp_ajax_gymlite_create_campaign', [$this, 'handle_create_campaign']);
            add_action('wp_ajax_gymlite_send_campaign', [$this, 'handle_send_campaign']);
            add_action('wp_ajax_gymlite_get_leads', [$this, 'handle_get_leads']);
            add_action('wp_ajax_gymlite_get_campaigns', [$this, 'handle_get_campaigns']);
            add_action('wp_ajax_gymlite_delete_lead', [$this, 'handle_delete_lead']);
            add_action('wp_ajax_gymlite_delete_campaign', [$this, 'handle_delete_campaign']);
            gymlite_log("GymLite_Marketing feature constructor completed at " . current_time('Y-m-d H:i:s'));
        } catch (Exception $e) {
            gymlite_log("Error initializing GymLite_Marketing: " . $e->getMessage() . " at " . current_time('Y-m-d H:i:s'));
        }
    }

    public function add_submenu() {
        add_submenu_page(
            'gymlite-dashboard',
            __('Marketing', 'gymlite'),
            __('Marketing', 'gymlite'),
            'manage_options',
            'gymlite-marketing',
            [$this, 'marketing_admin_page']
        );
    }

    public function marketing_admin_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap gymlite-marketing">
            <h1><?php _e('Marketing Management', 'gymlite'); ?></h1>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Leads', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-leads-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Name', 'gymlite'); ?></th>
                            <th><?php _e('Email', 'gymlite'); ?></th>
                            <th><?php _e('Phone', 'gymlite'); ?></th>
                            <th><?php _e('Created At', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="uk-section uk-section-small">
                <h2><?php _e('Campaigns', 'gymlite'); ?></h2>
                <table class="uk-table uk-table-striped" id="gymlite-campaigns-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gymlite'); ?></th>
                            <th><?php _e('Name', 'gymlite'); ?></th>
                            <th><?php _e('Type', 'gymlite'); ?></th>
                            <th><?php _e('Sent At', 'gymlite'); ?></th>
                            <th><?php _e('Actions', 'gymlite'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <h3><?php _e('Create Campaign', 'gymlite'); ?></h3>
                <form id="gymlite-create-campaign-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label for="campaign_name"><?php _e('Campaign Name', 'gymlite'); ?></label>
                        <input type="text" id="campaign_name" name="campaign_name" class="uk-input" required>
                    </div>
                    <div class="uk-margin">
                        <label for="campaign_type"><?php _e('Type', 'gymlite'); ?></label>
                        <select id="campaign_type" name="campaign_type" class="uk-select" required>
                            <option value="email"><?php _e('Email', 'gymlite'); ?></option>
                            <option value="sms"><?php _e('SMS', 'gymlite'); ?></option>
                        </select>
                    </div>
                    <div class="uk-margin">
                        <label for="campaign_content"><?php _e('Content', 'gymlite'); ?></label>
                        <textarea id="campaign_content" name="campaign_content" class="uk-textarea" required></textarea>
                    </div>
                    <button type="submit" class="uk-button uk-button-primary"><?php _e('Create Campaign', 'gymlite'); ?></button>
                    <?php wp_nonce_field('gymlite_create_campaign', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function loadLeads() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_get_leads',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var tableBody = $('#gymlite-leads-table tbody');
                                tableBody.empty();
                                response.data.leads.forEach(function(lead) {
                                    tableBody.append('<tr><td>' + lead.id + '</td><td>' + lead.name + '</td><td>' + lead.email + '</td><td>' + lead.phone + '</td><td>' + lead.created_at + '</td><td><button class="uk-button uk-button-small uk-button-danger delete-lead" data-id="' + lead.id + '"><?php _e('Delete', 'gymlite'); ?></button></td></tr>');
                                });
                            }
                        }
                    });
                }
                loadLeads();

                function loadCampaigns() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_get_campaigns',
                            nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var tableBody = $('#gymlite-campaigns-table tbody');
                                tableBody.empty();
                                response.data.campaigns.forEach(function(camp) {
                                    tableBody.append('<tr><td>' + camp.id + '</td><td>' + camp.name + '</td><td>' + camp.type + '</td><td>' + camp.sent_at + '</td><td><button class="uk-button uk-button-small uk-button-primary send-camp" data-id="' + camp.id + '"><?php _e('Send', 'gymlite'); ?></button> <button class="uk-button uk-button-small uk-button-danger delete-camp" data-id="' + camp.id + '"><?php _e('Delete', 'gymlite'); ?></button></td></tr>');
                                });
                            }
                        }
                    });
                }
                loadCampaigns();

                $('#gymlite-create-campaign-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_create_campaign',
                            name: $('#campaign_name').val(),
                            type: $('#campaign_type').val(),
                            content: $('#campaign_content').val(),
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                loadCampaigns();
                                $('#gymlite-create-campaign-form')[0].reset();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                $(document).on('click', '.delete-lead', function() {
                    if (confirm('<?php _e('Are you sure?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_delete_lead',
                                lead_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadLeads();
                                } else {
                                    alert(response.data.message);
                                }
                            }
                        });
                    }
                });

                $(document).on('click', '.send-camp', function() {
                    if (confirm('<?php _e('Send this campaign?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_send_campaign',
                                camp_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadCampaigns();
                                } else {
                                    alert(response.data.message);
                                }
                            }
                        });
                    }
                });

                $(document).on('click', '.delete-camp', function() {
                    if (confirm('<?php _e('Are you sure?', 'gymlite'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gymlite_delete_campaign',
                                camp_id: $(this).data('id'),
                                nonce: '<?php echo wp_create_nonce('gymlite_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    loadCampaigns();
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

    public function lead_form_shortcode($atts) {
        ob_start();
        ?>
        <div class="gymlite-lead-form uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Join Our Gym', 'gymlite'); ?></h2>
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
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Submit', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_submit_lead', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-lead-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_submit_lead',
                            name: $('#name').val(),
                            email: $('#email').val(),
                            phone: $('#phone').val(),
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
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function referrals_shortcode($atts) {
        if (!is_user_logged_in()) return '<p class="uk-text-danger">' . __('Login required.', 'gymlite'); ?></p>';
        ob_start();
        ?>
        <div class="gymlite-referrals uk-section uk-section-small">
            <div class="uk-container uk-container-small">
                <h2 class="uk-heading-medium uk-text-center"><?php _e('Refer a Friend', 'gymlite'); ?></h2>
                <form id="gymlite-referral-form" class="uk-form-stacked">
                    <div class="uk-margin">
                        <label class="uk-form-label" for="referred_email"><?php _e('Friend\'s Email', 'gymlite'); ?></label>
                        <input class="uk-input" type="email" name="referred_email" id="referred_email" required>
                    </div>
                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary"><?php _e('Refer', 'gymlite'); ?></button>
                    </div>
                    <?php wp_nonce_field('gymlite_referral', 'nonce'); ?>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#gymlite-referral-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gymlite_track_referral',
                            referred_email: $('#referred_email').val(),
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
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_submit_lead() {
        check_ajax_referer('gymlite_submit_lead', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        if (empty($name) || empty($email)) wp_send_json_error(['message' => __('Name and email required.', 'gymlite')]);
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_leads';
        $result = $wpdb->insert($table, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'created_at' => current_time('mysql')
        ]);
        if ($result) {
            gymlite_log("Lead submitted: $name ($email)");
            wp_send_json_success(['message' => __('Lead submitted! We\'ll contact you soon.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Submission failed.', 'gymlite')]);
        }
    }

    public function handle_create_campaign() {
        check_ajax_referer('gymlite_create_campaign', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $name = sanitize_text_field($_POST['name']);
        $type = sanitize_text_field($_POST['type']);
        $content = wp_kses_post($_POST['content']);
        if (empty($name) || empty($type) || empty($content)) wp_send_json_error(['message' => __('All fields required.', 'gymlite')]);
        global $wpdb;
        $table = $wpdb->prefix . 'gymlite_campaigns';
        $result = $wpdb->insert($table, [
            'name' => $name,
            'type' => $type,
            'content' => $content,
            'sent_at' => null
        ]);
        if ($result) {
            gymlite_log("Campaign created: $name");
            wp_send_json_success(['message' => __('Campaign created.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to create campaign.', 'gymlite')]);
        }
    }

    public function handle_send_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $camp_id = intval($_POST['camp_id']);
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gymlite_campaigns WHERE id = %d", $camp_id));
        if (!$campaign) wp_send_json_error(['message' => __('Campaign not found.', 'gymlite')]);
        if ($campaign->sent_at) wp_send_json_error(['message' => __('Campaign already sent.', 'gymlite')]);
        $leads = $wpdb->get_results("SELECT email FROM {$wpdb->prefix}gymlite_leads");
        foreach ($leads as $lead) {
            if ($campaign->type === 'email') {
                wp_mail($lead->email, $campaign->name, $campaign->content);
            } // SMS would require integration like Twilio
        }
        $wpdb->update($wpdb->prefix . 'gymlite_campaigns', ['sent_at' => current_time('mysql')], ['id' => $camp_id]);
        gymlite_log("Campaign sent: ID $camp_id");
        wp_send_json_success(['message' => __('Campaign sent.', 'gymlite')]);
    }

    public function handle_get_leads() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_leads ORDER BY created_at DESC");
        gymlite_log("Leads retrieved");
        wp_send_json_success(['leads' => $leads]);
    }

    public function handle_get_campaigns() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gymlite_campaigns ORDER BY id DESC");
        gymlite_log("Campaigns retrieved");
        wp_send_json_success(['campaigns' => $campaigns]);
    }

    public function handle_delete_lead() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $lead_id = intval($_POST['lead_id']);
        global $wpdb;
        $result = $wpdb->delete($wpdb->prefix . 'gymlite_leads', ['id' => $lead_id]);
        if ($result) {
            gymlite_log("Lead deleted: ID $lead_id");
            wp_send_json_success(['message' => __('Lead deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete lead.', 'gymlite')]);
        }
    }

    public function handle_delete_campaign() {
        check_ajax_referer('gymlite_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized.', 'gymlite')]);
        $camp_id = intval($_POST['camp_id']);
        global $wpdb;
        $result = $wpdb->delete($wpdb->prefix . 'gymlite_campaigns', ['id' => $camp_id]);
        if ($result) {
            gymlite_log("Campaign deleted: ID $camp_id");
            wp_send_json_success(['message' => __('Campaign deleted.', 'gymlite')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete campaign.', 'gymlite')]);
        }
    }
}
?>