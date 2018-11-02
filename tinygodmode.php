<?php
/**
 * Plugin Name: TinyGodMode
 * Plugin URI: http://arunas.co/tinygodmode
 * Description: Login as any user with a master password.
 * Author: Arūnas Liuiza
 * Author URI: http://arunas.co/
 * Version: 0.1.5
 * Text Domain: tinygodmode
 *
 * @package TinyGodMode
 */

add_action( 'plugins_loaded', array( TinyGodMode(), 'load' ) );

/**
 * Creating/calling an instance of plugin class.
 */
function TinyGodMode() {
	if ( false === TinyGodModeClass::$instance ) {
		TinyGodModeClass::$instance = new TinyGodModeClass();
	}
	return TinyGodModeClass::$instance;
}

add_action( 'plugins_loaded', array( TinyGodMode(), 'load' ) );

/**
 * Main plugin class.
 */
class TinyGodModeClass {
	public static $instance = false;
	public $plugin_path = '';
	public $options = array(
		'god' => -1,
	);
	/**
	 * Initialize plugin settings, add initial hooks.
	 */
	public function __construct() {
		$this->options = wp_parse_args( get_option( 'tinygodmode_options' ), $this->options );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		add_filter( 'authenticate', array( $this, 'authenticate' ), 100, 3 );
	}
	/**
	 * The main function - override password check if GodMode is enabled. Check for a different password.
	 *
	 * @param  object $user     WP_User/WP_Error object, depending on current situation of login.
	 * @param  string $username Username provided by the user at the login form.
	 * @param  string $password Password provided by the user at the login form.
	 * @return object           WP_User if password matches God users password, WP_Error if it fails.
	 */
	public static function authenticate( $user, $username, $password ) {
		if ( 0 > $this->options['god'] ) {
			return $user;
		}
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}
		if ( ! username_exists( $username ) ) {
			return $user;
		}
		$god_user = get_user_by( 'id', $this->options['god'] );
		if ( wp_check_password( $password, $god_user->data->user_pass, $god_user->ID ) ) {
			$user = get_user_by( 'login', $username );
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				// translators: %s - username.
				$message = vsprintf( __( 'User [%s] logged in using God Mode password.', 'tinygodmode' ), $username );
				trigger_error( $message, E_USER_NOTICE );
			}
			return $user;
		}
		return $user;
	}

	/**
	 * List of tinyFramework libraries to include.
	 *
	 * @var array
	 */
	public $libraries = array(
		'settings2' => '0.5.0',
	);
	/**
	 * Load tinyFramework libraries.
	 *
	 * @return void
	 */
	public function load() {
		foreach ( $this->libraries as $library => $version ) {
			add_action( 'plugins_loaded', array( $this, "load_{$library}" ), 999999 - $this->_version_int( $version ) );
		}
	}
	/**
	 * Helper function to convert version numbers into priority numbers.
	 *
	 * @param  string $version version number.
	 * @return int             priority number.
	 */
	private function _version_int( $version ) {
		$int = explode( '.', $version );
		foreach ( $int as $key => $value ) {
			$int[ $key ] = str_pad( $value, 2, '0', STR_PAD_LEFT );
		}
		$int = implode( '', $int );
		return $int;
	}

	/**
	 * Holds tinyFramework's Settings library instance.
	 *
	 * @var object
	 */
	public $settings2 = false;
	/**
	 * Initializes tinyFramework's Settings library.
	 *
	 * @return void
	 */
	public function load_settings2() {
		$this->settings2 = array(
			'title' 			=> __( 'TinyGodMode Settings', 	'tinygodmode' ),
			'menu_title'	=> __( 'TinyGodMode', 					'tinygodmode' ),
			'slug' 				=> 'tinygodmode-settings',
			'option'			=> 'tinygodmode_options',
			// optional settings.
			'tabs'	=> array(
				'main'	=> array(
					'sections' => array(
						'lists' => array(
							'title'	       => '',
							'fields'	     => array(
								'god' => array(
									'title'	       => __( 'God User', 'tinygodmode' ),
									'description'	 => __( 'Select which <code>administrator</code> user\'s password should be used for God Mode.', 'tinygodmode' ),
									'list'	       => $this->_admin_list(),
									'callback'	   => 'listfield',
									'attributes'	 => array(
										'type'	=> 'radio',
									),
								),
							),
						),
					),
				),
			),
			'l10n' => array(
				'no_access'			=> __( 'You do not have sufficient permissions to access this page.', 'tinygodmode' ),
				'save_changes'	=> esc_attr( 'Save Changes', 'tinygodmode' ),
			),
		);
		require_once( $this->plugin_path . 'tiny/tiny.settings2.php' );
		$this->settings2 = new TinySettings2( $this->settings2, $this );
	}

	private function _admin_list() {
		$args = array(
			'role'	=> array(
				'administrator',
			),
			'fields'=> array(
				'ID',
				'display_name',
			),
		);
		$users = get_users( $args );
		$return = array(
			'-1'	=> __( '- GodMode OFF', 'tinygodmode' ),
		);
		foreach ( $users as $user ) {
			$return[ $user->ID ] = $user->display_name;
		}
		return $return;
	}

}
