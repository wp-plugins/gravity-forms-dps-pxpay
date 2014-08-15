<?php

/**
* custom exception types
*/
class GFDpsPxPayException extends Exception {}
class GFDpsPxPayCurlException extends Exception {}

/**
* class for managing the plugin
*/
class GFDpsPxPayPlugin {
	public $urlBase;									// string: base URL path to files in plugin
	public $options;									// array of plugin options

	private $validationMessage = '';					// current feed mapping form fields to payment fields
	private $feed = null;								// current feed mapping form fields to payment fields
	private $formData = null;							// current form data collected from form

	/**
	* static method for getting the instance of this singleton object
	* @return self
	*/
	public static function getInstance() {
		static $instance = null;

		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	* initialise plugin
	*/
	private function __construct() {
		// grab options, setting new defaults for any that are missing
		$this->initOptions();

		// record plugin URL base
		$this->urlBase = plugin_dir_url(GFDPSPXPAY_PLUGIN_FILE);

		add_action('init', array($this, 'init'));
		add_action('parse_request',  array($this, 'processDpsReturn'));		// process DPS PxPay return
		add_action('wp',  array($this, 'processFormConfirmation'), 5);		// process redirect to GF confirmation
	}

	/**
	* initialise plug-in options, handling undefined options by setting defaults
	*/
	private function initOptions() {
		$defaults = array (
			'userID' => '',
			'userKey' => '',
			'testID' => '',
			'testKey' => '',
			'useTest' => false,
			'sslVerifyPeer' => true,
		);

		$this->options = (array) get_option(GFDPSPXPAY_PLUGIN_OPTIONS);

		if (count(array_diff(array_keys($defaults), array_keys($this->options))) > 0) {
			$this->options = array_merge($defaults, $this->options);
			update_option(GFDPSPXPAY_PLUGIN_OPTIONS, $this->options);
		}
	}

	/**
	* handle the plugin's init action
	*/
	public function init() {
		// hook into Gravity Forms
		add_filter('gform_logging_supported', array($this, 'enableLogging'));
		add_filter('gform_validation', array($this, 'gformValidation'));
		add_filter('gform_validation_message', array($this, 'gformValidationMessage'), 10, 2);
		add_filter('gform_confirmation', array($this, 'gformConfirmation'), 1000, 4);
		add_filter('gform_disable_post_creation', array($this, 'gformDelayPost'), 10, 3);
		add_filter('gform_disable_user_notification', array($this, 'gformDelayUserNotification'), 10, 3);
		add_filter('gform_disable_admin_notification', array($this, 'gformDelayAdminNotification'), 10, 3);
		add_filter('gform_disable_notification', array($this, 'gformDelayNotification'), 10, 4);
		add_filter('gform_custom_merge_tags', array($this, 'gformCustomMergeTags'), 10, 4);
		add_filter('gform_replace_merge_tags', array($this, 'gformReplaceMergeTags'), 10, 7);

		// register custom post types
		$this->registerTypeFeed();

		if (is_admin()) {
			// kick off the admin handling
			new GFDpsPxPayAdmin($this);
		}
	}

	/**
	* register custom post type for PxPay form field mappings
	*/
	protected function registerTypeFeed() {
		// register the post type
		register_post_type(GFDPSPXPAY_TYPE_FEED, array(
			'labels' => array (
				'name' => 'DPS PxPay Feeds',
				'singular_name' => 'DPS PxPay Feed',
				'add_new_item' => 'Add New DPS PxPay Feed',
				'edit_item' => 'Edit DPS PxPay Feed',
				'new_item' => 'New DPS PxPay Feed',
				'view_item' => 'View DPS PxPay Feed',
				'search_items' => 'Search DPS PxPay Feeds',
				'not_found' => 'No DPS PxPay feeds found',
				'not_found_in_trash' => 'No DPS PxPay feeds found in Trash',
				'parent_item_colon' => 'Parent DPS PxPay feed',
			),
			'description' => 'DPS PxPay Feeds, as a custom post type',
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'hierarchical' => false,
			'has_archive' => false,
			//~ 'capabilities' => array (
			//~ ),
			'supports' => array('null'),
			'rewrite' => false,
		));
	}

	/**
	* filter whether post creation from form is enabled (yet)
	* @param bool $is_disabled
	* @param array $form
	* @param array $lead
	* @return bool
	*/
	public function gformDelayPost($is_disabled, $form, $lead) {
		$feed = $this->getFeed($form['id']);
		$is_disabled = !empty($feed->DelayPost);

		self::log_debug(sprintf('delay post creation: %s; form id %s, lead id %s', $is_disabled ? 'yes' : 'no', $form['id'], $lead['id']));

		return $is_disabled;
	}

	/**
	* deprecated: filter whether form triggers autoresponder (yet)
	* @param bool $is_disabled
	* @param array $form
	* @param array $lead
	* @return bool
	*/
	public function gformDelayUserNotification($is_disabled, $form, $lead) {
		$feed = $this->getFeed($form['id']);
		$is_disabled = !empty($feed->DelayAutorespond);

		$this->log_debug(sprintf('delay user notification: %s; form id %s, lead id %s', $is_disabled ? 'yes' : 'no', $form['id'], $lead['id']));

		return $is_disabled;
	}

	/**
	* deprecated: filter whether form triggers admin notification (yet)
	* @param bool $is_disabled
	* @param array $form
	* @param array $lead
	* @return bool
	*/
	public function gformDelayAdminNotification($is_disabled, $form, $lead) {
		$feed = $this->getFeed($form['id']);
		$is_disabled = !empty($feed->DelayNotify);

		$this->log_debug(sprintf('delay admin notification: %s; form id %s, lead id %s', $is_disabled ? 'yes' : 'no', $form['id'], $lead['id']));

		return $is_disabled;
	}

	/**
	* filter whether form triggers admin notification (yet)
	* @param bool $is_disabled
	* @param array $notification
	* @param array $form
	* @param array $lead
	* @return bool
	*/
	public function gformDelayNotification($is_disabled, $notification, $form, $lead) {
		$feed = $this->getFeed($form['id']);

		if ($feed) {
			switch (rgar($notification, 'type')) {
				// old "user" notification
				case 'user':
					if ($feed->DelayAutorespond) {
						$is_disabled = true;
					}
					break;

				// old "admin" notification
				case 'admin':
					if ($feed->DelayNotify) {
						$is_disabled = true;
					}
					break;

				// new since 1.7, add any notification you like
				default:
					if (trim($notification['to']) == '{admin_email}') {
						if ($feed->DelayNotify) {
							$is_disabled = true;
						}
					}
					else {
						if ($feed->DelayAutorespond) {
							$is_disabled = true;
						}
					}
					break;
			}
		}

		$this->log_debug(sprintf('delay notification: %s; form id %s, lead id %s, notification "%s"', $is_disabled ? 'yes' : 'no',
			$form['id'], $lead['id'], $notification['name']));

		return $is_disabled;
	}

	/**
	* process a form validation filter hook; if can find a total, attempt to bill it
	* @param array $data an array with elements is_valid (boolean) and form (array of form elements)
	* @return array
	*/
	public function gformValidation($data) {

		// make sure all other validations passed
		if ($data['is_valid']) {

			$feed = $this->getFeed($data['form']['id']);
			if ($feed) {
				$formData = $this->getFormData($data['form']);

				// make sure form hasn't already been submitted / processed
				if ($this->hasFormBeenProcessed($data['form'])) {
					$data['is_valid'] = false;
					$this->validationMessage .= "Payment already submitted and processed - please close your browser window.\n";
				}

				// make sure that we have something to bill
				// TODO: conditional payments
				//~ else if (!$formData->isCcHidden() && $formData->isLastPage() && is_array($formData->ccField)) {
					if (!$formData->hasPurchaseFields()) {
						$data['is_valid'] = false;
						$this->validationMessage .= "This form has no products or totals; unable to process transaction.\n";
					}
				//~ }
			}
		}

		return $data;
	}

	/**
	* alter the validation message
	* @param string $msg
	* @param array $form
	* @return string
	*/
	public function gformValidationMessage($msg, $form) {
		if ($this->validationMessage) {
			$msg = "<div class='validation_error'>" . nl2br($this->validationMessage) . "</div>";
		}

		return $msg;
	}

	/**
	* on form confirmation, send user's browser to DPS PxPay with required data
	* @param mixed $confirmation text or redirect for form submission
	* @param array $form the form submission data
	* @param array $entry the form entry
	* @param bool $ajax form submission via AJAX
	* @return mixed
	*/
	public function gformConfirmation($confirmation, $form, $entry, $ajax) {

		// run away if not for the current form
		if (RGForms::post('gform_submit') != $form['id']) {
			return $confirmation;
		}

		// get feed mapping form fields to payment request, run away if not set
		$feed = $this->getFeed($form['id']);
		if (!$feed) {
			return $confirmation;
		}

		// run away if nothing to charge
		$formData = $this->getFormData($form);
		if (empty($formData->total)) {
			return $confirmation;
		}

		// generate a unique transactiond ID to avoid collisions, e.g. between different installations using the same PxPay account
		// use last three characters of entry ID as prefix, to avoid collisions with entries created at same microsecond
		// uniqid() generates 13-character string, plus 3 characters from entry ID = 16 characters which is max for field
		$transactionID = uniqid(substr($entry['id'], -3));

		// allow plugins/themes to modify transaction ID; NB: must remain unique for PxPay account!
		$transactionID = apply_filters('gfdpspxpay_invoice_trans_number', $transactionID, $form);

		// record payment gateway and generated transaction number, for later reference
		gform_update_meta($entry['id'], 'payment_gateway', 'gfdpspxpay');
		gform_update_meta($entry['id'], 'gfdpspxpay_txn_id', $transactionID);

		// build a payment request and execute on API
		list($userID, $userKey) = $this->getDpsCredentials($this->options['useTest']);
		$paymentReq = new GFDpsPxPayPayment($userID, $userKey);
		$paymentReq->txnType = 'Purchase';
		$paymentReq->amount = $formData->total;
		$paymentReq->currency = GFCommon::get_currency();
		$paymentReq->transactionNumber = $transactionID;
		$paymentReq->invoiceReference = $formData->MerchantReference;
		$paymentReq->option1 = $formData->TxnData1;
		$paymentReq->option2 = $formData->TxnData2;
		$paymentReq->option3 = $formData->TxnData3;
		$paymentReq->invoiceDescription = $feed->Opt;
		$paymentReq->emailAddress = $formData->EmailAddress;
		$paymentReq->urlSuccess = home_url(GFDPSPXPAY_RETURN);
		$paymentReq->urlFail = home_url(GFDPSPXPAY_RETURN);			// NB: redirection will happen after transaction status is updated

		// allow plugins/themes to modify invoice description and reference, and set option fields
		$paymentReq->invoiceDescription = apply_filters('gfdpspxpay_invoice_desc', $paymentReq->invoiceDescription, $form);
		$paymentReq->invoiceReference = apply_filters('gfdpspxpay_invoice_ref', $paymentReq->invoiceReference, $form);
		$paymentReq->option1 = apply_filters('gfdpspxpay_invoice_txndata1', $paymentReq->option1, $form);
		$paymentReq->option2 = apply_filters('gfdpspxpay_invoice_txndata2', $paymentReq->option2, $form);
		$paymentReq->option3 = apply_filters('gfdpspxpay_invoice_txndata3', $paymentReq->option3, $form);

//~ error_log(__METHOD__ . "\n" . print_r($paymentReq,1));
//~ error_log(__METHOD__ . "\n" . $paymentReq->getPaymentXML());

		self::log_debug(sprintf('%s gateway, invoice ref: %s, transaction: %s, amount: %s',
			$this->options['useTest'] ? 'test' : 'live',
			$paymentReq->invoiceReference, $paymentReq->transactionNumber, $paymentReq->amount));

		try {
			$response = $paymentReq->processPayment();

//~ error_log(__METHOD__ . "\n" . print_r($response,1));

			if ($response->isValid) {
				// set lead payment status to Processing
				GFFormsModel::update_lead_property($entry['id'], 'payment_status', 'Processing');

				// NB: GF handles redirect via JavaScript if headers already sent, or AJAX
				$confirmation = array('redirect' => $response->paymentURL);

				self::log_debug('Payment Express request valid, redirecting...');
			}
			else {
				self::log_debug('Payment Express request invalid');
			}
		}
		catch (GFDpsPxPayException $e) {
			// TODO: what now?
			GFFormsModel::update_lead_property($entry['id'], 'payment_status', 'Failed');
			echo nl2br(esc_html($e->getMessage()));
			self::log_error(__METHOD__ . ": " . $e->getMessage());
			exit;
		}

		return $confirmation;
	}

	/**
	* return from DPS PxPay website, retrieve and process payment result and redirect to form
	*/
	public function processDpsReturn() {
		// must parse out query params ourselves, to prevent the result param getting dropped / filtered out
		// [speculation: maybe it's an anti-malware filter watching for base64-encoded injection attacks?]
		$parts = parse_url($_SERVER['REQUEST_URI']);
		$path = $parts['path'];
		if (isset($parts['query'])) {
			parse_str($parts['query'], $args);
		}
		else {
			$args = array();
		}

		// check for request path containing our path element, and a result argument
		if (strpos($path, GFDPSPXPAY_RETURN) !== false && isset($args['result'])) {
			list($userID, $userKey) = $this->getDpsCredentials($this->options['useTest']);

			$resultReq = new GFDpsPxPayResult($userID, $userKey);
			$resultReq->result = stripslashes($args['result']);

//~ error_log(__METHOD__ . "\n" . print_r($resultReq,1));
//~ error_log(__METHOD__ . "\n" . $resultReq->getResultXML());

			try {
				$response = $resultReq->processResult();

//~ error_log(__METHOD__ . "\n" . print_r($response,1));

				if ($response->isValid) {
					global $wpdb;
					$sql = "select lead_id from {$wpdb->prefix}rg_lead_meta where meta_key='gfdpspxpay_txn_id' and meta_value = %s";
					$lead_id = $wpdb->get_var($wpdb->prepare($sql, $response->transactionNumber));

					$lead = GFFormsModel::get_lead($lead_id);
					$form = GFFormsModel::get_form_meta($lead['form_id']);

					// update lead entry, with success/fail details
					if ($response->success) {
						$lead['payment_status'] = 'Approved';
						$lead['payment_date'] = date('Y-m-d H:i:s');
						$lead['payment_amount'] = $response->amount;
						$lead['transaction_id'] = $response->txnRef;
						$lead['transaction_type'] = 1;	// order

						// update the entry
						if (class_exists('GFAPI')) {
							GFAPI::update_entry($lead);
						}
						else {
							GFFormsModel::update_lead($lead);
						}

						// record bank authorisation code
						gform_update_meta($lead['id'], 'authcode', $response->authCode);

						// record entry's unique ID in database
						gform_update_meta($lead['id'], 'gfdpspxpay_unique_id', GFFormsModel::get_form_unique_id($form['id']));

						self::log_debug(sprintf('success, date = %s, id = %s, status = %s, amount = %s, authcode = %s',
							$lead['payment_date'], $lead['transaction_id'], $lead['payment_status'],
							$lead['payment_amount'], $response->authCode));
					}
					else {
						$lead['payment_status'] = 'Failed';
						$lead['transaction_id'] = $response->txnRef;
						$lead['transaction_type'] = 1;	// order

						// update the entry
						if (class_exists('GFAPI')) {
							GFAPI::update_entry($lead);
						}
						else {
							GFFormsModel::update_lead($lead);
						}

						self::log_debug(sprintf('failed; %s', $response->statusText));

						// redirect to failure page if set, otherwise fall through to redirect back to confirmation page
						$feed = $this->getFeed($form['id']);
						if ($feed->UrlFail) {
							wp_redirect($feed->UrlFail);
							exit;
						}
					}

					// redirect to Gravity Forms page, passing form and lead IDs, encoded to deter simple attacks
					$query = "form_id={$lead['form_id']}&lead_id={$lead['id']}";
					$query .= "&hash=" . wp_hash($query);
					wp_redirect(add_query_arg(array(GFDPSPXPAY_RETURN => base64_encode($query)), $lead['source_url']));
					exit;
				}
			}
			catch (GFDpsPxPayException $e) {
				// TODO: what now?
				echo nl2br(esc_html($e->getMessage()));
				self::log_error(__METHOD__ . ': ' . $e->getMessage());
				exit;
			}
		}
	}

	/**
	* payment processed and recorded, show confirmation message / page
	*/
	public function processFormConfirmation() {
		// check for redirect to Gravity Forms page with our encoded parameters
		if (isset($_GET[GFDPSPXPAY_RETURN])) {
			// decode the encoded form and lead parameters
			parse_str(base64_decode($_GET[GFDPSPXPAY_RETURN]), $query);

//~ error_log(__METHOD__ . "\n" . print_r($query,1));

			// make sure we have a match
			if (wp_hash("form_id={$query['form_id']}&lead_id={$query['lead_id']}") == $query['hash']) {

				// stop WordPress SEO from stripping off our query parameters and redirecting the page
				global $wpseo_front;
				if (isset($wpseo_front)) {
					remove_action('template_redirect', array($wpseo_front, 'clean_permalink'), 1);
				}

				// load form and lead data
				$form = GFFormsModel::get_form_meta($query['form_id']);
				$lead = GFFormsModel::get_lead($query['lead_id']);

				// get confirmation page
				if (!class_exists('GFFormDisplay'))
					require_once(GFCommon::get_base_path() . '/form_display.php');
				$confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

				// preload the GF submission, ready for processing the confirmation message
				GFFormDisplay::$submission[$form['id']] = array(
					'is_confirmation' => true,
					'confirmation_message' => $confirmation,
					'form' => $form,
					'lead' => $lead,
				);

				// if order hasn't been fulfilled, and have defered actions, act now!
				if (!$lead['is_fulfilled']) {
					$feed = $this->getFeed($form['id']);

					if ($feed->DelayPost) {
						GFFormsModel::create_post($form, $lead);
					}

					if ($feed->DelayNotify || $feed->DelayAutorespond) {
						$this->sendDeferredNotifications($feed, $form, $lead);
					}

					GFFormsModel::update_lead_property($lead['id'], 'is_fulfilled', true);
				}

				// if it's a redirection (page or other URL) then do the redirect now
				if (is_array($confirmation) && isset($confirmation['redirect'])) {
					header('Location: ' . $confirmation['redirect']);
					exit;
				}
			}
		}
	}

	/**
	* send deferred notifications, handling pre- and post-1.7.0 worlds
	* @param array $feed
	* @param array $form the form submission data
	* @param array $lead the form entry
	*/
	protected function sendDeferredNotifications($feed, $form, $lead) {
		if (self::versionCompareGF('1.7.0', '<')) {
			// pre-1.7.0 notifications
			if ($feed->DelayNotify) {
				GFCommon::send_admin_notification($form, $lead);
			}
			if ($feed->DelayAutorespond) {
				GFCommon::send_user_notification($form, $lead);
			}
		}
		else {
			$notifications = GFCommon::get_notifications_to_send("form_submission", $form, $lead);
			foreach ($notifications as $notification) {
				switch (rgar($notification, 'type')) {
					// old "user" notification
					case 'user':
						if ($feed->DelayAutorespond) {
							GFCommon::send_notification($notification, $form, $lead);
						}
						break;

					// old "admin" notification
					case 'admin':
						if ($feed->DelayNotify) {
							GFCommon::send_notification($notification, $form, $lead);
						}
						break;

					// new since 1.7, add any notification you like
					default:
						if (trim($notification['to']) == '{admin_email}') {
							if ($feed->DelayNotify) {
								GFCommon::send_notification($notification, $form, $lead);
							}
						}
						else {
							if ($feed->DelayAutorespond) {
								GFCommon::send_notification($notification, $form, $lead);
							}
						}
						break;
				}
			}
		}
	}

	/**
	* add custom merge tags
	* @param array $merge_tags
	* @param int $form_id
	* @param array $fields
	* @param int $element_id
	* @return array
	*/
	public function gformCustomMergeTags($merge_tags, $form_id, $fields, $element_id) {
		if ($form_id && $this->getFeed($form_id)) {
			$merge_tags[] = array('label' => 'Transaction ID', 'tag' => '{transaction_id}');
			$merge_tags[] = array('label' => 'Auth Code', 'tag' => '{authcode}');
			$merge_tags[] = array('label' => 'Payment Amount', 'tag' => '{payment_amount}');
			$merge_tags[] = array('label' => 'Payment Status', 'tag' => '{payment_status}');
		}

		return $merge_tags;
	}

	/**
	* replace custom merge tags
	* @param string $text
	* @param array $form
	* @param array $lead
	* @param bool $url_encode
	* @param bool $esc_html
	* @param bool $nl2br
	* @param string $format
	* @return string
	*/
	public function gformReplaceMergeTags($text, $form, $lead, $url_encode, $esc_html, $nl2br, $format) {
		$gateway = gform_get_meta($lead['id'], 'payment_gateway');
		if ($gateway == 'gfdpspxpay') {
			$authCode = gform_get_meta($lead['id'], 'authcode');

			// format payment amount as currency
			$payment_amount = isset($lead['payment_amount']) ? GFCommon::format_number($lead['payment_amount'], 'currency') : '';

			$tags = array (
				'{transaction_id}',
				'{payment_status}',
				'{payment_amount}',
				'{authcode}',
			);
			$values = array (
				isset($lead['transaction_id']) ? $lead['transaction_id'] : '',
				isset($lead['payment_status']) ? $lead['payment_status'] : '',
				$payment_amount,
				!empty($authCode) ? $authCode : '',
			);

			$text = str_replace($tags, $values, $text);
		}

		return $text;
	}

	/**
	* get DPS credentials for selected operation mode
	* @param bool $useTest
	* @return array
	*/
	protected function getDpsCredentials($useTest) {
		if ($this->options['useTest']) {
			return array($this->options['testID'], $this->options['testKey']);
		}
		else {
			return array($this->options['userID'], $this->options['userKey']);
		}
	}

	/**
	* check whether this form entry's unique ID has already been used; if so, we've already done a payment attempt.
	* @param array $form
	* @return boolean
	*/
	protected function hasFormBeenProcessed($form) {
		global $wpdb;

		$unique_id = GFFormsModel::get_form_unique_id($form['id']);

		$sql = "select lead_id from {$wpdb->prefix}rg_lead_meta where meta_key='gfdpspxpay_unique_id' and meta_value = %s";
		$lead_id = $wpdb->get_var($wpdb->prepare($sql, $unique_id));

		return !empty($lead_id);
	}

	/**
	* get feed for form
	* @param int $form_id the submitted form's ID
	* @return GFDpsPxPayFeed
	*/
	protected function getFeed($form_id) {
		if ($this->feed !== false && (empty($this->feed) || $this->feed->FormID != $form_id)) {
			$this->feed = GFDpsPxPayFeed::getFormFeed($form_id);
		}

		return $this->feed;
	}

	/**
	* get form data for form
	* @param array $form the form submission data
	* @return GFDpsPxPayFormData
	*/
	protected function getFormData($form) {
		if (empty($this->formData) || $this->formData->formID != $form['id']) {
			$feed = $this->getFeed($form['id']);
			$this->formData = new GFDpsPxPayFormData($form, $feed);
		}

		return $this->formData;
	}

	/**
	* enable Gravity Forms Logging Add-On support for this plugin
	* @param array $plugins
	* @return array
	*/
	public function enableLogging($plugins){
		$plugins['gfdpspxpay'] = 'Gravity Forms DPS PxPay';

		return $plugins;
	}

	/**
	* write an error log via the Gravity Forms Logging Add-On
	* @param string $message
	*/
	public static function log_error($message){
		if (class_exists('GFLogging')) {
			GFLogging::include_logger();
			GFLogging::log_message('gfdpspxpay', $message, KLogger::ERROR);
		}
	}

	/**
	* write an debug message log via the Gravity Forms Logging Add-On
	* @param string $message
	*/
	public static function log_debug($message){
		if (class_exists('GFLogging')) {
			GFLogging::include_logger();
			GFLogging::log_message('gfdpspxpay', $message, KLogger::DEBUG);
		}
	}

	/**
	* send data via cURL (or similar if cURL is unavailable) and return response
	* @param string $url
	* @param string $data
	* @param bool $sslVerifyPeer whether to validate the SSL certificate
	* @return string $response
	* @throws GFDpsPxPayCurlException
	*/
	public static function curlSendRequest($url, $data, $sslVerifyPeer = true) {
		// send data via HTTPS and receive response
		$response = wp_remote_post($url, array(
			'user-agent' => 'Gravity Forms DPS PxPay ' . GFDPSPXPAY_PLUGIN_VERSION,
			'sslverify' => $sslVerifyPeer,
			'timeout' => 60,
			'headers' => array('Content-Type' => 'text/xml; charset=utf-8'),
			'body' => $data,
		));

//~ error_log(__METHOD__ . "\n" . print_r($response,1));

		if (is_wp_error($response)) {
			throw new GFDpsPxPayCurlException($response->get_error_message());
		}

		return $response['body'];
	}

	/**
	* compare Gravity Forms version against target
	* @param string $target
	* @param string $operator
	* @return bool
	*/
	public static function versionCompareGF($target, $operator) {
		if (class_exists('GFCommon')) {
			return version_compare(GFCommon::$version, $target, $operator);
		}

		return false;
	}

}
