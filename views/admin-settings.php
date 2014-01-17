<?php
// settings form

global $wp_version;
?>

<?php settings_errors(); ?>

<?php if (version_compare($wp_version, '3.8', '<')) screen_icon(); ?>
<h3>DPS PxPay Settings</h3>

<form action="<?php echo admin_url('options.php'); ?>" method="POST">
	<?php settings_fields(GFDPSPXPAY_PLUGIN_OPTIONS); ?>

	<table class="form-table">

	<tr>
		<th>User ID</th>
		<td>
			<input type='text' class="regular-text" name='gfdpspxpay_plugin[userID]' value="<?php echo esc_attr($options['userID']); ?>" />
		</td>
	</tr>

	<tr>
		<th>User Key</th>
		<td>
			<input type='text' class="large-text" name='gfdpspxpay_plugin[userKey]' value="<?php echo esc_attr($options['userKey']); ?>" />
		</td>
	</tr>

	<tr valign='top'>
		<th>Use Sandbox (testing)
			<span class="gfdpspxpay-opt-admin-test">
				<br />Sandbox requires a separate account that has not been activated for live payments.
			</span>
		</th>
		<td>
			<label><input type="radio" name="gfdpspxpay_plugin[useTest]" value="1" <?php checked($options['useTest'], '1'); ?> />&nbsp;yes</label>
			&nbsp;&nbsp;<label><input type="radio" name="gfdpspxpay_plugin[useTest]" value="0" <?php checked($options['useTest'], '0'); ?> />&nbsp;no</label>
		</td>
	</tr>

	<tr class="gfdpspxpay-opt-admin-test">
		<th>Test ID</th>
		<td>
			<input type='text' class="regular-text" name='gfdpspxpay_plugin[testID]' value="<?php echo esc_attr($options['testID']); ?>" />
		</td>
	</tr>

	<tr class="gfdpspxpay-opt-admin-test">
		<th>Test Key</th>
		<td>
			<input type='text' class="large-text" name='gfdpspxpay_plugin[testKey]' value="<?php echo esc_attr($options['testKey']); ?>" />
		</td>
	</tr>

	</table>

	<?php submit_button(); ?>
</form>

