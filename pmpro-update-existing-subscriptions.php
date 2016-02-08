<?php
/*
Plugin Name: Paid Memberships Pro - Update Existing Subscriptions
Plugin URI: http://www.paidmembershipspro.com/wp/update-existing-subscriptsions/
Description: Interface to update the details of existing subscriptions.
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Update Subscriptions page.
	
	Update subscription [ for level 1 ].
	
	Set billing amount to ____ (use 0 to cancel the subscription)
	
	Set billing period to ____ [ days/months/weeks/years ]
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
	if(is_admin() && current_user_can('manage_options') && !empty($_REQUEST['page']) && $_REQUEST['page'] == 'pmpro-update-existing-subscriptions') {
		//check fields
		if(!empty($_REQUEST['pmproues_gateway']) && 
		   !empty($_REQUEST['pmproues_level']) &&
		   (empty($_REQUEST['pmproues_billing_amount']) || !empty($_REQUEST['pmproues_cycle_number']) && !empty($_REQUEST['pmproues_cycle_period']))
		) {
			//running
			wp_register_script('pmproues', plugin_dir_url( __FILE__ ) . 'js/pmproues.js');

			//get values
			wp_localize_script('pmproues', 'pmproues', 
				array(
					'gateway'=>$_REQUEST['pmproues_gateway'],
					'level'=>$_REQUEST['pmproues_level'],
					'billing_amount'=>$_REQUEST['pmproues_billing_amount'],
					'cycle_number'=>$_REQUEST['pmproues_cycle_number'],
					'cycle_period'=>$_REQUEST['pmproues_cycle_period'],
					'live'=>$_REQUEST['pmproues_live']
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
	//only admins can get this
	if(!function_exists("current_user_can") || !current_user_can("manage_options"))
	{
		die(__("You do not have permissions to perform this action.", "pmpro"));
	}

	//vars
	global $pmpro_currency_symbol, $gateway;
	
	if(isset($_REQUEST['pmproues_gateway']))
		$pmproues_gateway = $_REQUEST['pmproues_gateway'];
	else
		$pmproues_gateway = "stripe";
	
	if(isset($_REQUEST['pmproues_level']))
		$pmproues_level = $_REQUEST['pmproues_level'];
	else
		$pmproues_level = "";
		
	if(isset($_REQUEST['pmproues_billing_amount']))
		$pmproues_billing_amount = $_REQUEST['pmproues_billing_amount'];
	else
		$pmproues_billing_amount = "";
		
	if(isset($_REQUEST['pmproues_cycle_number']))
		$pmproues_cycle_number = $_REQUEST['pmproues_cycle_number'];
	else
		$pmproues_cycle_number = "1";
		
	if(isset($_REQUEST['pmproues_cycle_period']))
		$pmproues_cycle_period = $_REQUEST['pmproues_cycle_period'];
	else
		$pmproues_cycle_period = "Month";		
	
	require_once(PMPRO_DIR . "/adminpages/admin_header.php");
	
	//clear out msg fields
	$msg = "";
	$msgt = "";
	
	//running?
	if(!empty($_REQUEST['updatesubscriptions'])) {				
		//check fields
		if(empty($pmproues_gateway) || empty($pmproues_level)) {
			$msg = __('You must select a gateway and level to update.');
			$msgt = 'error';
		} elseif(!empty($pmproues_billing_amount) && (empty($pmproues_cycle_number) || empty($pmproues_cycle_period))) {
			$msg = __('Select a cycle number and billing period or use billing amount 0 to cancel the subscriptions.');
			$msgt = 'error';
		} else {
			$updatesubscriptions = true;
		}
	}
?>

<h2><?php _e('Update Existing Subscriptions at the Gateway', 'pmpro');?></h2>

<?php if(!empty($msg)) { ?>
	<div class="message <?php echo $msgt;?>"><p><?php echo $msg;?></p></div>
<?php } ?>

<p><?php _e('This plugin currently supports updating Stripe subscriptions only. You can choose one level to update at a time and set a new billing amount and/or period for all active subscriptions for users of that level.');?></p>

<?php if(!empty($updatesubscriptions)) { ?>

<p id="pmproues_updates_intro"><?php _e('Updates are processing. This may take a few minutes to complete.', 'pmproues');?></p>
<textarea id="pmproues_updates_status" rows="20" cols="120">Loading...</textarea>

<?php } else { ?>
	<form action="" method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="pmproues_gateway"><?php _e('Gateway', 'pmproues');?></label>
					</th>
					<td>
						<select name="pmproues_gateway" id="pmproues_gateway">
							<option value="stripe"><?php _e('Stripe', 'pmproues');?></option>						
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="pmproues_level"><?php _e('Level', 'pmproues');?></label>
					</th>
					<td>
						<select name="pmproues_level" id="pmproues_level">
							<option value="">- <?php _e('Choose One', 'pmproues');?> -</option>
							<?php
								$levels = pmpro_getAllLevels(true, true);
								foreach($levels as $level) {
								?>
								<option value="<?php echo $level->id;?>"><?php echo $level->name;?></option>
								<?php
								}
							?>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row" valign="top"><label for="pmproues_billing_amount"><?php _e('New Billing Amount', 'pmproues');?>:</label></th>
					<td>
						<?php
						if(pmpro_getCurrencyPosition() == "left")
							echo $pmpro_currency_symbol;
						?>
						<input id="pmproues_billing_amount" name="pmproues_billing_amount" type="text" size="20" value="<?php echo esc_attr($pmproues_billing_amount);?>" /> 
						<?php
						if(pmpro_getCurrencyPosition() == "right")
							echo $pmpro_currency_symbol;
						?>
						<small><?php _e('per', 'pmpro');?></small>
						<input id="pmproues_cycle_number" name="pmproues_cycle_number" type="text" size="10" value="<?php echo esc_attr($pmproues_cycle_number);?>" />
						<select id="pmproues_cycle_period" name="pmproues_cycle_period">
						  <?php
							$cycles = array( __('Day(s)', 'pmpro') => 'Day', __('Week(s)', 'pmpro') => 'Week', __('Month(s)', 'pmpro') => 'Month', __('Year(s)', 'pmpro') => 'Year' );
							foreach ( $cycles as $name => $value ) {
							  echo "<option value='$value'";
							  if ( $pmproues_cycle_period == $value ) echo " selected='selected'";
							  echo ">$name</option>";
							}
						  ?>
						</select>
						<br /><small>
							<?php _e('Use billing amount of 0 to cancel subscriptions.', 'pmproues'); ?>
							<?php if($gateway == "stripe") { ?>
								<br /><strong <?php if(!empty($pmpro_stripe_error)) { ?>class="pmpro_red"<?php } ?>><?php _e('Stripe integration currently only supports billing periods of "Week", "Month" or "Year".', 'pmpro');?>
							<?php } elseif($gateway == "braintree") { ?>
								<br /><strong <?php if(!empty($pmpro_braintree_error)) { ?>class="pmpro_red"<?php } ?>><?php _e('Braintree integration currently only supports billing periods of "Month" or "Year".', 'pmpro');?>						
							<?php } elseif($gateway == "payflowpro") { ?>
								<br /><strong <?php if(!empty($pmpro_payflow_error)) { ?>class="pmpro_red"<?php } ?>><?php _e('Payflow integration currently only supports billing frequencies of 1 and billing periods of "Week", "Month" or "Year".', 'pmpro');?>
							<?php } ?>
						</small>	
						<?php if($gateway == "braintree" && $edit < 0) { ?>
							<p class="pmpro_message"><strong><?php _e('Note', 'pmpro');?>:</strong> <?php _e('After saving this level, make note of the ID and create a "Plan" in your Braintree dashboard with the same settings and the "Plan ID" set to <em>pmpro_#</em>, where # is the level ID.', 'pmpro');?></p>
						<?php } elseif($gateway == "braintree") { ?>
							<p class="pmpro_message"><strong><?php _e('Note', 'pmpro');?>:</strong> <?php _e('You will need to create a "Plan" in your Braintree dashboard with the same settings and the "Plan ID" set to', 'pmpro');?> <em>pmpro_<?php echo $level->id;?></em>.</p>
						<?php } ?>						
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="pmproues_live"><?php _e('Test or Live', 'pmproues');?></label>
					</th>
					<td>
						<select name="pmproues_live" id="pmproues_live">
							<option value="0"><?php _e('Test Run', 'pmproues');?></option>
							<option value="1"><?php _e('Live Run', 'pmproues');?></option>
						</select>
						<small><?php _e('When testing, planned updates are shown but not made at the gateway.', 'pmproues');?></small>
					</td>
				</tr>
				
			</tbody>
		</table>
		
		<p class="submit topborder">
			<input type="hidden" name="updatesubscriptions" value="1" />
			<input name="run" type="submit" class="button-primary" value="<?php _e('Run Update', 'pmproues'); ?>" />		
		</p>
	</form>
<?php } ?>
	
<?php
	require_once(PMPRO_DIR . "/adminpages/admin_footer.php");	
}

/*
	Load an update via AJAX
*/
function pmproues_wp_ajax() {
	//make sure the user is an admin
	if(!current_user_can('manage_options'))
		exit;
		
	//get values
	$gateway = $_REQUEST['gateway'];
	$level = $_REQUEST['level'];
	$billing_amount = $_REQUEST['billing_amount'];
	$cycle_number = $_REQUEST['cycle_number'];
	$cycle_period = $_REQUEST['cycle_period'];
	$live = $_REQUEST['live'];
	
	if(empty($_REQUEST['limit']))
		$limit = 5;
	else
		$limit = intval($_REQUEST['limit']);
	
	//continue progress?
	$hash = substr(md5($gateway . $level . $billing_amount . $cycle_number . $cycle_period . $live), 0, 16);
	$last_user = get_transient('pmproues_update_last_row_' . $hash);
		
	if(empty($last_user))
		$last_user = 0;		
	
	//find members
	global $wpdb;
	$sqlQuery = "SELECT user_id FROM $wpdb->pmpro_memberships_users WHERE user_id > $last_user AND membership_id = '" . intval($level) . "' AND status = 'active' ORDER BY user_id LIMIT $limit";
		
	$member_ids = $wpdb->get_col($sqlQuery);
	
	if(empty($member_ids)) {
		delete_transient('pmproues_update_last_row_' . $hash);
		echo "done";
		exit;
	} else {		
		//update subs
		foreach($member_ids as $member_id) {
			
			$last_user = $member_id;
			echo "\n----\nMember ID #" . $member_id . ". ";
			
			//get user
			$user = get_userdata($member_id);

			//no user?
			if(empty($user) || empty($user->ID)) {
				echo "Could not find user. ";
				continue;
			}
			else
				echo "User found. (" . $user->user_email . ") ";
			
			//get order
			$order = new MemberOrder();
			$order->getLastMemberOrder($user->ID);
			
			//no order?
			if(empty($order->id)) {
				echo "Could not find order. ";
				continue;
			}
			else
				echo "Order found. (" . $order->code . ") ";
			
			//different gateway?
			if($order->gateway != $gateway) {
				echo "Different gateway. ";
				continue;
			}
			else
				echo "Gateway matches. ";
				
			//okay find the sub
			if(empty($order->subscription_transaction_id)) {
				echo "No subscription transaction ID. ";
				continue;
			}
			else
				echo "Subscription ID found. ";
			
			if(empty($live)) {
				echo "Would have updated the subscription here, but we're just testing. ";
				continue;
			}

			//let's do it live!
			if($gateway == "stripe") {
				
				/*
					Note: This code is copied and modified from the user_profile_fields_save method.
				*/
				//get level for user
				$user_level = pmpro_getMembershipLevelForUser($user->ID);
				
				//get current plan at Stripe to get payment date
				$order->Gateway->getCustomer($order);
				$subscription = $order->Gateway->getSubscription($order);
				
				if(!empty($subscription))
				{
					$end_timestamp = $subscription->current_period_end;
					//cancel the old subscription
					if(!$order->Gateway->cancelSubscriptionAtGateway($subscription))
					{
						echo "Could not cancel the old subscription. Skipping. ";
						continue;
					}
				}
				//if we didn't get an end date, let's set one one cycle out
				if(empty($end_timestamp))
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
				//update subscription
				$update_order->Gateway->subscribe($update_order, false);
				//update membership
				$sqlQuery = "UPDATE $wpdb->pmpro_memberships_users
								SET billing_amount = '" . esc_sql($billing_amount) . "',
									cycle_number = '" . esc_sql($cycle_number) . "',
									cycle_period = '" . esc_sql($cycle_period) . "',
									trial_amount = '',
									trial_limit = ''
								WHERE user_id = '" . esc_sql($user->ID) . "'
									AND membership_id = '" . esc_sql($order->membership_id) . "'
									AND status = 'active'
								LIMIT 1";
				$wpdb->query($sqlQuery);
				//save order so we know which plan to look for at stripe (order code = plan id)
				$update_order->status = "success";
				$update_order->saveOrder();

				echo "ORDER UPDATED!";
			}
		}
	}
	
	set_transient('pmproues_update_last_row_' . $hash, $last_user, 60*60*24);
	
	exit;
}
add_action('wp_ajax_pmpro_update_existing_subscriptions', 'pmproues_wp_ajax');