<?php
/*
	Plugin Name: WP-Gitdown
	Plugin URI:
	Description:
	Author: James DiGioia
	Version:
	Author URI:
	Text Domain:
	Domain Path:
 */

class WordPress_Gitdown {
  /**
   * This plugin is required for this to run
   */
  static $required = 'wp-markdown';

  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;


  /**
   * __construct function.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    register_activation_hook(__FILE__,array(__CLASS__, 'install' ));
    require_once(dirname(__FILE__) . '/lib/Git.php');
    add_action( 'admin_menu', array( $this, 'gitdown_page' ) );
    add_action( 'admin_init', array( $this, 'gitdown_page_init' ) );
  }

  /**
   * install function.
   * Runs when plugin is activated
   *
   * @access public
   * @static
   * @return void
   */
  static function install() {
    self::dependentplugin_activate();
    // @todo: plugin to add notification to add git creds
  }

  /**
   * Check whether WP-Markdown is active
   *
   * @access public
   * @return void
   */
  static function dependentplugin_activate() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    if ( !is_plugin_active( self::$required . '/' . self::$required . '.php' ) ) {

      // deactivate dependent plugin
      deactivate_plugins( __FILE__);

      // throw new Exception and exit
      // @todo: Write better exit message
      exit ('<b>Requires WP-Markdown.</b>');
    }
  }

  /**
   * Add options page
   */
  public function gitdown_page() {
    // This page will be under "Settings"
    add_options_page(
      'WP Gitdown',
      'WP Gitdown Settings',
      'manage_options',
      'wp-gitdown',
      array( $this, 'gitdown_settings_page' )
    );
  }

  /**
   * Options page callback
   */
  public function gitdown_settings_page() {
    // Set class property
    $this->options = get_option('gitdown_settings'); ?>
    <div class="wrap">
    <h2>My Settings</h2>
    <form method="post" action="options.php">
    <?php
    // This prints out all hidden setting fields
    settings_fields( 'gitdown_settings' );
    do_settings_sections( 'gitdown_settings_admin' );
    // @todo: write Export All button and function
    submit_button();
?>
    </form>
    <?php
  }

  /**
   * Register and add settings
   */
  public function gitdown_page_init() {
    register_setting(
      'gitdown_settings', // Option group
      'gitdown_settings', // Option name
      array( $this, 'sanitize' ) // Sanitize
    );

    add_settings_section(
      'gitdown_settings_gitcreds', // ID
      'GitHub Credentials', // Title
      array( $this, 'gitcreds_section' ), // Callback
      'gitdown_settings_admin' // Page
    );

    add_settings_field(
      'github_username', // ID
      'GitHub Username', // Title
      array( $this, 'github_username' ), // Callback
      'gitdown_settings_admin', // Page
      'gitdown_settings_gitcreds' // Section
    );

    add_settings_field(
      'github_password', // ID
      'GitHub Password', // Title
      array( $this, 'github_password' ), // Callback
      'gitdown_settings_admin', // Page
      'gitdown_settings_gitcreds' // Section
    );
  }

  /**
   * Sanitize each setting field as needed
   *
   * @param array $input Contains all settings fields as array keys
   */
  public function sanitize( $input ) {
    $new_input = array();
    if( isset( $input['id_number'] ) )
      $new_input['id_number'] = absint( $input['id_number'] );

    if( isset( $input['title'] ) )
      $new_input['title'] = sanitize_text_field( $input['title'] );

    return $new_input;
  }

  /**
   * Print the Section text
   * @todo Write section text
   */
  public function gitcreds_section() {

  }

  /**
   * github_username function.
   *
   * @access public
   * @return void
   */
  public function github_username() {
    printf(
      '<input type="text" id="github_username" name="my_option_name[github_username]" value="%s" />',
      isset( $this->options['github_username'] ) ? esc_attr( $this->options['github_username']) : ''
    );
  }


  /**
   * github_password function.
   *
   * @access public
   * @return void
   * @todo Hash password before putting it into the database
   */
  public function github_password() {
    printf(
      '<input type="password" id="github_password" name="my_option_name[github_password]" value="%s" />',
      isset( $this->options['github_password'] ) ? esc_attr( $this->options['github_password']) : ''
    );
  }
}

$wordpress_gitdown = new WordPress_Gitdown();