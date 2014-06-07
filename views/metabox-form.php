
<select size="1" name="_gfdpspxpay_form">
	<option value="">-- please choose --</option>
	<?php
	foreach ($forms as $form) {
		// only if form for this feed, or without a feed
		if ($form->id == $feed->FormID || !isset($feedMap[$form->id])) {
			$selected = selected($feed->FormID, $form->id, false);
			echo "<option value='{$form->id}' $selected>", esc_html($form->title), "</option>\n";
		}
	}
	?>
</select>

