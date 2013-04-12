<?php

/**
* Feed admin
*/
class GFDpsPxPayFeedAdmin {

	protected $plugin;							// handle to the plugin object

	/**
	* @param GFDpsPxPayPlugin $plugin handle to the plugin object
	*/
	public function __construct($plugin) {
		$this->plugin = $plugin;

		add_filter('parent_file', array($this, 'filterParentFile'));
		add_action('add_meta_boxes_'.GFDPSPXPAY_TYPE_FEED, array($this, 'actionAddMetaBoxes'));
		add_action('save_post', array($this, 'saveCustomFields'), 10, 2);
		add_filter('manage_'.GFDPSPXPAY_TYPE_FEED.'_posts_columns', array($this, 'filterManageColumns'));
		add_filter('post_row_actions', array($this, 'filterPostRowActions'));
		add_filter('wp_insert_post_data', array($this, 'filterInsertPostData'), 10, 2);
		add_filter('post_updated_messages', array($this, 'filterPostUpdatedMessages'));

		wp_enqueue_script('gfdpspxpay-feed-admin', $this->plugin->urlBase . 'js/feed-admin.min.js', array('jquery'), GFDPSPXPAY_PLUGIN_VERSION, true);
	}

	/**
	* tell WordPress admin that Gravity Forms menu is parent page
	* @param string $parent_file
	* @return string
	*/
	public function filterParentFile($parent_file) {
		global $submenu_file;

		// set parent menu for filter return
		$parent_file = 'gf_edit_forms';

		// set submenu by side effect
		$submenu_file = 'gfdpspxpay-options';

		return $parent_file;
	}

	/**
	* add meta boxes for custom fields
	* @param WP_Post $post
	*/
	public function actionAddMetaBoxes($post) {
		try {
			$feed = new GFDpsPxPayFeed();
			if ($post && $post->ID) {
				$feed->loadFromPost($post);
			}
		}
		catch (GFDpsPxPayException $e) {
			// NOP -- we'll have an empty feed
		}

		add_meta_box('meta_'.GFDPSPXPAY_TYPE_FEED.'_form', 'Gravity Form', array($this, 'metaboxForm'),
			GFDPSPXPAY_TYPE_FEED, 'normal', 'high', array('feed' => $feed));
		add_meta_box('meta_'.GFDPSPXPAY_TYPE_FEED.'_fields', 'Map Form to Transaction', array($this, 'metaboxFields'),
			GFDPSPXPAY_TYPE_FEED, 'normal', 'high', array('feed' => $feed));
		add_meta_box('meta_'.GFDPSPXPAY_TYPE_FEED.'_urls', 'Redirect URLs', array($this, 'metaboxURLs'),
			GFDPSPXPAY_TYPE_FEED, 'normal', 'high', array('feed' => $feed));
		add_meta_box('meta_'.GFDPSPXPAY_TYPE_FEED.'_opts', 'Options', array($this, 'metaboxOpts'),
			GFDPSPXPAY_TYPE_FEED, 'normal', 'high', array('feed' => $feed));
		add_meta_box('meta_'.GFDPSPXPAY_TYPE_FEED.'_list', 'Return to List', array($this, 'metaboxList'),
			GFDPSPXPAY_TYPE_FEED, 'side', 'low', array('feed' => $feed));

		// replace standard Publish box with a custom one
		remove_meta_box('submitdiv', GFDPSPXPAY_TYPE_FEED, 'side');
		add_meta_box('meta_'.GFDPSPXPAY_TYPE_FEED.'_submit', 'Save', array($this, 'metaboxSave'),
			GFDPSPXPAY_TYPE_FEED, 'side', 'high', array('feed' => $feed));
	}

	/**
	* metabox for Return to List link
	* @param WP_Post $post
	* @param array $metabox has metabox id, title, callback, and args elements.
	*/
	public function metaboxList($post, $metabox) {
		$feedsURL = 'edit.php?post_type=' . GFDPSPXPAY_TYPE_FEED;
		echo "<a href=\"$feedsURL\">Click to return to list</a>.\n";
	}

	/**
	* metabox for custom save/publish
	* @param WP_Post $post
	* @param array $metabox has metabox id, title, callback, and args elements.
	*/
	public function metaboxSave($post, $metabox) {
		global $action;

		?>

		<div style="display:none;">
		<?php submit_button( __( 'Save' ), 'button', 'save' ); ?>
		</div>

		<div id="major-publishing-actions">
		<?php do_action('post_submitbox_start'); ?>
		<div id="delete-action">
		<?php
		if ( current_user_can( "delete_post", $post->ID ) ) {
			if ( !EMPTY_TRASH_DAYS )
				$delete_text = __('Delete Permanently');
			else
				$delete_text = __('Move to Trash');
			?>
			<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
		} ?>
		</div>

		<div id="publishing-action">
		<span class="spinner"></span>
			<input name="original_publish" type="hidden" id="original_publish" value="Save" />
			<?php submit_button('Save', 'primary button-large', 'publish', false, array() ); ?>
		</div>
		<div class="clear"></div>

		</div>

		<?php
	}

	/**
	* metabox for Gravity Form field, only listing forms that don't have a feed or are current feed's form
	* @param WP_Post $post
	* @param array $metabox has metabox id, title, callback, and args elements.
	*/
	public function metaboxForm($post, $metabox) {
		$feed = $metabox['args']['feed'];
		$forms = GFFormsModel::get_forms();

		$feeds = GFDpsPxPayFeed::getList();
		$feedMap = array();
		foreach ($feeds as $f) {
			$feedMap[$f->FormID] = 1;
		}

		?>
		<select size="1" name="_gfdpspxpay_form">
			<option value="">-- please choose --</option>
			<?php
			foreach ($forms as $form) {
				// only if form for this feed, or without a feed
				if ($form->id == $feed->FormID || !isset($feedMap[$form->id])) {
					$selected = selected($feed->FormID, $form->id);
					echo "<option value='{$form->id}' $selected>", htmlspecialchars($form->title), "</option>\n";
				}
			}
			?>
		</select>

		<?php
	}

	/**
	* metabox for Redirect URLs
	* @param WP_Post $post
	* @param array $metabox has metabox id, title, callback, and args elements.
	*/
	public function metaboxURLs($post, $metabox) {
		$feed = $metabox['args']['feed'];
		$UrlFail = htmlspecialchars($feed->UrlFail);

		?>
		<p><label>URL to redirect to on transaction failure:</label><br />
			<input type="url" class='large-text' name="_gfdpspxpay_url_fail" value="<?php echo $UrlFail; ?>" /></p>

		<p><em>Please note: standard Gravity Forms submission logic applies if the DPS transaction is successful.</em></p>

		<?php
	}

	/**
	* metabox for options
	* @param WP_Post $post
	* @param array $metabox has metabox id, title, callback, and args elements.
	*/
	public function metaboxOpts($post, $metabox) {
		$feed = $metabox['args']['feed'];
		?>
		<p><label><input type="checkbox" name="_gfdpspxpay_delay_notify" value="1" <?php checked($feed->DelayNotify); ?> />
		 Send admin notification only when payment is received</label></p>
		<p><label><input type="checkbox" name="_gfdpspxpay_delay_autorespond" value="1" <?php checked($feed->DelayAutorespond); ?> />
		 Send user notification only when payment is received</label></p>
		<p><label><input type="checkbox" name="_gfdpspxpay_delay_post" value="1" <?php checked($feed->DelayPost); ?> />
		 Create post only when payment is received</label></p>

		<?php
	}

	/**
	* metabox for Fields to Map
	* @param WP_Post $post
	* @param array $metabox has metabox id, title, callback, and args elements.
	*/
	public function metaboxFields($post, $metabox) {
		wp_nonce_field(GFDPSPXPAY_TYPE_FEED.'_save', GFDPSPXPAY_TYPE_FEED.'_wpnonce', false, true);

		$feed = $metabox['args']['feed'];
		$MerchantReference = htmlspecialchars($feed->MerchantReference);
		$EmailAddress = htmlspecialchars($feed->EmailAddress);
		$TxnData1 = htmlspecialchars($feed->TxnData1);
		$TxnData2 = htmlspecialchars($feed->TxnData2);
		$TxnData3 = htmlspecialchars($feed->TxnData3);

		$fields = $feed->FormID ? self::getFormFields($feed->FormID) : false;

		?>
		<table class='gfdpspxpay-feed-fields gfdpspxpay-details'>

			<tr>
				<th>Merchant Reference:</th>
				<td>
					<select size="1" name="_gfdpspxpay_merchant_ref">
						<?php if ($fields) echo self::selectFields($MerchantReference, $fields); ?>
					</select> <span class='required' title='required field'>*</span>
				</td>
			</tr>

			<tr>
				<th>TxnData1:</th>
				<td>
					<select size="1" name="_gfdpspxpay_txndata1">
						<?php if ($fields) echo self::selectFields($TxnData1, $fields); ?>
					</select>
				</td>
			</tr>

			<tr>
				<th>TxnData2:</th>
				<td>
					<select size="1" name="_gfdpspxpay_txndata2">
						<?php if ($fields) echo self::selectFields($TxnData2, $fields); ?>
					</select>
				</td>
			</tr>

			<tr>
				<th>TxnData3:</th>
				<td>
					<select size="1" name="_gfdpspxpay_txndata3">
						<?php if ($fields) echo self::selectFields($TxnData3, $fields); ?>
					</select>
				</td>
			</tr>

			<tr>
				<th>Email Address:</th>
				<td>
					<select size="1" name="_gfdpspxpay_email">
						<?php if ($fields) echo self::selectFields($EmailAddress, $fields); ?>
					</select>
				</td>
			</tr>

		</table>

		<p><em>Please note: this information will appear in your DPS Payline console.</em>
			<br /><em>Email Address is currently accepted by DPS but not stored; we hope this will change soon.</em>
			<br /><em>If you need to see Email Address in DPS Payline, please map it to one of the TxnData fields for now.</em>
		</p>

		<?php
	}

	/**
	* filter insert fields, to set post title from form name
	* @param array $data the post insert data
	* @param array $postarr data from the form post
	* @return array
	*/
	public function filterInsertPostData($data, $postarr) {
		$formID = isset($postarr['_gfdpspxpay_form']) ? intval($postarr['_gfdpspxpay_form']) : 0;
		if ($formID) {
			$form = GFFormsModel::get_form($formID);
			$data['post_title'] = $form->title;
			$data['post_name'] = sanitize_title($form->title);
		}

		return $data;
	}

	/**
	* save custom fields
	*/
	public function saveCustomFields($postID) {
		// Check whether this is an auto save routine. If it is, our form has not been submitted, so we don't want to do anything
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $postID;

		global $typenow;

		// handle post type
		if ($typenow == GFDPSPXPAY_TYPE_FEED) {
			// verify permission to edit post / page
			if (!current_user_can('edit_post', $postID))
				return $postID;

			$fields = array (
				'_gfdpspxpay_form',
				'_gfdpspxpay_url_fail',
				'_gfdpspxpay_url_success',
				'_gfdpspxpay_merchant_ref',
				'_gfdpspxpay_email',
				'_gfdpspxpay_txndata1',
				'_gfdpspxpay_txndata2',
				'_gfdpspxpay_txndata3',
				'_gfdpspxpay_opt',
				'_gfdpspxpay_delay_post',
				'_gfdpspxpay_delay_notify',
				'_gfdpspxpay_delay_autorespond',
			);

			if (isset($_POST['_gfdpspxpay_form'])) {
				if (!wp_verify_nonce($_POST[GFDPSPXPAY_TYPE_FEED.'_wpnonce'], GFDPSPXPAY_TYPE_FEED.'_save'))
					die('Security exception');
			}

			foreach ($fields as $fieldName) {
				if (isset($_POST[$fieldName])) {

					$value = $_POST[$fieldName];

					if (empty($value))
						delete_post_meta($postID, $fieldName);
					else
						update_post_meta($postID, $fieldName, $value);
				}
				else {
					// checkboxes aren't set, so delete them
					delete_post_meta($postID, $fieldName);
				}
			}
		}

		return $postID;
	}

	/**
	* remove unwanted actions from list of feeds
	* @param array $actions
	* @return array
	*/
	public function filterPostRowActions($actions) {
		unset($actions['inline hide-if-no-js']);		// "quick edit"

		return $actions;
	}

	/**
	* change the post updated messages
	* @param array $messages
	* @return array
	*/
	public function filterPostUpdatedMessages($messages) {
		$messages[GFDPSPXPAY_TYPE_FEED] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => 'Feed updated.',
			 2 => 'Custom field updated.',
			 3 => 'Custom field deleted.',
			 4 => 'Feed updated.',
			/* translators: %s: date and time of the revision */
			 5 => isset($_GET['revision']) ? sprintf( 'Feed restored to revision from %s', wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			 6 => 'Feed published.',
			 7 => 'Feed saved.',
			 8 => 'Feed submitted.',
			 9 => 'Feed scheduled for: ',
			10 => 'Feed draft updated.',
		);

		return $messages;
	}

	/**
	* filter to add columns to post list
	* @param array $posts_columns
	* @return array
	*/
	public function filterManageColumns($posts_columns) {
		// Date isn't useful for this post type
		unset($posts_columns['date']);

		// stop File Gallery adding No. of Attachments
		unset($posts_columns['attachment_count']);

		return $posts_columns;
	}

	/**
	* get a map of GF form field IDs to field names, for populating drop-down lists
	* @param int $formID
	* @return array
	*/
	public static function getFormFields($formID) {
		$form = GFFormsModel::get_form_meta($formID);

        $fields = array(
        	'form' => $formID . ' (form ID)',
        	'title' => $form['title'] . ' (form title)',
        );

        if (is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!rgar($field, 'displayOnly')) {
					// pick up simple fields and selected compound fields
					if (empty($field['inputs']) || in_array(GFFormsModel::get_input_type($field), array('name', 'address'))) {
						$fields[(string) $field['id']] = GFCommon::get_label($field);
					}

					// pick up subfields
					if (isset($field['inputs']) && is_array($field['inputs'])) {
						foreach($field['inputs'] as $input) {
							$fields[(string) $input['id']] = GFCommon::get_label($field, $input['id']);
						}
					}
				}
            }
        }

        return $fields;
	}

	/**
	* return a list of drop-down list items for field mappings
	* @param string $current the currently selected option
	* @param array $fields
	* @return string
	*/
	public static function selectFields($current, $fields) {
		$opts = "<option value=''>-- not selected --</option>\n";

		foreach ($fields as $name => $title) {
			$selected = selected($current, $name);
			$title = htmlspecialchars($title);
			$opts .= "<option value='$name' $selected>$title</option>\n";
		}

		return $opts;
	}
}
