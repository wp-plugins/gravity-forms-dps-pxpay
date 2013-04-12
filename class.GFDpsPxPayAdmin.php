<?php

/**
* class for admin screens
*/
class GFDpsPxPayAdmin {

	const MENU_PAGE = 'gfdpspxpay';					// slug for menu page(s)

	protected $plugin;

	/**
	* @param GFDpsPxPayPlugin $plugin
	*/
	public function __construct($plugin) {
		$this->plugin = $plugin;

		// handle admin init action
		add_action('admin_init', array($this, 'actionAdminInit'));

		// add GravityForms hooks
		add_filter("gform_addon_navigation", array($this, 'gformAddonNavigation'));
		add_action("gform_entry_info", array($this, 'gformEntryInfo'), 10, 2);

		// hook for showing admin messages
		add_action('admin_notices', array($this, 'actionAdminNotices'));

		// add action hook for adding plugin action links
		add_action('plugin_action_links_' . GFDPSPXPAY_PLUGIN_NAME, array($this, 'addPluginActionLinks'));

		// hook for adding links to plugin info
		add_filter('plugin_row_meta', array($this, 'addPluginDetailsLinks'), 10, 2);

		// hook for enqueuing admin styles
		add_filter('admin_enqueue_scripts', array($this, 'enqueueScripts'));

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

		// when editing pages, $typenow isn't set until later!
		// kludge thanks to WooCommerce :)
		if (empty($typenow) && !empty($_GET['post'])) {
			$post = get_post($_GET['post']);
			$typenow = $post->post_type;
		}

		if ($typenow && $typenow == GFDPSPXPAY_TYPE_FEED) {
			new GFDpsPxPayFeedAdmin($this->plugin);
		}
	}

	/**
	* enqueue our admin stylesheet
	*/
	public function enqueueScripts() {
		wp_enqueue_style('gfdpspxpay-admin', "{$this->plugin->urlBase}style-admin.css", false, GFDPSPXPAY_PLUGIN_VERSION);
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
			$settings_link = '<a href="admin.php?page=' . self::MENU_PAGE . '-options">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link);
		}

		return $links;
	}

	/**
	* action hook for adding plugin details links
	*/
	public static function addPluginDetailsLinks($links, $file) {
		if ($file == GFDPSPXPAY_PLUGIN_NAME) {
			$links[] = '<a href="http://wordpress.org/support/plugin/gravityforms-dps-pxpay">' . __('Get help') . '</a>';
			$links[] = '<a href="http://wordpress.org/extend/plugins/gravityforms-dps-pxpay/">' . __('Rating') . '</a>';
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
		// add menu item for options
		$menus[] = array('name' => self::MENU_PAGE.'-options', 'label' => 'DPS PxPay', 'callback' => array($this, 'optionsAdmin'), 'permission' => 'manage_options');

        return $menus;
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
		$admin = new GFDpsPxPayOptionsAdmin($this->plugin, self::MENU_PAGE.'-options');
		$admin->process();
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
