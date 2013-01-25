<?php

/**
* Options form input fields
*/
class GFDpsPxPayOptionsForm {

	public $userID;
	public $userKey;
	public $testID;
	public $testKey;
	public $useTest;
	//~ public $sslVerifyPeer;

	/**
	* initialise from form post, if posted
	*/
	public function __construct() {
		if (self::isFormPost()) {
			$this->userID = self::getPostValue('userID');
			$this->userKey = self::getPostValue('userKey');
			$this->testID = self::getPostValue('testID');
			$this->testKey = self::getPostValue('testKey');
			$this->useTest = self::getPostValue('useTest');
			//~ $this->sslVerifyPeer = self::getPostValue('sslVerifyPeer');
		}
	}

	/**
	* Is this web request a form post?
	*
	* Checks to see whether the HTML input form was posted.
	*
	* @return boolean
	*/
	public static function isFormPost() {
		return ($_SERVER['REQUEST_METHOD'] == 'POST');
	}

	/**
	* Read a field from form post input.
	*
	* Guaranteed to return a string, trimmed of leading and trailing spaces, sloshes stripped out.
	*
	* @return string
	* @param string $fieldname name of the field in the form post
	*/
	public static function getPostValue($fieldname) {
		return isset($_POST[$fieldname]) ? stripslashes(trim($_POST[$fieldname])) : '';
	}

	/**
	* Validate the form input, and return error messages.
	*
	* Return a string detailing error messages for validation errors discovered,
	* or an empty string if no errors found.
	* The string should be HTML-clean, ready for putting inside a paragraph tag.
	*
	* @return string
	*/
	public function validate() {
		$errmsg = '';

		if (strlen($this->userID) === 0)
			$errmsg .= "# Please enter the DPS user ID.<br/>\n";

		if (strlen($this->userKey) === 0)
			$errmsg .= "# Please enter the DPS user key.<br/>\n";

		if ($this->useTest == 'Y') {
			if (strlen($this->testID) === 0)
				$errmsg .= "# Please enter the DPS test ID.<br/>\n";

			if (strlen($this->testKey) === 0)
				$errmsg .= "# Please enter the DPS test key.<br/>\n";
		}

		return $errmsg;
	}
}

/**
* Options admin
*/
class GFDpsPxPayOptionsAdmin {

	private $plugin;							// handle to the plugin object
	private $menuPage;							// slug for admin menu page
	private $scriptURL = '';
	private $frm;								// handle for the form validator

	/**
	* @param GFDpsPxPayPlugin $plugin handle to the plugin object
	* @param string $menuPage URL slug for this admin menu page
	*/
	public function __construct($plugin, $menuPage) {
		$this->plugin = $plugin;
		$this->menuPage = $menuPage;
		$this->scriptURL = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH) . "?page={$menuPage}";

		wp_enqueue_script('gfdpspxpay-options', $this->plugin->urlBase . 'js/options-admin.min.js', array('jquery'), GFDPSPXPAY_PLUGIN_VERSION, true);
	}

	/**
	* process the admin request
	*/
	public function process() {

		echo "<div class='wrap'>\n";
		screen_icon();
		echo "<h2>Gravity Forms DPS PxPay Payments</h2>\n";

		$this->frm = new GFDpsPxPayOptionsForm();
		if ($this->frm->isFormPost()) {
			$errmsg = $this->frm->validate();
			if (empty($errmsg)) {
				$this->plugin->options['userID'] = $this->frm->userID;
				$this->plugin->options['userKey'] = $this->frm->userKey;
				$this->plugin->options['testID'] = $this->frm->testID;
				$this->plugin->options['testKey'] = $this->frm->testKey;
				$this->plugin->options['useTest'] = ($this->frm->useTest == 'Y');
				//~ $this->plugin->options['sslVerifyPeer'] = ($this->frm->sslVerifyPeer == 'Y');

				update_option(GFDPSPXPAY_PLUGIN_OPTIONS, $this->plugin->options);
				$this->plugin->showMessage(__('Options saved.'));
			}
			else {
				$this->plugin->showError($errmsg);
			}
		}
		else {
			// initialise form from stored options
			$this->frm->userID = $this->plugin->options['userID'];
			$this->frm->userKey = $this->plugin->options['userKey'];
			$this->frm->testID = $this->plugin->options['testID'];
			$this->frm->testKey = $this->plugin->options['testKey'];
			$this->frm->useTest = $this->plugin->options['useTest'] ? 'Y' : 'N';
			//~ $this->frm->sslVerifyPeer = $this->plugin->options['sslVerifyPeer'] ? 'Y' : 'N';
		}

		$feedsURL = 'edit.php?post_type=' . GFDPSPXPAY_TYPE_FEED;

		?>
		<form action="<?php echo $this->scriptURL; ?>" method="post">
			<table class="form-table">

				<tr>
					<th>User ID</th>
					<td>
						<input type='text' class="regular-text" name='userID' value="<?php echo htmlspecialchars($this->frm->userID); ?>" />
					</td>
				</tr>

				<tr>
					<th>User Key</th>
					<td>
						<input type='text' class="large-text" name='userKey' value="<?php echo htmlspecialchars($this->frm->userKey); ?>" />
					</td>
				</tr>

				<tr valign='top'>
					<th>Use Sandbox (testing)
						<span class="gfdpspxpay-opt-admin-test">
							<br />Sandbox requires a separate account that has not been activated for live payments.
						</span>
					</th>
					<td>
						<label><input type="radio" name="useTest" value="Y" <?php checked($this->frm->useTest, 'Y'); ?> />&nbsp;yes</label>
						&nbsp;&nbsp;<label><input type="radio" name="useTest" value="N" <?php checked($this->frm->useTest, 'N'); ?> />&nbsp;no</label>
					</td>
				</tr>

				<tr class="gfdpspxpay-opt-admin-test">
					<th>Test ID</th>
					<td>
						<input type='text' class="regular-text" name='testID' value="<?php echo htmlspecialchars($this->frm->testID); ?>" />
					</td>
				</tr>

				<tr class="gfdpspxpay-opt-admin-test">
					<th>Test Key</th>
					<td>
						<input type='text' class="large-text" name='testKey' value="<?php echo htmlspecialchars($this->frm->testKey); ?>" />
					</td>
				</tr>

			</table>
			<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="Save Changes" />
			<input type="hidden" name="action" value="save" />
			<?php wp_nonce_field($this->menuPage); ?>
			</p>
		</form>

		<p><a href="<?php echo $feedsURL; ?>">Edit feeds mapping forms to DPS PxPay</a></p>

		</div>

		<?php
	}
}
