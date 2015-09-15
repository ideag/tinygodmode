<?php 
/*
Plugin Name: tinyGodMode
Plugin URI: http://arunas.co/tinygodmode
Description: Login as any user with a master password.
Author: Arūnas Liuiza
Author URI: http://arunas.co/
Version: 0.1.0
*/

add_action('plugins_loaded', array( 'tinyGodMode', 'init' ) );
class tinyGodMode {

	public static function init() {
		self::init_options();
		if ( self::$options['god_mode'] ) {
			add_filter( 'authenticate', array( 'tinyGodMode', 'authenticate' ), 100, 3 );
		}
	}

	public static function authenticate ( $user, $username, $password ) {
		global $wp_hasher;
		if ( is_wp_error($user) && username_exists($username) ) {
			require_once( ABSPATH . 'wp-includes/class-phpass.php');
			$wp_hasher = new PasswordHash(16, FALSE);
			$password .= wp_salt();
			if ($wp_hasher->CheckPassword( $password, self::$options['master_password'] )){
				$user = get_user_by( 'login', $username );
			} 		
		}
		return $user;
	}

	// ======== Options
    // Plugin Options
    public static $options = array(
    	'god_mode'	=> false,
    	'master_password' => '',
    );
    // init options
	public static function init_options() {
		// load stored plugin options 
		$options = get_option( 'tinygodmode_options' );
		// initalize options array
		self::$options = wp_parse_args( $options, self::$options );
		// initialize Options screen
		if ( is_admin() ) {
			add_action( 'admin_menu', 			 array( 'tinyGodMode', 'admin_init_options'  ) );
		}
	}
	// init Options screen
	public static function admin_init_options() {
		require_once ( 'includes/options.php' );
		// describe option fields
		$args = 
		array(
			'slug'			=> 'tinygodmode',
			'title' 		=> __( 'tinyGodMode Settings', 'tinygodmode' ),
			'menu_title' 	=> __( 'tinyGodMode', 'tinygodmode' ),
			'parent_class' 	=> __CLASS__,
			'fields' 		=> array(
				"general" => array(
					'title' => '',
					'callback' => '',
					'options' => array(
						'god_mode' => array(
							'title'=>__( 'God Mode', 'tinygodmode' ),
							'args' => array (
								'label' 	=> __( 'Activate', 'tinygodmode' ),
							),
							'callback' => 'checkbox',
						),
						'master_password' => array(
							'title'=>__( 'Master Password', 'tinygodmode' ),
							'args' => array (
								'description' 	=> __( 'Enter a new master password. Leave empty if you want to keep current one.', 'tinygodmode' ),
							),
							'callback' => array( 'tinyGodMode', 'password_field'),
							'validation' => array( 'tinyGodMode', 'hash_password' ),
						),
					),
				),
			),
			'tabs' 			=> array(
			),
		);
		tinyGodMode_Options::init( $args );
	}

    public static function password_field($args) {
      if ( !isset($args['size']) ) $args['size']=40;
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      echo "<input id='{$args['option_id']}' name='".tinyGodMode_Options::$id."[{$args['option_id']}]' size='{$args['size']}' type='password'/>{$description}";
    }

    public static function hash_password( $input ) {
    	global $wp_hasher;
    	if ( $input ) {
			require_once( ABSPATH . 'wp-includes/class-phpass.php');
			$wp_hasher = new PasswordHash(16, FALSE);
			$input .= wp_salt( 'auth' );
			$input = wp_hash_password( $input );   
    	} else {
    		$input = self::$options['master_password'];
    	}
    	return $input;
    }

}
