
<p><label><input type="checkbox" name="_gfdpspxpay_delay_notify" value="1" <?php checked($feed->DelayNotify); ?> />
 Send admin notification only when payment is processed</label></p>
<p><label><input type="checkbox" name="_gfdpspxpay_delay_autorespond" value="1" <?php checked($feed->DelayAutorespond); ?> />
 Send user notification only when payment is processed</label></p>
<p><label><input type="checkbox" name="_gfdpspxpay_delay_post" value="1" <?php checked($feed->DelayPost); ?> />
 Create post only when payment is processed</label></p>
<p><label><input type="checkbox" name="_gfdpspxpay_delay_userrego" value="1" <?php checked($feed->DelayUserrego); ?> />
 Register user only when payment is processed</label></p>
<p><label><input type="checkbox" name="_gfdpspxpay_delay_exec_always" value="1" <?php checked($feed->ExecDelayedAlways); ?> />
 Always execute delayed actions, regardless of payment status</label></p>
