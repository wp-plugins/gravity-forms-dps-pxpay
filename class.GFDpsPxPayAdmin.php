<?php

/**
* class for admin screens
*/
class GFDpsPxPayAdmin {

	protected $plugin;

	/**
	* @param GFDpsPxPayPlugin $plugin
	*/
	public function __construct($plugin) {
		$this->plugin = $plugin;

		// admin hooks
		add_action('admin_init', array($this, 'actionAdminInit'));
		add_action('admin_notices', array($this, 'actionAdminNotices'));
		add_action('plugin_action_links_' . GFDPSPXPAY_PLUGIN_NAME, array($this, 'addPluginActionLinks'));
		add_filter('plugin_row_meta', array($this, 'addPluginDetailsLinks'), 10, 2);
		add_filter('admin_enqueue_scripts', array($this, 'enqueueScripts'));

		// GravityForms hooks
		add_filter('gform_addon_navigation', array($this, 'gformAddonNavigation'));
		add_action('forms_page_gf_settings', array($this, 'adminPageGfSettings'));
		add_action('gform_entry_info', array($this, 'gformEntryInfo'), 10, 2);

		// AJAX actions
		add_action('wp_ajax_gfdpspxpay_form_fields', array($this, 'ajaxGfFormFields'));
		add_action('wp_ajax_gfdpspxpay_form_has_feed', array($this, 'ajaxGfFormHasFeed'));
	}

	/**
	* test whether GravityForms plugin is installed and active
	* @return boolean
	*/
	public static function isGfActive() {
		return class_exists('RGForms');
	}

	/**
	* handle admin init action
	*/
	public function actionAdminInit() {
		global $typenow;

		// register plugin settings
		add_settings_section(GFDPSPXPAY_PLUGIN_OPTIONS, false, false, GFDPSPXPAY_PLUGIN_OPTIONS);
		register_setting(GFDPSPXPAY_PLUGIN_OPTIONS, GFDPSPXPAY_PLUGIN_OPTIONS, array($this, 'settingsValidate'));

		// when editing pages, $typenow isn't set until later!
		// kludge thanks to WooCommerce :)
		if (empty($typenow) && !empty($_GET['post'])) {
			$post = get_post($_GET['post']);
			$typenow = $post->post_type;
		}

		if ($typenow && $typenow == GFDPSPXPAY_TYPE_FEED) {
			new GFDpsPxPayFeedAdmin($this->plugin);
		}

		if (isset($_GET['page'])) {
			switch ($_GET['page']) {
				case 'gf_settings':
					// add our settings page to the Gravity Forms settings menu
					RGForms::add_settings_page('DPS PxPay', array($this, 'optionsAdmin'));
					break;

				case 'gfdpspxpay-feeds':
					wp_redirect(admin_url('edit.php?post_type=' . GFDPSPXPAY_TYPE_FEED));
					break;
			}
		}
	}

	/**
	* enqueue our admin stylesheet
	*/
	public function enqueueScripts() {
		$ver = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? time() : GFDPSPXPAY_PLUGIN_VERSION;
		wp_enqueue_style('gfdpspxpay-admin', "{$this->plugin->urlBase}css/admin.css", false, $ver);
	}

	/**
	* show admin messages
	*/
	public function actionAdminNotices() {
		if (!self::isGfActive()) {
			$this->plugin->showError('Gravity Forms DPS PxPay requires <a href="http://www.gravityforms.com/">Gravity Forms</a> to be installed and activated.');
		}
	}

	/**
	* action hook for adding plugin action links
	*/
	public function addPluginActionLinks($links) {
		// add settings link, but only if GravityForms plugin is active
		if (self::isGfActive()) {
			$settings_link = sprintf('<a href="%s">Settings</a>', admin_url('admin.php?page=gf_settings&subview=DPS+PxPay'));
			array_unshift($links, $settings_link);
		}

		return $links;
	}

	/**
	* action hook for adding plugin details links
	*/
	public static function addPluginDetailsLinks($links, $file) {
		if ($file == GFDPSPXPAY_PLUGIN_NAME) {
			$links[] = '<a href="http://wordpress.org/support/plugin/gravity-forms-dps-pxpay">' . __('Get help') . '</a>';
			$links[] = '<a href="http://wordpress.org/plugins/gravity-forms-dps-pxpay/">' . __('Rating') . '</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=C4P55EH25BGTL">' . __('Donate') . '</a>';
		}

		return $links;
	}

	/**
	* filter hook for building GravityForms navigation
	* @param array $menus
	* @return array
	*/
	public function gformAddonNavigation($menus) {
		// add menu item for feeds (NB: adds a link that is redirected to feeds post editor on admin_init action)
		$menus[] = array('name' => 'gfdpspxpay-feeds', 'label' => 'DPS PxPay', 'callback' => array($this, 'feedsAdmin'), 'permission' => 'manage_options');

        return $menus;
	}

	/**
	* clean up settings-updated from settings menu links (put there by settings API because of our settings page!)
	*/
	public function adminPageGfSettings() {
		parse_str($_SERVER['QUERY_STRING'], $qs);
		if (isset($qs['settings-updated']) && $qs['settings-updated']) {
			$_SERVER['REQUEST_URI'] = remove_query_arg('settings-updated', $_SERVER['REQUEST_URI']);
		}
	}

	/**
	* action hook for building the entry details view
	* @param int $form_id
	* @param array $lead
	*/
	public function gformEntryInfo($form_id, $lead) {
		$payment_gateway = gform_get_meta($lead['id'], 'payment_gateway');
		if ($payment_gateway == 'gfdpspxpay') {
			$authCode = gform_get_meta($lead['id'], 'authcode');
			if ($authCode) {
				echo 'Auth Code: ', htmlspecialchars($authCode), "<br /><br />\n";
			}
		}
	}

	/**
	* action hook for processing admin menu item
	*/
	public function optionsAdmin() {
		$min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		$ver = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? time() : GFDPSPXPAY_PLUGIN_VERSION;
		wp_enqueue_script('gfdpspxpay-options', "{$this->plugin->urlBase}js/options-admin$min.js", array('jquery'), $ver, true);

		$options = $this->plugin->options;
		require GFDPSPXPAY_PLUGIN_ROOT . 'views/admin-settings.php';
	}

	/**
	* action hook for processing feeds menu item
	*/
	public function feedsAdmin() {
		// actually handled via redirect on init action...
		//~ $feedsURL = 'edit.php?post_type=' . GFDPSPXPAY_TYPE_FEED;
	}

	/**
	* validate settings on save
	* @param array $input
	* @return array
	*/
	public function settingsValidate($input) {
		$output['userID'] = trim($input['userID']);
		$output['userKey'] = trim($input['userKey']);
		$output['testID'] = trim($input['testID']);
		$output['testKey'] = trim($input['testKey']);
		$output['useTest'] = empty($input['useTest']) ? 0 : 1;
		$output['sslVerifyPeer'] = 1;		// always set (for now anyway!)

		if (empty($output['userID'])) {
			$msg = "Please enter the DPS user ID.";
			add_settings_error(GFDPSPXPAY_PLUGIN_OPTIONS, '', $msg);
		}

		if (empty($output['userKey'])) {
			$msg = "Please enter the DPS user key.";
			add_settings_error(GFDPSPXPAY_PLUGIN_OPTIONS, '', $msg);
		}

		if ($output['useTest']) {
			if (empty($output['testID'])) {
				$msg = "Please enter the DPS test ID.";
				add_settings_error(GFDPSPXPAY_PLUGIN_OPTIONS, '', $msg);
			}

			if (empty($output['testKey'])) {
				$msg = "Please enter the DPS test key.";
				add_settings_error(GFDPSPXPAY_PLUGIN_OPTIONS, '', $msg);
			}
		}

		return $output;
	}

	/**
	* AJAX action to check for GF form already has feed, returning feed ID
	*/
	public function ajaxGfFormHasFeed() {
		$formID = isset($_GET['id']) ? $_GET['id'] : 0;
		if (!$formID) {
			die("Bad form ID: $formID");
		}

		$feed = GFDpsPxPayFeed::getFormFeed($formID);
		echo $feed ? $feed->ID : 0;
		exit;
	}

	/**
	* AJAX action for getting a list of form fields for a form
	*/
	public function ajaxGfFormFields() {
		$formID = isset($_GET['id']) ? $_GET['id'] : 0;
		if (!$formID) {
			die("Bad form ID: $formID");
		}

		$fields = GFDpsPxPayFeedAdmin::getFormFields($formID);
		$html = GFDpsPxPayFeedAdmin::selectFields('', $fields);

		echo $html;
		exit;
	}
}
