jQuery(document).ready(function() {
	//find status
	var $status = jQuery('#pmproues_updates_status');
	var $row = 1;
	var $count = 0;
	var $title = document.title;
	var $cycles = ['|','/','-','\\'];
	var $data = {
		'action': 'pmpro_update_existing_subscriptions',
		'gateway': pmproues.gateway,
		'level': pmproues.level,
		'billing_amount': pmproues.billing_amount,
		'cycle_number': pmproues.cycle_number,
		'cycle_period': pmproues.cycle_period,
		'live': pmproues.live,
		'after_date': pmproues.after_date,
		'before_date': pmproues.before_date,
	};

	//start updates and update status
	if($status.length > 0)
	{
		$status.html($status.html() + '\n' + 'JavaScript Loaded. Starting updates.\n');

		function pmpro_updates()
		{
			jQuery.ajax({
				url: ajaxurl,type:'GET', timeout: 30000,
				dataType: 'html',
				data: $data,
				error: function(xml){
					alert('Error with update. Try refreshing.');				
				},
				success: function(responseHTML){
					if (responseHTML === 'error')
					{
						alert('Error with update. Try refreshing.');
						document.title = $title;
					}
					else if(responseHTML === 'done')
					{
						$status.html($status.html() + '\n----\n\nDone!');
						document.title = '! ' + $title;
						jQuery('#pmproues_updates_intro').html('All updates are complete. <a href="'+window.location.href+'">Start another update.</a>');
						
						jQuery('#pmproues_updates_done').show();
					}
					else
					{
						$count++;
						$status.html($status.html() + responseHTML);
						document.title = $cycles[$count%4] + ' ' + $title;
						$update_timer = setTimeout(function() { pmpro_updates();}, 500);
					}

					//scroll the text area unless the mouse is over it
					if (jQuery('#status:hover').length != 0) {						
						$status.scrollTop($status[0].scrollHeight - $status.height());						
					}
				}
			});
		}

		var $update_timer = setTimeout(function() { pmpro_updates();}, 500);
	}
});