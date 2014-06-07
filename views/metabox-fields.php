
<table class='gfdpspxpay-feed-fields gfdpspxpay-details'>

	<tr>
		<th>Merchant Reference:</th>
		<td>
			<select size="1" name="_gfdpspxpay_merchant_ref">
				<?php if ($fields) echo self::selectFields($feed->MerchantReference, $fields); ?>
			</select> <span class='required' title='required field'>*</span>
		</td>
	</tr>

	<tr>
		<th>TxnData1:</th>
		<td>
			<select size="1" name="_gfdpspxpay_txndata1">
				<?php if ($fields) echo self::selectFields($feed->TxnData1, $fields); ?>
			</select>
		</td>
	</tr>

	<tr>
		<th>TxnData2:</th>
		<td>
			<select size="1" name="_gfdpspxpay_txndata2">
				<?php if ($fields) echo self::selectFields($feed->TxnData2, $fields); ?>
			</select>
		</td>
	</tr>

	<tr>
		<th>TxnData3:</th>
		<td>
			<select size="1" name="_gfdpspxpay_txndata3">
				<?php if ($fields) echo self::selectFields($feed->TxnData3, $fields); ?>
			</select>
		</td>
	</tr>

	<tr>
		<th>Email Address:</th>
		<td>
			<select size="1" name="_gfdpspxpay_email">
				<?php if ($fields) echo self::selectFields($feed->EmailAddress, $fields); ?>
			</select>
		</td>
	</tr>

</table>

<p><em>Please note: this information will appear in your DPS Payline console.</em>
	<br /><em>Email Address is currently accepted by DPS but not stored; we hope this will change soon.</em>
	<br /><em>If you need to see Email Address in DPS Payline, please map it to one of the TxnData fields for now.</em>
</p>

