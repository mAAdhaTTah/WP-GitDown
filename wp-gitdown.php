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

  static $required = 'wp-markdown';
  
  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;

  public function __construct() {
    register_activation_hook(__FILE__,array(__CLASS__, 'install' ));
    add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'page_init' ) );
  }

  static function install() {
    self::dependentplugin_activate();
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

      // throw new Exception('Requires another plugin!');
      // exit();
      // @todo: Write better exit message
      exit ('Requires WP-Markdown.');
    }
  }
  
  /**
   * Add options page
   */
  public function add_plugin_page() {
    // This page will be under "Settings"
    add_options_page(
        'Settings Admin', 
        'My Settings', 
        'manage_options', 
        'my-setting-admin', 
        array( $this, 'create_admin_page' )
    );
  }

  /**
   * Options page callback
   */
  public function create_admin_page() {
    // Set class property
    $this->options = get_option( 'my_option_name' );
    ?>
    <div class="wrap">
        <?php screen_icon(); ?>
        <h2>My Settings</h2>           
        <form method="post" action="options.php">
        <?php
            // This prints out all hidden setting fields
            settings_fields( 'my_option_group' );   
            do_settings_sections( 'my-setting-admin' );
            submit_button(); 
        ?>
        </form>
    </div>
    <?php
  }

  /**
   * Register and add settings
   */
  public function page_init() {        
    register_setting(
        'my_option_group', // Option group
        'my_option_name', // Option name
        array( $this, 'sanitize' ) // Sanitize
    );

    add_settings_section(
        'setting_section_id', // ID
        'My Custom Settings', // Title
        array( $this, 'print_section_info' ), // Callback
        'my-setting-admin' // Page
    );  

    add_settings_field(
        'id_number', // ID
        'ID Number', // Title 
        array( $this, 'id_number_callback' ), // Callback
        'my-setting-admin', // Page
        'setting_section_id' // Section           
    );      

    add_settings_field(
        'title', 
        'Title', 
        array( $this, 'title_callback' ), 
        'my-setting-admin', 
        'setting_section_id'
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
   */
  public function print_section_info() {
    print 'Enter your settings below:';
  }

  /** 
   * Get the settings option array and print one of its values
   */
  public function id_number_callback() {
    printf(
        '<input type="text" id="id_number" name="my_option_name[id_number]" value="%s" />',
        isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
    );
  }

  /** 
   * Get the settings option array and print one of its values
   */
  public function title_callback() {
    printf(
        '<input type="text" id="title" name="my_option_name[title]" value="%s" />',
        isset( $this->options['title'] ) ? esc_attr( $this->options['title']) : ''
    );
  }
}

require_once('lib/Git.php');
$wordpress_gitdown = new WordPress_Gitdown();