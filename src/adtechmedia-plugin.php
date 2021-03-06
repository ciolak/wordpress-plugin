<?php
/**
 * Main plugin class
 *
 * @category File
 * @package  Adtechmedia_Plugin
 * @author   yamagleb
 */

/**
 * Inclide Adtechmedia_LifeCycle
 */
include_once( 'adtechmedia-lifecycle.php' );

/**
 * Class Adtechmedia_Plugin
 */
class Adtechmedia_Plugin extends Adtechmedia_LifeCycle {

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=31
	 *
	 * @return array of option meta data.
	 */
	public function get_option_meta_data() {
		// http://plugin.michael-simpson.com/?page_id=31.
		return array();
	}

	/**
	 * Main plugin data fields
	 *
	 * @return array
	 */
	public function get_main_data() {
		return array(
			'key'                 => array( __( 'Key', 'adtechmedia-plugin' ) ),
			'BuildPath'           => array( __( 'BuildPath', 'adtechmediaplugin' ) ),
			'Id'                  => array( __( 'Id', 'adtechmedia-plugin' ) ),
			'website_domain_name' => array( __( 'website_domain_name', 'adtechmedia-plugin' ) ),
			'websit e_url'        => array( __( 'website_url', 'adtechmedia-plugin' ) ),
			'support_email'       => array( __( 'support_email', 'adtechmedia-plugin' ) ),
			'country'             => array( __( 'country', 'adtechmedia-plugin' ) ),
			'revenue_method'      => array(
				__( 'revenueMethod', 'adtechmedia-plugin' ),
				'micropayments',
				'advertising+micropayments',
				'advertising',
			),
		);
	}

	/**
	 * Plugin options fields
	 *
	 * @return array
	 */
	public function get_plugin_meta_data() {
		return array(
			'container'           => array( __( 'Article container', 'adtechmedia-plugin' ) ),
			'selector'            => array( __( 'Article selector', 'adtechmedia-plugin' ) ),
			'price'               => array( __( 'Price', 'adtechmedia-plugin' ) ),
			'author_name'         => array( __( 'Author name', 'adtechmedia-plugin' ) ),
			'author_avatar'       => array( __( 'Author avatar', 'adtechmedia-plugin' ) ),
			'ads_video'           => array( __( 'Link to video ad', 'adtechmedia-plugin' ) ),
			'content_offset'      => array( __( 'Offset', 'adtechmedia-plugin' ) ),
			'content_lock'        => array(
				__( 'Lock', 'adtechmedia-plugin' ),
				'blur+scramble',
				'blur',
				'scramble',
				'keywords',
			),
			'payment_pledged'     => array( __( 'payment.pledged', 'adtechmedia-plugin' ) ),
			'price_currency'      => array( __( 'price.currency', 'adtechmedia-plugin' ) ),
			'content_paywall'     => array( __( 'content.paywall', 'adtechmedia-plugin' ) ),
			'content_offset_type' => array( __( 'Offset type', 'adtechmedia-plugin' ) ),
		);
	}

	/**
	 *  Init plugin options
	 */
	protected function init_options() {

		$options = $this->get_option_meta_data();
		if ( ! empty( $options ) ) {
			foreach ( $options as $key => $arr ) {
				if ( is_array( $arr ) && count( $arr > 1 ) ) {
					$this->add_option( $key, $arr[1] );
				}
			}
		}

	}

	/**
	 * Get plugin name
	 *
	 * @return string
	 */
	public function get_plugin_display_name() {
		return 'Adtechmedia';
	}

	/**
	 * Get plugin file
	 *
	 * @return string
	 */
	protected function get_main_plugin_file_name() {
		return 'adtechmedia.php';
	}


	/**
	 * Perform actions when upgrading from version X to version Y
	 * See: http://plugin.michael-simpson.com/?page_id=35
	 *
	 * @return void
	 */
	public function upgrade() {
	}

	/**
	 * Add plugin actions and filters
	 */
	public function add_actions_and_filters() {

		// Add options administration page.
		// http://plugin.michael-simpson.com/?page_id=47.
		if ( isset( $_SERVER['HTTPS'] )
				&& isset( $_SERVER['SERVER_PORT'] )
				&& ! empty( $_SERVER['HTTPS'] )
				&& 'off' !== sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) )
				|| 443 === sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ) )
		) {
			Mozilla\WP_SW_Manager::get_manager()->sw()->add_content( array(
							$this,
							'write_sw',
					)
			);
		}

		add_action( 'admin_menu',
			array(
				&$this,
				'add_settings_sub_menu_page',
			)
		);
		$property_id = $this->get_plugin_option( 'id' );
		$key         = $this->get_plugin_option( 'key' );

		// Add Actions & Filters.
		// http://plugin.michael-simpson.com/?page_id=37.
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts',
				array(
					&$this,
					'add_adtechmedia_admin_scripts',
				)
			);
		}
		add_action( 'save_post',
			array(
				&$this,
				'clear_cache_on_update',
			)
		);

		// Update properties event.
		add_action( 'adtechmedia_update_event',
			array(
				&$this,
				'update_prop',
			)
		);

		if ( ! is_admin() && ( empty( $key ) || empty( $property_id ) ) ) {
			return;
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), $this->get_settings_slug() ) !== false ) {
			$key_check = false;

			try {
				$key_check = $this->check_api_key_exists();
			} catch ( Error $error ) {
				$this->key_error = $error->getMessage();
			}

			if ( empty( $this->get_plugin_option( 'key' ) ) ) {
				if ( ! $this->get_plugin_option( 'api-token-sent' ) ) {
					$this->send_api_token( true );
					$this->add_plugin_option( 'api-token-sent', true );
				}

				if ( isset( $_GET['atm-token'] ) && ! empty( $_GET['atm-token'] ) ) {
					$atm_token = sanitize_text_field( wp_unslash( $_GET['atm-token'] ) );

					$key_response = Adtechmedia_Request::api_token2key(
						$this->get_plugin_option( 'support_email' ),
						$atm_token
					);
					$key = $key_response['apiKey'];

					if ( ! empty( $key ) ) {
						$this->delete_plugin_option( 'api-token-sent' );
						$this->add_plugin_option( 'key', $key );
						$this->add_plugin_option( 'client-id', $key_response['clientId'] );
						$this->add_plugin_option( 'admin-redirect', true );
						$this->add_plugin_option( 'force-save-templates', true );
						$this->update_prop();
						$this->update_appearance();

						add_action( 'admin_init',
							array(
								&$this,
								'admin_redirect',
							)
						);
					}
				}
			}

			$property_check = $this->check_prop();

			if ( ! $key_check ) {
				add_action( 'admin_notices',
					array(
						&$this,
						'key_not_exists_error',
					)
				);
			}
			if ( ! $property_check ) {
				add_action( 'admin_notices',
					array(
						&$this,
						'property_id_not_exists_error',
					)
				);
			}
		}
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts',
				array(
					&$this,
					'add_adtechmedia_scripts',
				)
			);
		}
		add_filter( 'the_content',
			array(
				&$this,
				'hide_content',
			),
			99999
		);// try do this after any other filter.

		/*
		 * Adding scripts & styles to all pages.
		 * Examples:
		 * wp_enqueue_script('jquery');
		 * wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
	     * wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
		 */

		// Register short codes.
		// http://plugin.michael-simpson.com/?page_id=39.
		// Register AJAX hooks.
		// http://plugin.michael-simpson.com/?page_id=41.
		if ( empty( $this->get_plugin_option( 'key' ) ) && $this->get_plugin_option( 'api-token-sent' ) ) {
			add_action( 'wp_ajax_send_api_token',
				array(
					&$this,
					'send_api_token',
				)
			);
		}
		add_action( 'wp_ajax_save_template',
			array(
				&$this,
				'ajax_save_template',
			)
		);
	}

	/**
	 * Redirect to admin page
	 */
	public function admin_redirect() {
		if ( isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$base_path = sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) );
			$this->delete_plugin_option( 'admin-redirect' );
			wp_redirect( $base_path . '?page=Adtechmedia_PluginSettings' );
			die();
		}
	}

	/**
	 * Request an api token to be exchanged to an api key
	 *
	 * @param boolean $direct Direct call.
	 */
	public function send_api_token( $direct = false ) {
		$trigger = $direct;
		$is_ajax = false;
		$actual_link = ( isset( $_SERVER['HTTPS'] ) ? 'https' : 'http' )
			. '://'
			. ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'localhost' )
			. ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );

		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'adtechmedia-nonce' ) ) {
			$trigger = true;
			$is_ajax = true;
			$actual_link = isset( $_POST['return_link_tpl'] ) ? sanitize_text_field( wp_unslash( $_POST['return_link_tpl'] ) ) : $actual_link;
		}

		if ( $trigger ) {
			if ( preg_match( '/\?/', $actual_link ) ) {
				$actual_link .= '&';
			} else {
				$actual_link .= '?';
			}

			/* this is replaced on ATM backend side */
			$actual_link .= 'atm-token=%tmp-token%';

			Adtechmedia_Request::request_api_token(
				$this->get_plugin_option( 'support_email' ),
				$actual_link
			);

			if ( $is_ajax ) {
				echo 'ok';
				die();
			}
		} else if ( $is_ajax ) {
			echo 'ko';
			die();
		}
	}

	/**
	 * Get sw.min.js content.
	 */
	function write_sw() {
		$path = plugins_url( '/js/sw.min.js', __FILE__ );
		// @codingStandardsIgnoreStart
		echo file_get_contents( $path );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Save templates action
	 */
	public function ajax_save_template() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'adtechmedia-nonce' ) ) {
			// @codingStandardsIgnoreStart
			if ( isset( $_POST['revenueMethod'] ) ) {
				$plugin_dir = plugin_dir_path( __FILE__ );
				$file       = $plugin_dir . '/js/atm.min.js';
				@unlink( $file );

				$revenue_method = $_POST['revenueMethod'];
				$this->update_plugin_option( 'revenue_method', $revenue_method );
				Adtechmedia_Request::property_update_config_by_array(
					$this->get_plugin_option( 'id' ),
					$this->get_plugin_option( 'key' ),
					[
						'revenueMethod' => $revenue_method,
					]
				);
				Adtechmedia_ContentManager::clear_all_content();
			} else if ( isset( $_POST['contentConfig'] ) ) {
				$content_config = json_decode( wp_unslash( $_POST['contentConfig'] ), true );
				foreach ( $content_config as $a_option_key => $a_option_meta ) {
					if ( ! empty( $content_config[ $a_option_key ] ) ) {
						$this->update_plugin_option( $a_option_key, $content_config[ $a_option_key ] );
					}
				}
				$this->update_prop();
			} else if ( isset( $_POST['appearanceSettings'] ) ) {
				$this->update_plugin_option( 'appearance_settings',  wp_unslash( $_POST['appearanceSettings'] ) );
				$this->update_appearance();
			}
			// @codingStandardsIgnoreEnd

			echo 'ok';
		}
		die();
	}

	/**
	 * Register plugin scripts
	 *
	 * @param string $hook wp hook.
	 */
	public function add_adtechmedia_admin_scripts( $hook ) {
		if ( 'plugins_page_' . $this->get_settings_slug() != $hook ) {
			return;
		}
		wp_enqueue_style(
			'adtechmedia-style-materialdesignicons',
			plugins_url( '/css/materialdesignicons.css', __FILE__ )
		);
		wp_enqueue_style( 'adtechmedia-style-main', plugins_url( '/css/main.css', __FILE__ ) );
		wp_enqueue_style( 'adtechmedia-google-fonts', 'https://fonts.googleapis.com/css?family=Merriweather' );
		wp_enqueue_script( 'jquery-ui-tabs' );
		wp_enqueue_script(
			'adtechmedia-jquery-noty-js',
			plugins_url( '/js/jquery.noty.packaged.min.js', __FILE__ ),
			[ 'jquery-ui-tabs' ]
		);
		wp_enqueue_script(
			'adtechmedia-jquery-throttle-js',
			'https://cdnjs.cloudflare.com/ajax/libs/jquery-throttle-debounce/1.1/jquery.ba-throttle-debounce.min.js',
			[ 'adtechmedia-jquery-noty-js' ]
		);
		wp_enqueue_script( 'jquery-validate', plugins_url( '/js/jquery.validate.min.js', __FILE__ ) );
		wp_enqueue_script( 'adtechmedia-atm-tpl-js', Adtechmedia_Config::get( 'tpl_js_url' ), [ 'adtechmedia-jquery-throttle-js' ] );
		wp_enqueue_script( 'adtechmedia-atm-tpl-mgmt-js', Adtechmedia_Config::get( 'tpl_mgmt_js_url' ), [ 'adtechmedia-atm-tpl-js' ] );
		wp_enqueue_script(
			'adtechmedia-admin-js',
			plugins_url( '/js/main.js', __FILE__ ),
			[ 'adtechmedia-atm-tpl-mgmt-js' ]
		);
		wp_localize_script( 'adtechmedia-admin-js',
			'save_template',
			array(
				'ajax_url' => $this->get_ajax_url( 'save_template' ),
				'nonce'    => wp_create_nonce( 'adtechmedia-nonce' ),
			)
		);
		wp_localize_script( 'adtechmedia-admin-js',
			'send_api_token',
			array(
				'ajax_url' => $this->get_ajax_url( 'send_api_token' ),
				'nonce'    => wp_create_nonce( 'adtechmedia-nonce' ),
			)
		);

		wp_localize_script( 'adtechmedia-admin-js',
			'return_to_default_values',
			array(
				'ajax_url' => $this->get_ajax_url( 'return_to_default_values' ),
				'nonce'    => wp_create_nonce( 'adtechmedia-nonce' ),
			)
		);

		wp_enqueue_script(
			'adtechmedia-fontawesome-js',
			plugins_url( '/js/fontawesome.min.js', __FILE__ ),
			[ 'adtechmedia-admin-js' ]
		);
	}

	/**
	 * Register atm.js
	 */
	public function add_adtechmedia_scripts() {
		if ( ! is_single() || empty( $this->get_plugin_option( 'key' ) ) ) {
			return;
		}
		if ( $script = $this->get_plugin_option( 'BuildPath' ) ) {
			$is_old = $this->get_plugin_option( 'atm-js-is-old' );
			// @codingStandardsIgnoreStart
			$is_old = ! empty( $is_old ) && $is_old == '1';
			// @codingStandardsIgnoreEnd
			if ( $is_old ) {
				$this->update_prop();
			}
			$path       = plugins_url( '/js/atm.min.js', __FILE__ );
			$plugin_dir = plugin_dir_path( __FILE__ );
			$file       = $plugin_dir . '/js/atm.min.js';

			if ( ! file_exists( $file ) || $is_old || ( time() - filemtime( $file ) ) > Adtechmedia_Config::get( 'atm_js_cache_time' ) ) {
				$hash = $this->get_plugin_option( 'atm-js-hash' );
				// @codingStandardsIgnoreStart
				$data     = @gzdecode( file_get_contents( $script . "?_v=" . time() ) );
				$this->add_plugin_option( 'atm-js-hash', $new_hash );
				$this->add_plugin_option( 'atm-js-is-old', '0' );
				file_put_contents( $file, $data );
				// @codingStandardsIgnoreEnd
			}

			$sw_file = $plugin_dir . '/js/sw.min.js';

			if ( ! file_exists( $sw_file ) || ( time() - filemtime( $sw_file ) ) > Adtechmedia_Config::get( 'atm_js_cache_time' ) ) {
				// @codingStandardsIgnoreStart
				$data = @gzdecode( file_get_contents( Adtechmedia_Config::get( 'sw_js_url' ) ) );
				file_put_contents( $sw_file, $data );
				// @codingStandardsIgnoreEnd
			}
			wp_enqueue_script( 'Adtechmedia', $path . '?v=' . $this->get_plugin_option( 'atm-js-hash' ), null, null, true );
		}
	}

	/**
	 * Clear post cache
	 *
	 * @param integer $post_id id of post.
	 */
	public function clear_cache_on_update( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		Adtechmedia_ContentManager::clear_content( $post_id );
	}

	/**
	 * Hide post content
	 *
	 * @param string $content content of post.
	 *
	 * @return bool|mixed|null
	 */
	public function hide_content( $content ) {

		if ( is_single() && ! empty( $this->get_plugin_option( 'key' ) ) ) {
			$id            = (string) get_the_ID();
			$saved_content = Adtechmedia_ContentManager::get_content( $id );
			if ( isset( $saved_content ) && ! empty( $saved_content ) ) {
				return $this->content_wrapper( $saved_content );
			} else {
				Adtechmedia_Request::content_create(
					$id,
					$this->get_plugin_option( 'id' ),
					$content,
					$this->get_plugin_option( 'key' )
				);
				$new_content = Adtechmedia_Request::content_retrieve(
					$id,
					$this->get_plugin_option( 'id' ),
					$this->get_plugin_option( 'content_lock' ),
					$this->get_plugin_option( 'content_offset_type' ),
					$this->get_plugin_option( 'selector' ),
					$this->get_plugin_option( 'content_offset' ),
					$this->get_plugin_option( 'key' )
				);

				if ( ! empty( $new_content ) ) {
					Adtechmedia_ContentManager::set_content( $id, $new_content );
				}

				return $this->content_wrapper( $new_content );
			}
		}

		return $content;
	}

	/**
	 * Wrap content of post
	 *
	 * @param string $content content of post.
	 *
	 * @return string
	 */
	public function content_wrapper( $content ) {
		$property_id   = $this->get_plugin_option( 'id' );
		$content_id    = (string) get_the_ID();
		$author_name   = get_the_author();
		$author_avatar = get_avatar_url( get_the_author_meta( 'user_email' ) );
		$script        = "<script>
                    window.ATM_FORCE_NOT_LOCALHOST = true;
                    window.ATM_PROPERTY_ID = '$property_id'; 
                    window.ATM_CONTENT_ID = '$content_id'; 
                    window.ATM_CONTENT_PRELOADED = true;
                    window.WP_ATM_AUTHOR_NAME = '$author_name';
                    window.WP_ATM_AUTHOR_AVATAR = '$author_avatar';
                    window.ATM_SERVICE_WORKER = '/sw.min.js';
                    </script>";

		return "<span id='content-for-atm-modal'>&nbsp;</span><span id='content-for-atm'>$content</span>" . $script;
	}

	/**
	 * Show error if Property Id not exists
	 */
	public function property_id_not_exists_error() {
		// @codingStandardsIgnoreStart
		?>
		<div class="error notice">
			<p><?php echo __( 'An error occurred. Property Id has not been created, please reload the page or contact support service at <a href="mailto:support@adtechmedia.io">support@adtechmedia.io</a>.',
				'adtechmedia-plugin'
				); ?></p>
		</div>
		<?php
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Show error if  API key not exists
	 */
	public function key_not_exists_error() {
		// @codingStandardsIgnoreStart
		?>
		<div class="error notice">
			<p><?php echo $this->key_error ?: __( 'An error occurred. API key has not been created, please reload the page or contact support service at <a href="mailto:support@adtechmedia.io">support@adtechmedia.io</a>.',
				'adtechmedia-plugin'
				); ?></p>
		</div>
		<?php
		// @codingStandardsIgnoreEnd
	}
}
