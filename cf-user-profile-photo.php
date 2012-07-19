<?php
/*
Plugin Name: CF User Profile Photo
Plugin URI:
Description: Allows users to upload their own photo for their profile.  Photo is managed in the profile edit screen.
Version: 0.7
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

class cf_User_Profile_Photo {
	protected $image_sizes = array();

	function __construct() {
		// Assign our nonce prefix, so we don't have conflicts with other cf_action plugins
		$this->prefix = 'cfupp';
		$this->meta_name = 'cf_user_profile_photo';
		$this->text_domain = 'cfupp';
		$this->action = $this->prefix.'cf_action';
		$this->ver = '0.7';
	}

	/**
	 * Handler for adding our various actions, etc.
	 *
	 * @return void
	 */
	function add_actions() {
		if (is_admin()) {
			add_action('init', array($this, 'early_request_handler'), 1);
			add_action('init', array($this, 'request_handler'));

			add_action('show_user_profile', array($this, 'photo_form'));
			add_action('edit_user_profile', array($this, 'photo_form'));

			add_action('user_edit_form_tag', array($this, 'output_profile_form_enctype'));

			// Only include our resources when on the right page
			add_action('admin_head-profile.php', array($this, 'get_resources'));
			add_action('admin_head-user-edit.php', array($this, 'get_resources'));
		}
	}

	/**
	 * Handles Photo uploads
	 *
	 * @return void
	 **/
	function request_handler() {
		if (!empty($_POST[$this->action])) {
			switch ($_POST[$this->action]) {
				case $this->prefix.'photo_upload':
					// Include our functions to handle the FILE upload
					require_once(ABSPATH . 'wp-admin/includes/media.php');
					require_once(ABSPATH . 'wp-admin/includes/file.php');
					require_once(ABSPATH . 'wp-admin/includes/image.php');

					if (
						current_user_can('upload_files') // Includes everyone down through Authors
						&& check_admin_referer($this->prefix.'uploading_user_photo', $this->prefix.'user_photo_nonce') // verify our nonce
						&& !empty($_FILES) // We have some uploads
						&& !empty($_FILES[$this->prefix.'photo_upload']) // We have *our* upload field
						&& !empty($_FILES[$this->prefix.'photo_upload']['size']) // We have something in that field
						&& !empty($_POST['user_id']) // We have a user ID to attach meta to
						) {

						global $wpdb;

						// Let WP handle the file upload
						$r = media_handle_upload($this->prefix.'photo_upload', 0);
						if (!$this->is_valid_media_result($r)) {
							echo 'invalid!';
							// Do some admin notice here
							return;
						}

						// Add the media image file ID to user meta
						$user_id = intval($_POST['user_id']);
						update_user_meta(
							$user_id,
							$this->meta_name.'_'.$wpdb->blogid,
							$r
						);
					}
					break;
				case 'delete_profile_photo':
					if (
						current_user_can('upload_files') // Includes everyone down through Authors
						&& check_admin_referer($this->prefix.'uploading_user_photo', $this->prefix.'user_photo_nonce') // verify our nonce
						&& !empty($_POST['user_id']) // We have a user ID to attach meta to
						) {
						// Default to failure...hmmmm
						$r = false;

						// Get our user ID
						$user_id = intval($_POST['user_id']);

						// Get the ID of the photo
						global $wpdb;
						$photo_id = get_user_meta(
							$user_id,
							$this->meta_name.'_'.$wpdb->blogid,
							true
						);

						// really delete the photo, not just trash it
						$del = wp_delete_post($photo_id, true);
						if ($del !== false) {
							// detach the photo ID from the user
							$r = delete_user_meta(
								$user_id,
								$this->meta_name.'_'.$wpdb->blogid
							);
						}
						echo $r;
						exit;
					}
					break;
			}
		}
	}

	/**
	 * Validate our upload result:
	 * Must not be empty (might be a zero or false on failure)
	 * Must not be a WP_Error
	 *
	 * @param array $r
	 * @return bool
	 */
	function is_valid_media_result($r) {
		return !empty($r) && !is_wp_error($r);
	}

	/**
	 * Handles early requests for mostly-static items like JS and CSS
	 *
	 * @return void
	 **/
	function early_request_handler() {
		if (!empty($_GET[$this->action])) {
			switch ($_GET[$this->action]) {
				case 'js':
					$this->js();
					exit;
			}
		}
	}

	/**
	 * If we have CSS/JS to include, here's the place
	 *
	 * @return void
	 */
	function get_resources() {
		// Write JS to change the form submit type if there aren't any filters.
		wp_enqueue_script('jquery');
		wp_enqueue_script($this->prefix.'profile-photo', admin_url('?'.$this->action.'=js'), array('jquery'), $this->ver);
		global $wp_scripts;
		$wp_scripts->localize($this->prefix.'profile-photo', 'cfuppObj', array(
			'prefix' => $this->prefix,
			'deleteEndpoint' => admin_url(),
			'deleteLinkId' => 'delete_profile_photo',
			'deleteErrorMsg' => __('An error occurred trying to remove your profile photo.', $this->text_domain),
			'cf_action' => $this->action,
		));
	}

	/**
	 * Outputs the JavaScript
	 *
	 * @return echos JS
	 */
	function js() {
		if (!headers_sent()) {
			header('Content-Type: text/javascript');
		}
		require_once('script.js');
	}

	/**
	 * Outputs the profile page form for the photo
	 *
	 * @param obj $profileuser
	 * @return void
	 */
	function photo_form($profileuser) {
		$photo_url = $this->get_user_photo_url($profileuser->ID);
		?>
		<h3><?php esc_html_e('Profile Photo', $this->text_domain); ?></h3>
		<table class="form-table">
			<?php
			if ($photo_url) {
				?>
				<tr id="current_photo_row">
					<th><?php _e('Current Photo', $this->text_domain); ?></th>
					<td><img src="<?php echo $photo_url; ?>" /><a href="#" id="<?php echo esc_attr($this->prefix.'delete_profile_photo'); ?>">Delete</a></td>
				</tr>
				<?php
			}
			?>
			<tr>
				<th><label for="<?php echo esc_attr($this->prefix.'photo_upload'); ?>"><?php _e('Upload Your Own Photo', $this->text_domain); ?></label></th>
				<td><input type="file" name="<?php echo esc_attr($this->prefix.'photo_upload'); ?>" id="<?php echo esc_attr($this->prefix.'photo_upload'); ?>" value="" />
			</tr>
		</table><!-- /form-table -->
		<input type="hidden" name="<?php echo esc_attr($this->action); ?>" value="<?php echo esc_attr($this->prefix.'photo_upload'); ?>" />
		<?php
		wp_nonce_field($this->prefix.'uploading_user_photo', $this->prefix.'user_photo_nonce');
	}

	/**
	 * Returns the URL for the profile photo
	 *
	 * @param int $user_id
	 * @return mixed: string on success, bool false on failure
	 */
	function get_user_photo_url($user_id, $size = 'thumbnail') {
		global $wpdb;
		$url = false;

		$photo_id = get_user_meta(
			$user_id,
			$this->meta_name.'_'.$wpdb->blogid,
			true
		);

		if (empty($photo_id)) {
			// look at old data store
			$photo_id = get_user_meta($user_id, $this->meta_name, true);

			if ($photo_id
				&& ($url = $this->get_photo_url($photo_id, $size)) !== false
				) {
				// Add our new data format
				update_user_meta(
					$user_id,
					$this->meta_name.'_'.$wpdb->blogid,
					$photo_id
				);
				// Delete previous format
				delete_user_meta($user_id, $this->meta_name);
			}
		}
		else {
			$url = $this->get_photo_url($photo_id, $size);
		}

		return $url;
	}


	/**
	 * Fetches the attachment URL by post (photo) ID.
	 *
	 * @param int $photo_id
	 * @param string $size
	 * @return mixed: string on success, bool false on failure
	 */
	protected function get_photo_url($photo_id, $size) {
		$url = false;
		$r = wp_get_attachment_image_src($photo_id, $size);
		if (is_array($r) && !empty($r[0])) {
			$url = $r[0];
		}
		return $url;
	}

	/**
	 * Filter the get_avatar function
	 * @param int $image_size the image size (pixels) to set custom avatars at.
	 */
	function setup_custom_avatars() {
		// Add filter to get_avatar.
		add_filter('get_avatar', array($this, 'filter_get_avatar'), 10, 5);
	}

	/**
	 * @param int $size size of avatar (square)
	 */
	function add_avatar_size($size) {
		$size = (int) $size;
		// Set image size to pull
		if (!in_array($size, $this->image_sizes)) {
			$this->image_sizes[$this->prefix . 'image_' . $size] = $size;
			add_image_size($this->prefix . 'image_' . $size, $size, $size, true);
		}
	}

	function filter_get_avatar($avatar, $id_or_email, $size, $default, $alt) {
		// Get size key
		$size_key = '';
		foreach ($this->image_sizes as $key => $value) {
			// If there is an avatar size set that matches, use it.
			if ($size == $value) {
				$size_key = $key;
				break;
			}
		}
		// If there is no size key, use the default avatar.
		if (!$size_key) {
			return $avatar;
		}

		$id = $this->get_id_by_id_or_email($id_or_email);
		$src = $this->get_user_photo_url($id, $size_key);

		if ($src) {
			$avatar = preg_replace('/src=("[^"]*"|\'[^\']*\')/', 'src="'.$src.'"', $avatar);
		}

		return $avatar;
	}

	function get_id_by_id_or_email($id_or_email) {
		$id = 0;

		// It's an ID. We're good.
		if ( is_numeric($id_or_email) ) {
			$id = (int) $id_or_email;
		} // It's a comment object: get the ID.
		elseif ( is_object($id_or_email) && property_exists($id_or_email, 'user_id') ) {
			$id = $id_or_email->user_id;
		} // It's an email: get the ID.
		else {
			$user = get_user_by_email($id_or_email);
			$id = $user->user_id;
		}
		return $id;
	}

	function output_profile_form_enctype() {
		echo ' enctype="multipart/form-data"';
	}
}
$cfupp = new cf_User_Profile_Photo;
$cfupp->add_actions();
