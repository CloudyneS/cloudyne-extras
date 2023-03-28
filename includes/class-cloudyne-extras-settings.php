<?php
use function Env\env;

/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Cloudyne_Extras_Settings {

	/**
	 * The single instance of Cloudyne_Extras_Settings.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array(
		'googlead_code' => '',
	);

	/**
	 * Constructor function.
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = 'cldy_';

		// Initialise settings.
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add settings page to menu.
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->parent->file ),
			array(
				$this,
				'add_settings_link',
			)
		);

		// Configure placement of plugin settings page. See readme for implementation.
		add_filter( $this->base . 'menu_settings', array( $this, 'configure_settings' ) );

	}

	/**
	 * Initialise settings
	 *
	 * @return void
	 */
	public function init_settings() {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_menu_item() {

		$args = $this->menu_settings();

		// Do nothing if wrong location key is set.
		if ( is_array( $args ) && isset( $args['location'] ) && function_exists( 'add_' . $args['location'] . '_page' ) ) {
			switch ( $args['location'] ) {
				case 'options':
				case 'submenu':
					$page = add_submenu_page( $args['parent_slug'], $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );
					break;
				case 'menu':
					$page = add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'], $args['icon_url'], $args['position'] );
					break;
				default:
					return;
			}
			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
		}
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings() {
		return apply_filters(
			$this->base . 'menu_settings',
			array(
				'location'    => 'menu',
				'page_title'  => __( 'Cloudyne Settings', 'cloudyne-extras' ),
				'menu_title'  => __( 'CLDY Settings', 'cloudyne-extras' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent->_token . '_settings',
				'function'    => array( $this, 'settings_page' ),
				'icon_url'    => '',
				'position'    => null,
			)
		);
	}

	/**
	 * Container for settings page arguments
	 *
	 * @param array $settings Settings array.
	 *
	 * @return array
	 */
	public function configure_settings( $settings = array() ) {
		return $settings;
	}

	/**
	 * Load settings JS & CSS
	 *
	 * @return void
	 */
	public function settings_assets() {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
		wp_enqueue_style( 'farbtastic' );
		wp_enqueue_script( 'farbtastic' );

		// We're including the WP media scripts here because they're needed for the image upload field.
		// If you're not including an image upload then you can leave this function call out.
		wp_enqueue_media();

		wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0', true );
		wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 *
	 * @param  array $links Existing links.
	 * @return array        Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'cloudyne-extras' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {
		$allowData = "";
		$emailmsg = "Email Sender";
		$color = "";
		
		if ((env('SMTP_ALLOWONLY_DOMAINS') || env('SMTP_ALLOWONLY_EMAILS')) && !env('SMTP_FORCE_FROM')) {
			if (isset($_POST['cldy_email_from']) && $_POST['cldy_email_from'] !== 'noreply@customer.v3.nu') {
				$email = $_POST['cldy_email_from'];
				$domain = explode('@', $email)[1];
				$allowedDomains = explode(",", env('SMTP_ALLOWONLY_DOMAINS'));
				$allowedSenders = explode(",", env('SMTP_ALLOWONLY_EMAILS'));

				if (!in_array($domain, $allowedDomains) && !in_array($email, $allowedSenders)) {
					$_POST['cldy_email_from'] = 'invalid-domain-message@no-domain.com';
				}
			}
			
			if (get_option('cldy_email_from') === 'invalid-domain-message@no-domain.com') {
				echo '<div class="error" style="color: red; font-weight: bold;">You cannot send emails from this domain. Please use one of the allowed domains or email addresses.</div>';
				$emailmsg = "<style='color: red;'>Email Sender</style>";
				$color = 'style="color: red;"';
			}

			$allowData = "<br/><br/><h3 $color>Allowed sender domains/emails</h3><p>You can only configure emails to be sent from one of these domains or email addresses<ul>";
			$allowedDomains = explode(",", env('SMTP_ALLOWONLY_DOMAINS'));
			foreach ($allowedDomains as $domain) {
				$allowData .= "<li $color>".$domain . "</li>";
			}
			$allowedSenders = explode(",", env('SMTP_ALLOWONLY_EMAILS'));
			foreach (array_merge(['noreply@customer.v3.nu'], $allowedSenders) as $sender) {
				$allowData .= "<li $color>".$sender . "</li>";
			}
			$allowData .= "</ul></p>";
		} elseif (env('SMTP_FORCE_FROM')) {
			$allowData = "<br/><br/><h3>Allowed sender domains/emails</h3><p>You cannot change the email sender since none of your domains are setup in our Email system.";
			if (!env('SMTP_FORCE_FROM_NAME')) {
				$allowData .= " You can still change the sender name though.";
			}
			$allowData .= "</ul></p>";
		}

		$settings['emailsettings'] = array(
			'title' => __( 'Email Settings', 'cloudyne-extras'),
			'description' => __( 'These settings determine from which domain the emails from this site will be sent.' . $allowData, 'cloudyne-extras'),
			'fields' => array(
				array(
					'id' => 'disable_email_plugin',
					'label' => __( 'Disable Emails', 'cloudyne-extras'),
					'description' => __( 'Check this box to disable sending emails through our system. This means no emails can be sent unless you configure a plugin on your own.', 'cloudyne-extras'),
					'type' => 'checkbox',
					'default' => false,
				),
				array(
					'id' => 'email_from',
					'label' => $emailmsg,
					'description' => __( 'Enter the email address you want to send from', 'cloudyne-extras'),
					'type' => 'text',
					'default' => env('SMTP_FORCE_FROM') ?? env('SMTP_FROM') ?? '',
					'disabled' => env('SMTP_FORCE_FROM') ? true : false,
					'placeholder' => __( 'Email address', 'cloudyne-extras'),
				),
				array(
					'id' => 'email_from_name',
					'label' => __( 'Email From Name', 'cloudyne-extras'),
					'description' => __( 'Enter the name you want to send from', 'cloudyne-extras'),
					'type' => 'text',
					'default' => env('SMTP_FORCE_FROM_NAME') ?? env('SMTP_FROM_NAME') ?? '',
					'disabled' => env('SMTP_FORCE_FROM_NAME') ? true : false,
					'placeholder' => __( 'Sender Name', 'cloudyne-extras'),
				)
			)
		);

		$settings['googlead'] = array(
			'title'	   => __( 'Header Scripts', 'cloudyne-extras' ),
			'description' => __( 'Add additional Javascript or stylesheet data to the header', 'cloudyne-extras' ),
			'fields' => array(
				array(
					'id' => 'googlead_code',
					'label' => __( 'Additional javascript/stylesheets', 'cloudyne-extras' ),
					'description' => __( 'Insert additional code into the <head></head> tag of the site, normally google tag manager scripts, stylesheets or fonts', 'cloudyne-extras' ),
					'type' => 'textarea',
					'default' => '',
					'cols' => '120',
					'rows' => '15',
					'placeholder' => __( '<!-- Google tag (gtag.js) -->'.PHP_EOL.'.........', 'cloudyne-extras' ),
				)
			)
		);



		// $settings['standard'] = array(
		// 	'title'       => __( 'Standard', 'cloudyne-extras' ),
		// 	'description' => __( 'These are fairly standard form input fields.', 'cloudyne-extras' ),
		// 	'fields'      => array(
		// 		array(
		// 			'id'          => 'text_field',
		// 			'label'       => __( 'Some Text', 'cloudyne-extras' ),
		// 			'description' => __( 'This is a standard text field.', 'cloudyne-extras' ),
		// 			'type'        => 'text',
		// 			'default'     => '',
		// 			'placeholder' => __( 'Placeholder text', 'cloudyne-extras' ),
		// 		),
		// 		array(
		// 			'id'          => 'password_field',
		// 			'label'       => __( 'A Password', 'cloudyne-extras' ),
		// 			'description' => __( 'This is a standard password field.', 'cloudyne-extras' ),
		// 			'type'        => 'password',
		// 			'default'     => '',
		// 			'placeholder' => __( 'Placeholder text', 'cloudyne-extras' ),
		// 		),
		// 		array(
		// 			'id'          => 'secret_text_field',
		// 			'label'       => __( 'Some Secret Text', 'cloudyne-extras' ),
		// 			'description' => __( 'This is a secret text field - any data saved here will not be displayed after the page has reloaded, but it will be saved.', 'cloudyne-extras' ),
		// 			'type'        => 'text_secret',
		// 			'default'     => '',
		// 			'placeholder' => __( 'Placeholder text', 'cloudyne-extras' ),
		// 		),
		// 		array(
		// 			'id'          => 'text_block',
		// 			'label'       => __( 'A Text Block', 'cloudyne-extras' ),
		// 			'description' => __( 'This is a standard text area.', 'cloudyne-extras' ),
		// 			'type'        => 'textarea',
		// 			'default'     => '',
		// 			'placeholder' => __( 'Placeholder text for this textarea', 'cloudyne-extras' ),
		// 		),
		// 		array(
		// 			'id'          => 'single_checkbox',
		// 			'label'       => __( 'An Option', 'cloudyne-extras' ),
		// 			'description' => __( 'A standard checkbox - if you save this option as checked then it will store the option as \'on\', otherwise it will be an empty string.', 'cloudyne-extras' ),
		// 			'type'        => 'checkbox',
		// 			'default'     => '',
		// 		),
		// 		array(
		// 			'id'          => 'select_box',
		// 			'label'       => __( 'A Select Box', 'cloudyne-extras' ),
		// 			'description' => __( 'A standard select box.', 'cloudyne-extras' ),
		// 			'type'        => 'select',
		// 			'options'     => array(
		// 				'drupal'    => 'Drupal',
		// 				'joomla'    => 'Joomla',
		// 				'wordpress' => 'WordPress',
		// 			),
		// 			'default'     => 'wordpress',
		// 		),
		// 		array(
		// 			'id'          => 'radio_buttons',
		// 			'label'       => __( 'Some Options', 'cloudyne-extras' ),
		// 			'description' => __( 'A standard set of radio buttons.', 'cloudyne-extras' ),
		// 			'type'        => 'radio',
		// 			'options'     => array(
		// 				'superman' => 'Superman',
		// 				'batman'   => 'Batman',
		// 				'ironman'  => 'Iron Man',
		// 			),
		// 			'default'     => 'batman',
		// 		),
		// 		array(
		// 			'id'          => 'multiple_checkboxes',
		// 			'label'       => __( 'Some Items', 'cloudyne-extras' ),
		// 			'description' => __( 'You can select multiple items and they will be stored as an array.', 'cloudyne-extras' ),
		// 			'type'        => 'checkbox_multi',
		// 			'options'     => array(
		// 				'square'    => 'Square',
		// 				'circle'    => 'Circle',
		// 				'rectangle' => 'Rectangle',
		// 				'triangle'  => 'Triangle',
		// 			),
		// 			'default'     => array( 'circle', 'triangle' ),
		// 		),
		// 	),
		// );

		// $settings['extra'] = array(
		// 	'title'       => __( 'Extra', 'cloudyne-extras' ),
		// 	'description' => __( 'These are some extra input fields that maybe aren\'t as common as the others.', 'cloudyne-extras' ),
		// 	'fields'      => array(
		// 		array(
		// 			'id'          => 'number_field',
		// 			'label'       => __( 'A Number', 'cloudyne-extras' ),
		// 			'description' => __( 'This is a standard number field - if this field contains anything other than numbers then the form will not be submitted.', 'cloudyne-extras' ),
		// 			'type'        => 'number',
		// 			'default'     => '',
		// 			'placeholder' => __( '42', 'cloudyne-extras' ),
		// 		),
		// 		array(
		// 			'id'          => 'colour_picker',
		// 			'label'       => __( 'Pick a colour', 'cloudyne-extras' ),
		// 			'description' => __( 'This uses WordPress\' built-in colour picker - the option is stored as the colour\'s hex code.', 'cloudyne-extras' ),
		// 			'type'        => 'color',
		// 			'default'     => '#21759B',
		// 		),
		// 		array(
		// 			'id'          => 'an_image',
		// 			'label'       => __( 'An Image', 'cloudyne-extras' ),
		// 			'description' => __( 'This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an imge the thumbnail will display above these buttons.', 'cloudyne-extras' ),
		// 			'type'        => 'image',
		// 			'default'     => '',
		// 			'placeholder' => '',
		// 		),
		// 		array(
		// 			'id'          => 'multi_select_box',
		// 			'label'       => __( 'A Multi-Select Box', 'cloudyne-extras' ),
		// 			'description' => __( 'A standard multi-select box - the saved data is stored as an array.', 'cloudyne-extras' ),
		// 			'type'        => 'select_multi',
		// 			'options'     => array(
		// 				'linux'   => 'Linux',
		// 				'mac'     => 'Mac',
		// 				'windows' => 'Windows',
		// 			),
		// 			'default'     => array( 'linux' ),
		// 		),
		// 	),
		// );

		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	public function set_flashmessage($key, $value) {
		$_SESSION[$key] = $value;
	}

	public function get_flashmessage($key, $default = False) {
		if (isset($_SESSION[$key])) {
			$value = $_SESSION[$key];
			unset($_SESSION[$key]);
			return $value;
		}
		return $default;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings() {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab.
			//phpcs:disable
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}
			//phpcs:enable
			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section !== $section ) {
					continue;
				}

				// Add section to page.
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field.
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field.
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page.
					add_settings_field(
						$field['id'],
						$field['label'],
						array( $this->parent->admin, 'display_field' ),
						$this->parent->_token . '_settings',
						$section,
						array(
							'field'  => $field,
							'prefix' => $this->base,
						)
					);
				}

				if ( ! $current_section ) {
					break;
				}
			}
		}
	}

	/**
	 * Settings section.
	 *
	 * @param array $section Array of section ids.
	 * @return void
	 */
	public function settings_section( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html; //phpcs:ignore
	}

	/**
	 * Load settings page content.
	 *
	 * @return void
	 */
	public function settings_page() {

		// Build page HTML.
		$html      = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
			$html .= '<h2>' . __( 'Plugin Settings', 'cloudyne-extras' ) . '</h2>' . "\n";

			$tab = '';
		//phpcs:disable
		if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
			$tab .= $_GET['tab'];
		}
		//phpcs:enable

		// Show page tabs.
		if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

			$html .= '<h2 class="nav-tab-wrapper">' . "\n";

			$c = 0;
			foreach ( $this->settings as $section => $data ) {

				// Set tab class.
				$class = 'nav-tab';
				if ( ! isset( $_GET['tab'] ) ) { //phpcs:ignore
					if ( 0 === $c ) {
						$class .= ' nav-tab-active';
					}
				} else {
					if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) { //phpcs:ignore
						$class .= ' nav-tab-active';
					}
				}

				// Set tab link.
				$tab_link = add_query_arg( array( 'tab' => $section ) );
				if ( isset( $_GET['settings-updated'] ) ) { //phpcs:ignore
					$tab_link = remove_query_arg( 'settings-updated', $tab_link );
				}

				// Output tab.
				$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

				++$c;
			}

			$html .= '</h2>' . "\n";
		}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields.
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html     .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'cloudyne-extras' ) ) . '" />' . "\n";
				$html     .= '</p>' . "\n";
			$html         .= '</form>' . "\n";
		$html             .= '</div>' . "\n";

		echo $html; //phpcs:ignore
	}

	/**
	 * Main Cloudyne_Extras_Settings Instance
	 *
	 * Ensures only one instance of Cloudyne_Extras_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Cloudyne_Extras()
	 * @param object $parent Object instance.
	 * @return object Cloudyne_Extras_Settings instance
	 */
	public static function instance( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of Cloudyne_Extras_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of Cloudyne_Extras_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()

}
