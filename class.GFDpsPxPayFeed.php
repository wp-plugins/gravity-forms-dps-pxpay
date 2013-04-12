<?php
/**
* Feed model class
*/
class GFDpsPxPayFeed {

	public $ID;								// unique ID for feed, same as post ID
	public $FeedName;						// name of feed, same as post_title
	public $FormID;							// ID of form in Gravity Forms
	public $DelayPost;						// boolean: create post only when payment is received
	public $DelayNotify;					// boolean: send admin notification only when payment is received
	public $DelayAutorespond;				// boolean: send user notification only when payment is received
	public $IsEnabled;						// boolean: is this feed enabled?

	// fields set in admin
	public $UrlFail;						// URL to redirect to on transaction failure
	public $Opt;							// optional timeout data, TO=yymmddHHmm

	// field mappings to GF form
	public $MerchantReference;				// merchant reference
	public $EmailAddress;					// optional email address
	public $TxnData1;						// optional data #1
	public $TxnData2;						// optional data #2
	public $TxnData3;						// optional data #3

	protected static $fieldMap = array (
		'FormID'				=> '_gfdpspxpay_form',
		'UrlFail'				=> '_gfdpspxpay_url_fail',
		'MerchantReference'		=> '_gfdpspxpay_merchant_ref',
		'EmailAddress'			=> '_gfdpspxpay_email',
		'TxnData1'				=> '_gfdpspxpay_txndata1',
		'TxnData2'				=> '_gfdpspxpay_txndata2',
		'TxnData3'				=> '_gfdpspxpay_txndata3',
		'Opt'					=> '_gfdpspxpay_opt',
		'DelayPost'				=> '_gfdpspxpay_delay_post',
		'DelayNotify'			=> '_gfdpspxpay_delay_notify',
		'DelayAutorespond'		=> '_gfdpspxpay_delay_autorespond',
	);

	/**
	* @param integer $ID unique ID of feed, or NULL to create an empty object initialised to sensible defaults
	*/
	public function __construct($ID = NULL) {
		if (is_null($ID)) {
			$this->ID = 0;
			$this->IsEnabled = TRUE;
			return;
		}

		$post = get_post($ID);
		if ($post) {
			$this->loadFromPost($post);
		}
		else {
			throw new GFDpsPxPayException(__CLASS__ . ": can't load feed: $ID");
		}
	}

	/**
	* load feed from WordPress post object
	* @param WP_Post $post
	*/
	public function loadFromPost($post) {
		// sanity check -- is it a wine pages feed?
		if ($post->post_type != GFDPSPXPAY_TYPE_FEED) {
			throw new GFDpsPxPayException(__CLASS__ . ": post is not a DPS PxPay feed: {$post->ID}");
		}

		$this->ID = $post->ID;
		$this->FeedName = $post->post_title;
		$this->IsEnabled = ($post->post_status == 'publish');

		$meta = get_post_meta($post->ID);

		foreach (self::$fieldMap as $name => $metaname) {
			$this->$name = self::metaValue($meta, $metaname);
		}
	}

	/**
	* get single value from meta array
	* @param array $meta
	* @param string $key
	* @return mixed
	*/
	protected static function metaValue($meta, $key) {
		return (isset($meta[$key][0])) ? $meta[$key][0] : false;
	}

	/**
	* get inverse map of GF fields to feed fields
	* @return array
	*/
	public function getGfFieldMap() {
		$map = array();

		foreach (array('MerchantReference', 'EmailAddress', 'TxnData1', 'TxnData2', 'TxnData3') as $feedName) {
			if (!empty($this->$feedName)) {
				$map[(string) $this->$feedName] = $feedName;
			}
		}

		return $map;
	}

	/**
	* list all feeds
	* @param int $cat_id optional category ID
	* @return array(WpWinePagesProduct)
	*/
	public static function getList() {
		$feeds = array();

		$args = array (
			'post_type' => GFDPSPXPAY_TYPE_FEED,
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'posts_per_page' => -1,
		);

		$posts = get_posts($args);

		if ($posts) {
			try {
				foreach ($posts as $post) {
					$feed = new self();
					$feed->loadFromPost($post);
					$feeds[] = $feed;
				}
			}
			catch (GFDpsPxPayException $e) {
				$feeds = false;
//~ return $e;
			}
		}

		return $feeds;
	}

	/**
	* get feed for GF form, by form ID
	* @param int $formID
	* @return self
	*/
	public static function getFormFeed($formID) {
		if (!$formID) {
			throw new GFDpsPxPayException(__METHOD__ . ": must give form ID");
		}

		$posts = get_posts(array (
			'post_type' => GFDPSPXPAY_TYPE_FEED,
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'posts_per_page' => 1,
			'meta_key' => '_gfdpspxpay_form',
			'meta_value' => $formID,
		));

		if ($posts && count($posts) > 0) {
			try {
				$feed = new self();
				$feed->loadFromPost($posts[0]);
			}
			catch (GFDpsPxPayException $e) {
				$feed = false;
//~ return $e;
			}
		}
		else {
			$feed = false;
		}

		return $feed;
	}
}
