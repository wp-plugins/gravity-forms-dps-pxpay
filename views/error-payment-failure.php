<?php
// error message displayed on failure of payment request
// replaces confirmation text
?>
<?php echo $anchor; ?>
<div id='gform_confirmation_wrapper_<?php echo $form['id']; ?>' class='gform_confirmation_wrapper <?php echo $cssClass; ?>'>
	<div id='gform_confirmation_message_<?php echo $form['id']; ?>' class='gform_confirmation_message_<?php echo $form['id']; ?> gform_confirmation_message'>
	<p><strong>PxPay payment request error</strong></p>
	<?php echo $error_msg; ?>
	</div>
</div>
