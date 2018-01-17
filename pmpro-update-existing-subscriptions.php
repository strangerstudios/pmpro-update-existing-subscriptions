<?php
/*
Plugin Name: Paid Memberships Pro - Update Existing Subscriptions
Plugin URI: https://github.com/strangerstudios/pmpro-update-existing-subscriptions
Description: Interface to update the details of existing subscriptions.
Version: .2
Author: Stranger Studios
Author URI: https://www.strangerstudios.com
*/

/*
	Update Subscriptions page.

	Update subscription [ for level 1 ].

	Set billing amount to ____ (use 0 to cancel the subscription)

	Set billing period to ____ [ days/months/weeks/years ]

    Process records created prior to ______ (YYYY-MM-DD date)

    Process records created after _____ (YYYY-MM-DD date)

    Environment [ Sandbox / Test ]
*/

/*
	Dashboard Menu
*/
function pmproues_add_pages() {
    add_submenu_page('pmpro-membershiplevels', __('Update Subscriptions', 'pmpro'), __('Update Subscriptions', 'pmpro'), 'manage_options', 'pmpro-update-existing-subscriptions', 'pmpro_update_existing_subscriptions');
}

add_action('admin_menu', 'pmproues_add_pages', 15);

/*
	Enqueue updates.js if needed
*/
function pmproues_enqueue_js() {
    if (is_admin() && current_user_can('manage_options') && !empty($_REQUEST['page']) && $_REQUEST['page'] == 'pmpro-update-existing-subscriptions') {
        //check fields
        if (!empty($_REQUEST['pmproues_gateway']) &&
            !empty($_REQUEST['pmproues_level']) &&
            (empty($_REQUEST['pmproues_billing_amount']) || !empty($_REQUEST['pmproues_cycle_number']) && !empty($_REQUEST['pmproues_cycle_period']))
        ) {
            //running
            wp_register_script('pmproues', plugin_dir_url(__FILE__) . 'js/pmproues.js');

            //get values
            wp_localize_script('pmproues', 'pmproues',
                array(
                    'gateway' => isset($_REQUEST['pmproues_gateway']) ? sanitize_text_field($_REQUEST['pmproues_gateway']) : null,
                    'level' => isset($_REQUEST['pmproues_level']) ? intval($_REQUEST['pmproues_level']) : null,
                    'billing_amount' => isset($_REQUEST['pmproues_billing_amount']) ? floatval($_REQUEST['pmproues_billing_amount']) : null,
                    'cycle_number' => isset($_REQUEST['pmproues_cycle_number']) ? intval($_REQUEST['pmproues_cycle_number']) : 0,
                    'cycle_period' => isset($_REQUEST['pmproues_cycle_period']) ? sanitize_text_field($_REQUEST['pmproues_cycle_period']) : null,
                    'live' => isset($_REQUEST['pmproues_live']) ? sanitize_text_field($_REQUEST['pmproues_cycle_period']) : 0,
                    'before_date' => isset($_REQUEST['pmproues_before_date']) ? sanitize_text_field($_REQUEST['pmproues_before_date']) : null,
                    'after_date' => isset($_REQUEST['pmproues_after_date']) ? sanitize_text_field($_REQUEST['pmproues_after_date']) : null,
        )
            );

            //enqueue
            wp_enqueue_script('pmproues', NULL, array('jquery'), '5');
        }
    }
}
add_action('admin_enqueue_scripts', 'pmproues_enqueue_js');

/*
	Page
*/
function pmpro_update_existing_subscriptions() {
    // die if PMPro is deactivated or not installed
    if ( ! defined("PMPRO_DIR") ) {
	    die(__('Paid Memberships Pro must be activated before using this tool.', 'pmproues'));
	}

    //only admins can get this
    if (!function_exists("current_user_can") || !current_user_can("manage_options")) {
        die(__("You do not have permissions to perform this action.", "pmproues"));
    }

    //vars
    global $pmpro_currency_symbol, $gateway;
    $updatesubscriptions = false;

    // sanitize all request values we use
    $pmproues_gateway = isset($_REQUEST['pmproues_gateway']) ? sanitize_text_field($_REQUEST['pmproues_gateway']) : "stripe";
    $pmproues_level = isset($_REQUEST['pmproues_level']) ? intval($_REQUEST['pmproues_level']) : null;
    $pmproues_billing_amount = isset($_REQUEST['pmproues_billing_amount']) ? floatval($_REQUEST['pmproues_billing_amount']) : null;
    $pmproues_cycle_number = isset($_REQUEST['pmproues_cycle_number']) ? intval($_REQUEST['pmproues_cycle_number']) : 1;
    $pmproues_cycle_period = isset($_REQUEST['pmproues_cycle_period']) ? sanitize_text_field($_REQUEST['pmproues_cycle_period']) : "Month";

    // time limit the search/processing
    $pmproues_before = isset($_REQUEST['pmproues_before_date']) ? sanitize_text_field($_REQUEST['pmproues_before_date']) : null;
    $pmproues_after = isset($_REQUEST['pmproues_after_date']) ? sanitize_text_field($_REQUEST['pmproues_after_date']) : null;

    // Handle situations where the file may not be present
    if (file_exists(PMPRO_DIR . "/adminpages/admin_header.php")) {
        require_once(PMPRO_DIR . "/adminpages/admin_header.php");
    }

    //clear out msg fields
    $msg = "";
    $msgt = "";

    //running?
    if (isset($_REQUEST['updatesubscriptions']) && !empty($_REQUEST['updatesubscriptions'])) {
        //check fields
        if (empty($pmproues_gateway) || empty($pmproues_level)) {
            $msg = __('You must select a gateway and level to update.');
            $msgt = 'error';
        } elseif (!empty($pmproues_billing_amount) && (empty($pmproues_cycle_number) || empty($pmproues_cycle_period))) {
            $msg = __('Select a cycle number and billing period or use billing amount 0 to cancel the subscriptions.');
            $msgt = 'error';
        } else {
            $updatesubscriptions = true;
        }
    }
    ?>

    <h2><?php _e('Update Existing Subscriptions at the Gateway', 'pmpro'); ?></h2>

    <?php if (!empty($msg)) { ?>
    <div class="message <?php echo $msgt; ?>"><p><?php echo $msg; ?></p></div>
<?php } ?>

    <p><?php _e('This plugin currently supports updating Stripe subscriptions only. You can choose one level to update at a time and set a new billing amount and/or period for all active subscriptions for users of that level.'); ?></p>

    <?php if (!empty($updatesubscriptions)) { ?>

    <p id="pmproues_updates_intro"><?php _e('Updates are processing. This may take a few minutes to complete.', 'pmproues'); ?></p>
    <textarea id="pmproues_updates_status" rows="20" cols="120">Loading...</textarea>

<?php } else { ?>
    <form action="" method="post">
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="pmproues_gateway"><?php _e('Gateway', 'pmproues'); ?></label>
                </th>
                <td>
                    <select name="pmproues_gateway" id="pmproues_gateway">
                        <option value="stripe"><?php _e('Stripe', 'pmproues'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pmproues_level"><?php _e('Level', 'pmproues'); ?></label>
                </th>
                <td>
                    <select name="pmproues_level" id="pmproues_level">
                        <option value="">- <?php _e('Choose One', 'pmproues'); ?> -</option>
                        <?php
                        $levels = pmpro_getAllLevels(true, true);
                        foreach ($levels as $level) {
                            ?>
                            <option value="<?php echo $level->id; ?>"><?php echo $level->name; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top"><label
                        for="pmproues_billing_amount"><?php _e('New Billing Amount', 'pmproues'); ?>:</label></th>
                <td>
                    <?php
                    if (pmpro_getCurrencyPosition() == "left")
                        echo $pmpro_currency_symbol;
                    ?>
                    <input id="pmproues_billing_amount" name="pmproues_billing_amount" type="text" size="20"
                           value="<?php echo esc_attr($pmproues_billing_amount); ?>"/>
                    <?php
                    if (pmpro_getCurrencyPosition() == "right")
                        echo $pmpro_currency_symbol;
                    ?>
                    <small><?php _e('per', 'pmpro'); ?></small>
                    <input id="pmproues_cycle_number" name="pmproues_cycle_number" type="text" size="10"
                           value="<?php echo esc_attr($pmproues_cycle_number); ?>"/>
                    <select id="pmproues_cycle_period" name="pmproues_cycle_period">
                        <?php
                        $cycles = array(__('Day(s)', 'pmpro') => 'Day', __('Week(s)', 'pmpro') => 'Week', __('Month(s)', 'pmpro') => 'Month', __('Year(s)', 'pmpro') => 'Year');
                        foreach ($cycles as $name => $value) {
                            echo "<option value='$value'";
                            if ($pmproues_cycle_period == $value) echo " selected='selected'";
                            echo ">$name</option>";
                        }
                        ?>
                    </select>
                    <br/>
                    <small>
                        <?php _e('Use billing amount of 0 to cancel subscriptions.', 'pmproues'); ?>
                        <?php if ($gateway == "stripe") { ?>
                        <br/><strong
                            <?php if (!empty($pmpro_stripe_error)) { ?>class="pmpro_red"<?php } ?>><?php _e('Stripe integration currently only supports billing periods of "Week", "Month" or "Year".', 'pmpro'); ?>
                            <?php } elseif ($gateway == "braintree") { ?>
                            <br/><strong
                                <?php if (!empty($pmpro_braintree_error)) { ?>class="pmpro_red"<?php } ?>><?php _e('Braintree integration currently only supports billing periods of "Month" or "Year".', 'pmpro'); ?>
                                <?php } elseif ($gateway == "payflowpro") { ?>
                                <br/><strong
                                    <?php if (!empty($pmpro_payflow_error)) { ?>class="pmpro_red"<?php } ?>><?php _e('Payflow integration currently only supports billing frequencies of 1 and billing periods of "Week", "Month" or "Year".', 'pmpro'); ?>
                                    <?php } ?>
                    </small>
                    <?php if ($gateway == "braintree" && $edit < 0) { ?>
                        <p class="pmpro_message"><strong><?php _e('Note', 'pmpro'); ?>
                                :</strong> <?php _e('After saving this level, make note of the ID and create a "Plan" in your Braintree dashboard with the same settings and the "Plan ID" set to <em>pmpro_#</em>, where # is the level ID.', 'pmpro'); ?>
                        </p>
                    <?php } elseif ($gateway == "braintree") { ?>
                        <p class="pmpro_message"><strong><?php _e('Note', 'pmpro'); ?>
                                :</strong> <?php _e('You will need to create a "Plan" in your Braintree dashboard with the same settings and the "Plan ID" set to', 'pmpro'); ?>
                            <em>pmpro_<?php echo $level->id; ?></em>.</p>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pmproues_after_date"><?php _e('Records created after', 'pmproues'); ?></label>
                </th>
                <td>
                    <input name="pmproues_after_date" id="pmproues_before_date" type="date" value="<?php echo esc_attr($pmproues_after); ?>">
                    <small><?php _e("The date specified will include transactions from 00:00:00AM on the specified date ", 'pmproues'); ?></small>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pmproues_before_date"><?php _e('Records created prior to', 'pmproues'); ?></label>
                </th>
                <td>
                    <input name="pmproues_before_date" id="pmproues_before_date" type="date" value="<?php echo esc_attr($pmproues_before); ?>">
                    <small><?php _e("The date specified will include transactions until 11:59:59PM on the specified date ", 'pmproues'); ?></small>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pmproues_live"><?php _e('Test or Live', 'pmproues'); ?></label>
                </th>
                <td>
                    <select name="pmproues_live" id="pmproues_live">
                        <option value="0"><?php _e('Test Run', 'pmproues'); ?></option>
                        <option value="1"><?php _e('Live Run', 'pmproues'); ?></option>
                    </select>
                    <small><?php _e('When testing, planned updates are shown but not made at the gateway.', 'pmproues'); ?></small>
                </td>
            </tr>

            </tbody>
        </table>

        <p class="submit topborder">
            <input type="hidden" name="updatesubscriptions" value="1"/>
            <input name="run" type="submit" class="button-primary" value="<?php _e('Run Update', 'pmproues'); ?>"/>
        </p>
    </form>
<?php } ?>

    <?php
    if (file_exists(PMPRO_DIR . "/adminpages/admin_footer.php")) {
        require_once(PMPRO_DIR . "/adminpages/admin_footer.php");
    }

}

/*
	Load an update via AJAX
*/
function pmproues_wp_ajax() {
	// return quietly (PMPro is deactivated or not installed)
    if ( ! defined("PMPRO_DIR") ) {
	    exit;
	}

    //make sure the user is an admin
    if (!current_user_can('manage_options')) {
        exit;
    }

    // vars
    $where_args = null;

    //get values
    $gateway = isset($_REQUEST['gateway']) ? sanitize_text_field($_REQUEST['gateway']) : 'Stripe';
    $level = isset($_REQUEST['level']) ? intval($_REQUEST['level']) : null;
    $billing_amount = isset($_REQUEST['billing_amount']) ? floatval($_REQUEST['billing_amount']) : null;
    $cycle_number = isset($_REQUEST['cycle_number']) ? intval($_REQUEST['cycle_number']) : 0;
    $cycle_period = isset($_REQUEST['cycle_period']) ? sanitize_text_field($_REQUEST['cycle_period']) : 'Month';
    $live = isset($_REQUEST['live']) ? intval($_REQUEST['live']) : 0;
    $after_date = isset($_REQUEST['after_date']) ? sanitize_text_field($_REQUEST['after_date']) : null;
    $before_date = isset($_REQUEST['before_date']) ? sanitize_text_field($_REQUEST['before_date']) : null;

    // limit of transactions to process per cycle.
    $limit = isset($_REQUEST['limit']) && !empty($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 5;

    //continue progress?
    $hash = substr(md5($gateway . $level . $billing_amount . $cycle_number . $cycle_period . $live), 0, 16);
    $last_user = get_transient('pmproues_update_last_row_' . $hash);

    if (empty($last_user))
        $last_user = 0;

    //find members
    global $wpdb;

    $sqlQuery = "SELECT DISTINCT mu.user_id
          FROM {$wpdb->pmpro_memberships_users} AS mu
          ";

    // handle before & after dates (if specified)
    if ( ! empty($after_date) && ! empty($before_date) && ( strtotime($after_date ) && strtotime($before_date) ) ) {

        $sqlQuery .= "INNER JOIN {$wpdb->pmpro_membership_orders} AS mo ON mo.user_id = mu.user_id AND mo.timestamp BETWEEN '" . esc_sql($after_date) . " 00:00:00' AND '" . esc_sql($before_date) . " 23:59:59'
        ";

        $where_args = "
        AND mo.timestamp BETWEEN '" . esc_sql($after_date) ." 00:00:00' AND '" . esc_sql($before_date) . " 23:59:59'
        ";
    }

    // handle after date
    if ( ! empty($after_date) && empty($before_date) && strtotime($after_date)) {
        $sqlQuery .= "INNER JOIN {$wpdb->pmpro_membership_orders} AS mo ON mo.user_id = mu.user_id AND mo.timestamp >= '" . esc_sql($after_date) . " 00:00:00'
        ";
        $where_args = "
        AND mo.timestamp >= '" . esc_sql($after_date) ." 00:00:00'
        ";
    }

    // handle before date
    if ( ! empty($before_date) && empty($after_date) && strtotime($before_date)) {
        $sqlQuery .= "INNER JOIN {$wpdb->pmpro_membership_orders} AS mo ON mo.user_id = mu.user_id AND mo.timestamp <= '" . esc_sql($before_date) . " 23:59:59'
        ";
        $where_args = "
        AND mo.timestamp <= '" . esc_sql($before_date) . " 00:00:00'
        ";
    }

    $sqlQuery .="WHERE mu.user_id > " . esc_sql($last_user);

    // append the before/after args -- probably not needed, but playing it defensively
    if (!is_null($where_args)) {

        $sqlQuery .= $where_args;
    }

    // rest of query conditions
    $sqlQuery .= "
        AND mu.membership_id = " . esc_sql($level) . "
        AND mu.status = 'active'
        ORDER BY mu.user_id
        LIMIT " . esc_sql($limit);

    if (WP_DEBUG) {
        error_log("We're using: {$sqlQuery}");
    }

    $member_ids = $wpdb->get_col($sqlQuery);

    if (empty($member_ids)) {
        delete_transient('pmproues_update_last_row_' . $hash);
        echo "done";
        exit;
    } else {
        //update subs
        foreach ($member_ids as $member_id) {

            $last_user = $member_id;
            echo "\n----\nMember ID #" . $member_id . ". ";

            //get user
            $user = get_userdata($member_id);

            //no user?
            if (empty($user) || empty($user->ID)) {
                echo "Could not find user. ";
                continue;
            } else
                echo "User found. (" . $user->user_email . ") ";

            //get order
            $order = new MemberOrder();
            $order->getLastMemberOrder($user->ID);

            //no order?
            if (empty($order->id)) {
                echo "Could not find order. ";
                continue;
            } else
                echo "Order found. (" . $order->code . " on " . date_i18n("Y-m-d \a\\t H:i:s", $order->timestamp) . ") ";

            //different gateway?
            if ($order->gateway != $gateway) {
                echo "Different gateway. ";
                continue;
            } else
                echo "Gateway matches. ";

            //okay find the sub
            if (empty($order->subscription_transaction_id)) {
                echo "No subscription transaction ID. ";
                continue;
            } else
                echo "Subscription ID found. ";

            if (empty($live)) {
                echo "Would have updated the subscription here, but we're just testing. ";
                continue;
            }

            //let's do it live!
            if ($gateway == "stripe") {

                /*
                    Note: This code is copied and modified from the user_profile_fields_save method.
                */
                //get level for user
                $user_level = pmpro_getMembershipLevelForUser($user->ID);

                //get current plan at Stripe to get payment date
                $order->Gateway->getCustomer($order);
                $subscription = $order->Gateway->getSubscription($order);

                if (!empty($subscription)) {
                    $end_timestamp = $subscription->current_period_end;
                    //cancel the old subscription
                    if (!$order->Gateway->cancelSubscriptionAtGateway($subscription)) {
                        echo "Could not cancel the old subscription. Skipping. ";
                        continue;
                    }
                }
                //if we didn't get an end date, let's set one one cycle out
                if (empty($end_timestamp))
                    $end_timestamp = strtotime("+" . $cycle_number . " " . $cycle_period, current_time('timestamp'));
                //build order object
                $update_order = new MemberOrder();
                $update_order->setGateway('stripe');
                $update_order->user_id = $user->ID;
                $update_order->Email = $user->user_email;
                $update_order->membership_id = $user_level->id;
                $update_order->membership_name = $user_level->name;
                $update_order->InitialPayment = 0;
                $update_order->PaymentAmount = $billing_amount;
                $update_order->ProfileStartDate = date("Y-m-d", $end_timestamp);
                $update_order->BillingPeriod = $cycle_period;
                $update_order->BillingFrequency = $cycle_number;
                //need filter to reset ProfileStartDate
                add_filter('pmpro_profile_start_date', create_function('$startdate, $order', 'return "' . $update_order->ProfileStartDate . 'T0:0:0";'), 10, 2);

                if (! empty($billing_amount) ) {
                    //update subscription
                    $update_order->Gateway->subscribe( $update_order, false );
                    //update membership
                    $sqlQuery = "UPDATE $wpdb->pmpro_memberships_users
                                    SET billing_amount = '" . esc_sql( $billing_amount ) . "',
                                        cycle_number = '" . esc_sql( $cycle_number ) . "',
                                        cycle_period = '" . esc_sql( $cycle_period ) . "',
                                        trial_amount = '',
                                        trial_limit = ''
                                    WHERE user_id = '" . esc_sql( $user->ID ) . "'
                                        AND membership_id = '" . esc_sql( $order->membership_id ) . "'
                                        AND status = 'active'
                                    LIMIT 1";

                    $update_order->status = "success";
                } else {
                    $wpdb->prepare("
                        INSERT {$wpdb->pmpro_memberships_users}
                            ( cycle_number, cycle_period, trial_amount, trial_limit, status, membership_id, user_id )
                            VALUES
                            ( %d, %s, %d, %d, %s, %d, %d )",
                        $cycle_number, $cycle_period, '', '', $user->ID, 0, 'status'
                    );
                    $update_order->status = "success";
                }
                $wpdb->query($sqlQuery);
                //save order so we know which plan to look for at stripe (order code = plan id)

                $update_order->saveOrder();

                echo "ORDER UPDATED!";
            }
        }
    }

    set_transient('pmproues_update_last_row_' . $hash, $last_user, 60 * 60 * 24);

    exit;
}
add_action('wp_ajax_pmpro_update_existing_subscriptions', 'pmproues_wp_ajax');
